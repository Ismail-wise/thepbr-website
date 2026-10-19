<?php
// Scoped nginx/PHP-FPM upload repair. The JSON plan is a private configuration
// backup; never print it. No global PHP or other virtual-host settings change.
final class PbrUploadLimits
{
    private const PHP_VALUE = 'upload_max_filesize=10M'."\n".'post_max_size=12M';
    private const REQUEST_PATTERN = '"^/portal/workspaces/[0-9]+/knowledge(?:\\\\?|$)"';

    private static function parse(string $text): array
    {
        $pattern = <<<'REGEX'
~\s+|\#[^\n]*|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|[{};]|(?:\\.|[^\s{};\#"'])+~
REGEX;
        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        $tokens = array_values(array_filter($matches[0], fn ($t) => ! ctype_space($t[0]) && ! str_starts_with($t[0], '#')));
        $i = 0;
        $read = function (bool $nested = false) use (&$read, &$i, $tokens): array {
            $entries = [];
            while ($i < count($tokens)) {
                if ($tokens[$i][0] === '}') {
                    if (! $nested) throw new RuntimeException('UPLOAD_NGINX_UNBALANCED_CONFIG');
                    $i++;
                    return $entries;
                }
                $words = [];
                while ($i < count($tokens) && ! in_array($tokens[$i][0], ['{', '}', ';'], true)) $words[] = $tokens[$i++];
                if ($words === [] || ! isset($tokens[$i]) || $tokens[$i][0] === '}') throw new RuntimeException('UPLOAD_NGINX_UNSUPPORTED_CONFIG');
                $entry = ['name' => $words[0][0], 'args' => array_column(array_slice($words, 1), 0), 'start' => $words[0][1], 'children' => null];
                $boundary = $tokens[$i++];
                if ($boundary[0] === '{') {
                    $entry['open'] = $boundary[1];
                    $entry['children'] = $read(true);
                    $entry['close'] = $tokens[$i - 1][1];
                }
                $entry['end'] = $tokens[$i - 1][1] + strlen($tokens[$i - 1][0]);
                $entries[] = $entry;
            }
            if ($nested) throw new RuntimeException('UPLOAD_NGINX_UNBALANCED_CONFIG');
            return $entries;
        };
        return $read();
    }

    private static function value(string $text): string
    {
        return str_starts_with($text, '"') ? stripcslashes(substr($text, 1, -1)) : trim($text, "'");
    }

    private static function needsRaise(string $size): bool
    {
        if (! preg_match('/^(\d+)([kmg]?)$/iD', self::value($size), $m)) throw new RuntimeException('UPLOAD_BODY_SIZE_REQUIRES_REVIEW');
        if ((int) $m[1] === 0) return false; // Existing unlimited settings stay unchanged.
        return (int) $m[1] * (1024 ** (['' => 0, 'k' => 1, 'm' => 2, 'g' => 3][strtolower($m[2])])) < 12 * 1024 * 1024;
    }

