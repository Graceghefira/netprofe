FROM nginx:alpine

# Copy semua file HTML dari host ke direktori /var/www/html di dalam container
COPY . /var/www/html

# Copy konfigurasi Nginx yang sudah disesuaikan
COPY nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Expose port 80 dan 443
EXPOSE 80 443

# Jalankan Nginx
CMD ["nginx", "-g", "daemon off;"]
