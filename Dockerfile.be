FROM nginx:alpine

# Install MySQL server and basic dependencies
RUN apk add --no-cache \
    mysql mysql-client bash openrc && \
    rc-update add mysql default

# Configure MySQL (optional: update paths as needed)
COPY ./mysql/my.cnf /etc/mysql/my.cnf

# Initialize MySQL data directory
RUN mysql_install_db --user=mysql --ldata=/var/lib/mysql

# Copy Nginx configuration
COPY ./nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Copy Laravel application files
COPY ./netpro /usr/share/nginx/html

# Expose Nginx and MySQL ports
EXPOSE 80 443 3306

# Run both Nginx and MySQL as the main CMD
CMD ["sh", "-c", "service mysql start && nginx -g 'daemon off;'"]
