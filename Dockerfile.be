FROM nginx:alpine

# Periksa dan tambahkan grup/user jika belum ada
RUN getent group www-data || addgroup -S www-data && \
    getent passwd www-data || adduser -S www-data -G www-data

# Copy Nginx configuration
COPY ./nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Copy Laravel application files
COPY ./netpro /usr/share/nginx/html

# Expose Nginx and MySQL ports
EXPOSE 80 443 3306

# Run both Nginx and MySQL as the main CMD
CMD ["sh", "-c", "mysqld & nginx -g 'daemon off;'"]

