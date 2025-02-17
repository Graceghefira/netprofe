FROM nginx:alpine as nginx-stage

# Install dependencies
RUN apk add --no-cache bash curl mysql-client redis supervisor

# Copy configuration
COPY ./nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf
COPY ./netpro /usr/share/nginx/html

# Add supervisord configuration
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose ports
EXPOSE 8081 8443 3307 6381

# Start supervisord to manage all services
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
