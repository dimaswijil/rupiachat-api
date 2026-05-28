FROM webdevops/php-nginx:8.2-alpine

# Setel working directory ke /app
WORKDIR /app

# Salin seluruh berkas proyek ke container
COPY . .

# Izinkan Composer berjalan sebagai root dan instal dependensi produksi
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader

# Setel izin akses folder storage dan cache agar Laravel bisa menulis log/session
RUN chown -R application:application /app/storage /app/bootstrap/cache

# Setel folder publik Laravel sebagai Document Root web server
ENV WEB_DOCUMENT_ROOT=/app/public

# Jalankan perintah optimasi cache Laravel bawaan
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Expose port web server (Render akan otomatis melakukan binding ke port yang sesuai)
EXPOSE 80
