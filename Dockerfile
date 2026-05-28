FROM webdevops/php-nginx:8.4-alpine

WORKDIR /app

# Copy project files
COPY . .

# Install production dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader

# Set storage/cache permissions
RUN chown -R application:application /app/storage /app/bootstrap/cache

# Symlink CA certificates to match the MYSQL_ATTR_SSL_CA (/etc/ssl/cert.pem) path on Alpine
RUN ln -sf /etc/ssl/certs/ca-certificates.crt /etc/ssl/cert.pem

# Set Laravel public as document root
ENV WEB_DOCUMENT_ROOT=/app/public

# Create startup provisioning script (runs before supervisord)
RUN printf '#!/bin/bash\n\
set -e\n\
\n\
# Decode Firebase credentials from env var\n\
if [ -n "$FIREBASE_CREDENTIALS_BASE64" ]; then\n\
  mkdir -p /app/storage/app\n\
  echo "$FIREBASE_CREDENTIALS_BASE64" | base64 -d > /app/storage/app/firebase-credentials.json\n\
  chown application:application /app/storage/app/firebase-credentials.json\n\
fi\n\
\n\
# Laravel runtime setup\n\
cd /app\n\
php artisan config:clear\n\
php artisan config:cache\n\
php artisan route:cache\n\
php artisan view:cache\n\
php artisan migrate --force\n\
php artisan storage:link 2>/dev/null || true\n' > /opt/docker/provision/entrypoint.d/99-laravel.sh \
  && chmod +x /opt/docker/provision/entrypoint.d/99-laravel.sh

CMD ["/opt/docker/bin/entrypoint.sh", "supervisord"]
