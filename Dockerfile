FROM webdevops/php-nginx:8.2-alpine

WORKDIR /app

# Copy project files
COPY . .

# Install production dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader

# Set storage/cache permissions
RUN chown -R application:application /app/storage /app/bootstrap/cache

# Set Laravel public as document root
ENV WEB_DOCUMENT_ROOT=/app/public

# Build-time cache optimization
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Runtime: decode Firebase creds (if set), migrate, symlink, then start server
CMD sh -c '\
  if [ -n "$FIREBASE_CREDENTIALS_BASE64" ]; then \
    mkdir -p /app/storage/app && \
    echo "$FIREBASE_CREDENTIALS_BASE64" | base64 -d > /app/storage/app/firebase-credentials.json && \
    chown application:application /app/storage/app/firebase-credentials.json; \
  fi && \
  php artisan config:clear && \
  php artisan config:cache && \
  php artisan migrate --force && \
  php artisan storage:link 2>/dev/null || true && \
  /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf'
