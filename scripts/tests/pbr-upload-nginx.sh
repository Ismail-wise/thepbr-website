#!/usr/bin/env bash
# Syntax-check transformed virtual hosts with real nginx on a disposable CI
# runner. Never reads or changes an active host configuration, or starts nginx.
set -Eeuo pipefail
command -v nginx >/dev/null
ROOT="$(pwd)"
FIXTURE="$(mktemp -d /tmp/pbr-upload-nginx-XXXXXXXX)"
trap 'rm -rf -- "$FIXTURE"' EXIT
mkdir "$FIXTURE/logs"
php -r '
require $argv[1]."/scripts/pbr-upload-limits.php";
$fixture=$argv[2];
$source="server { listen 127.0.0.1:18088; server_name thepbr.io; root /var/www/pbr/public; location / { try_files \$uri /index.php?\$query_string; } location ~ \\.php\$ { include /etc/nginx/fastcgi_params; fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name; fastcgi_pass unix:/tmp/pbr-synthetic-fpm.sock; } }";
$files=[$fixture."/vhost.conf"=>$source,"/etc/nginx/fastcgi_params"=>file_get_contents("/etc/nginx/fastcgi_params")];
$changes=PbrUploadLimits::transform($files,"/var/www/pbr/public");
$files[$fixture."/vhost.conf"]=$changes[$fixture."/vhost.conf"]["after"];
if(PbrUploadLimits::transform($files,"/var/www/pbr/public")!==[])exit(2);
file_put_contents($fixture."/vhost.conf",$files[$fixture."/vhost.conf"]);
file_put_contents($fixture."/nginx.conf","pid ".$fixture."/nginx.pid;\nerror_log stderr;\nevents {}\nhttp { include ".$fixture."/vhost.conf; }\n");
' "$ROOT" "$FIXTURE"
nginx -t -p "$FIXTURE/" -c "$FIXTURE/nginx.conf"
echo PBR_SCOPED_UPLOAD_REAL_NGINX_SYNTAX=PASS