    public static function transform(array $files, string $publicRoot): array
    {
        $trees = array_map(self::parse(...), $files);
        $changes = [];
        $phpLocations = 0;
        // Resolve only includes that nginx itself listed in its validated dump.
        $scope = function (array $entries, array $seen = []) use (&$scope, $trees): array {
            $expanded = [];
            foreach ($entries as $entry) {
                if ($entry['name'] === 'include') {
                    $pattern = self::value($entry['args'][0] ?? '');
                    if (! str_starts_with($pattern, '/')) $pattern = '/etc/nginx/'.$pattern;
                    foreach ($trees as $path => $tree) {
                        if (fnmatch($pattern, $path)) {
                            if (isset($seen[$path])) throw new RuntimeException('UPLOAD_NGINX_INCLUDE_CYCLE');
                            $expanded = [...$expanded, ...$scope($tree, $seen + [$path => true])];
                        }
                    }
                } else $expanded[] = $entry;
            }
            return $expanded;
        };
        $httpLimit = '1m';
        foreach ($trees as $tree) foreach ($tree as $node) {
            if ($node['name'] === 'http' && $node['children'] !== null) foreach ($scope($node['children']) as $entry) {
                if ($entry['name'] === 'client_max_body_size') $httpLimit = $entry['args'][0];
            }
        }
        foreach ($files as $path => $text) {
            $edits = [];
            $visit = function (array $entries) use (&$visit, &$edits, &$phpLocations, $publicRoot, $scope, $httpLimit): void {
                foreach ($entries as $server) {
                    if ($server['children'] === null) continue;
                    if ($server['name'] !== 'server') {$visit($server['children']);continue;}
                    $names = [];$root = null;
                    foreach ($server['children'] as $entry) {
                        if ($entry['name'] === 'server_name') $names = array_map(self::value(...), $entry['args']);
                        if ($entry['name'] === 'root') $root = self::value($entry['args'][0] ?? '');
                    }
                    if (! in_array('thepbr.io', $names, true)) continue;
                    // Redirect-only hosts have no PHP upload endpoint to change.
                    if ($root === null) continue;
                    if (array_diff($names, ['thepbr.io', 'www.thepbr.io']) !== [] || rtrim($root, '/') !== rtrim($publicRoot, '/')) {
                        throw new RuntimeException('UPLOAD_NGINX_VHOST_REQUIRES_REVIEW');
                    }
                    $hasServerLimit = false;$hasErrorPage = false;
                    foreach ($scope($server['children']) as $entry) {
                        if ($entry['name'] === 'client_max_body_size') {
                            $hasServerLimit = true;
                            if (self::needsRaise($entry['args'][0]) && ! in_array($entry, $server['children'], true)) throw new RuntimeException('UPLOAD_INCLUDED_BODY_LIMIT_REQUIRES_REVIEW');
                        }
                        if ($entry['name'] === 'error_page' && in_array('413', $entry['args'], true)) {
                            if ($entry['args'] !== ['413', '/pbr-upload-too-large.html']) throw new RuntimeException('UPLOAD_CUSTOM_ERROR_PAGE_REQUIRES_REVIEW');
                            $hasErrorPage = true;
                        }
                    }
                    if (! $hasErrorPage) $edits[] = [$server['open'] + 1, $server['open'] + 1, "\n    error_page 413 /pbr-upload-too-large.html;"];
                    $limits = function (array $nodes, bool $top = false) use (&$limits, &$edits, &$hasServerLimit): void {
                        foreach ($nodes as $node) {
                            if ($node['name'] === 'client_max_body_size') {
                                if ($top) $hasServerLimit = true;
                                if (self::needsRaise($node['args'][0])) $edits[] = [$node['start'], $node['end'], 'client_max_body_size 12m;'];
                            }
                            if ($node['children'] !== null) $limits($node['children']);
                        }
                    };
                    $limits($server['children'], true);
                    if (! $hasServerLimit && self::needsRaise($httpLimit)) $edits[] = [$server['open'] + 1, $server['open'] + 1, "\n    client_max_body_size 12m;"];
                    $locations = function (array $nodes) use (&$locations, &$edits, &$phpLocations, $scope): void {
                        foreach ($nodes as $node) {
                            if ($node['children'] === null) continue;
                            if ($node['name'] !== 'location') {$locations($node['children']);continue;}
                            $expanded = $scope($node['children']);
                            $pass = false;$params = false;$managed = false;
                            foreach ($expanded as $entry) {
                                if ($entry['name'] === 'fastcgi_pass') $pass = true;
                                if ($entry['name'] === 'fastcgi_param') {
                                    $params = true;
                                    if (in_array($entry['args'][0] ?? '', ['PHP_VALUE', 'PHP_ADMIN_VALUE'], true)) {
                                        if (($entry['args'][0] ?? '') !== 'PHP_VALUE' || ($entry['args'][1] ?? '') !== '$pbr_knowledge_upload_php' || $managed) {
                                            throw new RuntimeException('UPLOAD_CUSTOM_PHP_VALUES_REQUIRE_REVIEW');
                                        }
                                        $managed = true;
                                    }
                                }
                            }
                            if ($pass) {
                                // Adding a first fastcgi_param would discard an
                                // inherited parameter set. Require an explicit
                                // existing set in this location or its includes.
                                if (! $params) throw new RuntimeException('UPLOAD_INHERITED_FASTCGI_PARAMS_REQUIRE_REVIEW');
                                if ($managed) {
                                    $defaults = array_values(array_filter($node['children'], fn ($e) => $e['name'] === 'set' && $e['args'] === ['$pbr_knowledge_upload_php', '""']));
                                    $rules = array_values(array_filter($node['children'], fn ($e) => $e['name'] === 'if' && $e['args'] === ['($request_uri', '~', self::REQUEST_PATTERN, ')']));
                                    if (count($defaults) !== 1 || count($rules) !== 1 || count($rules[0]['children']) !== 1) throw new RuntimeException('UPLOAD_MANAGED_SETTINGS_CHANGED');
                                    $setting = $rules[0]['children'][0];
                                    if ($setting['name'] !== 'set' || ($setting['args'][0] ?? '') !== '$pbr_knowledge_upload_php' || self::value($setting['args'][1] ?? '') !== self::PHP_VALUE) throw new RuntimeException('UPLOAD_MANAGED_SETTINGS_CHANGED');
                                }
                                $phpLocations++;
                                if (! $managed) $edits[] = [$node['open'] + 1, $node['open'] + 1,
                                    "\n        set \$pbr_knowledge_upload_php \"\";\n".
                                    '        if ($request_uri ~ '.self::REQUEST_PATTERN.") {\n".
                                    "            set \$pbr_knowledge_upload_php \"upload_max_filesize=10M\\npost_max_size=12M\";\n        }\n".
                                    "        fastcgi_param PHP_VALUE \$pbr_knowledge_upload_php;\n"];
                            }
                            $locations($node['children']);
                        }
                    };
                    $locations($server['children']);
                }
            };
            $visit($trees[$path]);
            usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
            $after = $text;
            foreach ($edits as [$start, $end, $replacement]) $after = substr_replace($after, $replacement, $start, $end - $start);
            if ($after !== $text) $changes[$path] = ['before' => $text, 'after' => $after];
        }
        if ($phpLocations === 0) throw new RuntimeException('UPLOAD_APP_PHP_LOCATION_NOT_FOUND');
        return $changes;
    }

