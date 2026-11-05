#!/usr/bin/env bash
set -euo pipefail
qa_root="$GITHUB_WORKSPACE/output/operational-uat-runtime"
qa_app="$qa_root/app"
qa_user="$(id -un)"
qa_group="$(id -gn)"
if ! command -v nginx >/dev/null || [ ! -x /usr/sbin/php-fpm8.4 ]; then
  sudo apt-get update -qq
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y nginx php8.4-fpm 2>&1 | tee "$qa_root/server-packages.log"
fi
fpm_binary=/usr/sbin/php-fpm8.4
"$fpm_binary" -v
nginx -v
cat > "$qa_root/fpm.conf" <<EOF
[global]
pid = $qa_root/fpm.pid
error_log = $qa_root/fpm-error.log
daemonize = no
log_level = notice
process_control_timeout = 10s
[qa]
user = $qa_user
group = $qa_group
listen = $qa_root/fpm.sock
listen.owner = $qa_user
listen.group = $qa_group
listen.mode = 0660
pm = dynamic
pm.max_children = 8
pm.start_servers = 2
pm.min_spare_servers = 2
pm.max_spare_servers = 4
pm.max_requests = 0
catch_workers_output = yes
clear_env = yes
security.limit_extensions = .php
php_admin_value[error_log] = $qa_root/fpm-worker.log
php_admin_flag[log_errors] = on
EOF
cat > "$qa_root/nginx.conf" <<EOF
user $qa_user $qa_group;
worker_processes 1;
pid $qa_root/nginx.pid;
error_log $qa_root/nginx-error.log warn;
events { worker_connections 1024; }
http {
  include /etc/nginx/mime.types;
  default_type application/octet-stream;
  log_format qa_no_query '\$remote_addr [\$time_local] "\$request_method \$uri" \$status \$body_bytes_sent \$request_time';
  access_log $qa_root/nginx-access.log qa_no_query;
  sendfile on;
  server {
    listen 127.0.0.1:8876;
    server_name 127.0.0.1;
    root $qa_app/public;
    index index.php;
    client_max_body_size 10m;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /index.php {
      include /etc/nginx/fastcgi_params;
      fastcgi_param SCRIPT_FILENAME \$document_root/index.php;
      fastcgi_pass unix:$qa_root/fpm.sock;
      fastcgi_read_timeout 120s;
    }
    location ~ \\.php$ { return 404; }
    location ~ /\\. { deny all; }
  }
}
EOF
# Root-owned service logs were unreadable by the unprivileged QA collector.
# Create the files before service startup so evidence remains accessible.
for qa_log in fpm-error.log nginx-error.log nginx-access.log; do
  sudo touch "$qa_root/$qa_log"
  sudo chown "$qa_user:$qa_group" "$qa_root/$qa_log"
  sudo chmod 0600 "$qa_root/$qa_log"
done
sudo "$fpm_binary" -t -y "$qa_root/fpm.conf"
sudo nginx -t -p "$qa_root/" -c "$qa_root/nginx.conf"
sudo "$fpm_binary" -F -y "$qa_root/fpm.conf" > "$qa_root/fpm.out" 2>&1 &
sudo nginx -p "$qa_root/" -c "$qa_root/nginx.conf" -g 'daemon off;' > "$qa_root/nginx.out" 2>&1 &
for i in $(seq 1 30); do if curl -fsS http://127.0.0.1:8876/up > /dev/null; then break; fi; sleep 1; done
curl -fsS http://127.0.0.1:8876/up > /dev/null
printf 'Nginx and PHP-FPM QA are healthy on loopback; eight FastCGI workers configured.\n'
