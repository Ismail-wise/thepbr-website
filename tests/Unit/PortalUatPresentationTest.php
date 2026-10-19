<?php

use App\Services\Portal\ChatPresentation;

require_once __DIR__.'/../../scripts/pbr-upload-limits.php';

test('citation labels disappear without changing business numbers user prose or line breaks', function (): void {
    $text = "Capital: 300,000 THB [S1].\n\n1. Aye: 60% [S1], [S2], [S4]\n\n2. Min: 40% (S2)\nReceipt DEMO-019. [S1, S3]";
    expect(ChatPresentation::answer($text))->toBe("Capital: 300,000 THB.\n\n1. Aye: 60%\n\n2. Min: 40%\nReceipt DEMO-019.");
    expect(ChatPresentation::answer('Owner note LOTUS-EMBER-731; 1, 2, 3; <script>'))->toBe('Owner note LOTUS-EMBER-731; 1, 2, 3; <script>');
});

function uploadNginxFixture(string $php = 'include fastcgi_params; fastcgi_pass unix:/run/php/php8.4-fpm.sock;'): array
{
    return [
        '/etc/nginx/sites-enabled/pbr' => "# Keep comment { }\nserver { server_name other.example; root /srv/other; client_max_body_size 1m; }\nserver { listen 443 ssl; server_name thepbr.io www.thepbr.io; root /var/www/pbr/public; location / { try_files \$uri /index.php?\$query_string; } location ~ \\.php\$ { $php } }\n",
        '/etc/nginx/fastcgi_params' => 'fastcgi_param QUERY_STRING $query_string; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;',
    ];
}

test('upload repair changes only the app vhost and keeps explicit FastCGI parameters', function (): void {
    $files = uploadNginxFixture();
    $changes = PbrUploadLimits::transform($files, '/var/www/pbr/public');
    expect(array_keys($changes))->toBe(['/etc/nginx/sites-enabled/pbr']);
    $after = $changes['/etc/nginx/sites-enabled/pbr']['after'];
    expect($after)->toContain('server_name other.example; root /srv/other; client_max_body_size 1m;')
        ->toContain('client_max_body_size 12m;')->toContain('error_page 413 /pbr-upload-too-large.html;')
        ->toContain('fastcgi_param PHP_VALUE "upload_max_filesize=10M\\npost_max_size=12M";')
        ->toContain('include fastcgi_params;')->toContain('fastcgi_pass unix:/run/php/php8.4-fpm.sock;');
    $files['/etc/nginx/sites-enabled/pbr'] = $after;
    expect(PbrUploadLimits::transform($files, '/var/www/pbr/public'))->toBe([]);
});

test('upload repair handles a tighter location override and quoted braces', function (): void {
    $files = uploadNginxFixture('client_max_body_size 512k; include fastcgi_params; fastcgi_pass php; set $test "{quoted}";');
    $changes = PbrUploadLimits::transform($files, '/var/www/pbr/public');
    expect($changes['/etc/nginx/sites-enabled/pbr']['after'])->not->toContain('512k')->toContain('set $test "{quoted}";');
});

test('upload repair refuses custom PHP settings ambiguous roots and inherited parameter sets', function (string $php, string $root, string $reason): void {
    expect(fn () => PbrUploadLimits::transform(uploadNginxFixture($php), $root))->toThrow(RuntimeException::class, $reason);
})->with([
    ['include fastcgi_params; fastcgi_pass php; fastcgi_param PHP_VALUE "memory_limit=32M";', '/var/www/pbr/public', 'UPLOAD_CUSTOM_PHP_VALUES_REQUIRE_REVIEW'],
    ['fastcgi_pass php;', '/var/www/pbr/public', 'UPLOAD_INHERITED_FASTCGI_PARAMS_REQUIRE_REVIEW'],
    ['include fastcgi_params; fastcgi_pass php;', '/different/public', 'UPLOAD_NGINX_VHOST_REQUIRES_REVIEW'],
]);

test('configuration replacement preserves permissions and rejects intervening changes', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'pbr-upload-test-');
    try {
        chmod($path, 0640);file_put_contents($path, 'before');
        PbrUploadLimits::replace($path, 'before', 'after');
        clearstatcache(true, $path);
        expect(file_get_contents($path))->toBe('after')->and(fileperms($path) & 0777)->toBe(0640);
        file_put_contents($path, 'external change');
        expect(fn () => PbrUploadLimits::replace($path, 'after', 'before'))->toThrow(RuntimeException::class, 'UPLOAD_CONFIG_CHANGED_REQUIRES_REVIEW');
        expect(file_get_contents($path))->toBe('external change');
    } finally {unlink($path);}
});