    public static function replace(string $path, string $expected, string $replacement): void
    {
        clearstatcache(true, $path);
        if (is_link($path) || ! is_file($path) || realpath($path) !== $path || file_get_contents($path) !== $expected) throw new RuntimeException('UPLOAD_CONFIG_CHANGED_REQUIRES_REVIEW');
        $stat = stat($path);
        $tmp = tempnam(dirname($path), '.pbr-upload-');
        try {
            if (file_put_contents($tmp, $replacement) !== strlen($replacement) || ! chmod($tmp, $stat['mode'] & 0777) || ! chown($tmp, $stat['uid']) || ! chgrp($tmp, $stat['gid']) || ! rename($tmp, $path)) throw new RuntimeException('UPLOAD_CONFIG_WRITE_FAILED');
        } finally {if (is_file($tmp)) unlink($tmp);}
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;
try {
    $action = $argv[1] ?? '';
    if ($action === 'plan') {
        $dump = file_get_contents($argv[2]);
        preg_match_all('/^# configuration file (\/[^\r\n]+):$/m', $dump, $matches);
        $files = [];
        foreach ($matches[1] as $path) $files[$path] = file_get_contents($path);
        if ($files === []) throw new RuntimeException('UPLOAD_NGINX_DUMP_EMPTY');
        $changes = PbrUploadLimits::transform($files, rtrim($argv[3], '/').'/public');
        $plan = [];
        foreach ($changes as $path => $change) {
            $real = realpath($path);
            if (! $real || ! str_starts_with($real, '/etc/nginx/') || is_link($real)) throw new RuntimeException('UPLOAD_NGINX_PATH_REQUIRES_REVIEW');
            if (isset($plan[$real]) && $plan[$real] !== $change) throw new RuntimeException('UPLOAD_DUPLICATE_CONFIG_PATH');
            $plan[$real] = $change;
        }
        file_put_contents($argv[4], json_encode($plan, JSON_THROW_ON_ERROR));chmod($argv[4], 0600);
        echo "SCOPED_UPLOAD_CONFIGURATION_PLAN=OK\n";
    } elseif (in_array($action, ['apply', 'verify', 'rollback'], true)) {
        $plan = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
        foreach ($plan as $path => $change) {
            $current = file_get_contents($path);
            $expected = $action === 'apply' ? $change['before'] : $change['after'];
            if ($action === 'rollback' && $current === $change['before']) continue;
            if ($current !== $expected) throw new RuntimeException('UPLOAD_CONFIG_CHANGED_REQUIRES_REVIEW');
        }
        foreach ($plan as $path => $change) {
            if ($action === 'apply') PbrUploadLimits::replace($path, $change['before'], $change['after']);
            if ($action === 'rollback' && file_get_contents($path) !== $change['before']) PbrUploadLimits::replace($path, $change['after'], $change['before']);
        }
        echo 'SCOPED_UPLOAD_CONFIGURATION_'.strtoupper($action)."=OK\n";
    } else throw new RuntimeException('UPLOAD_UNKNOWN_ACTION');
} catch (Throwable $error) {
    $safe = preg_match('/^UPLOAD_[A-Z_]+$/D', $error->getMessage()) ? $error->getMessage() : 'UPLOAD_CONFIGURATION_CHECK_FAILED';
    fwrite(STDERR, $safe."\n");exit(2);
}
