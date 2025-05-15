FROM alpine:latest

# Install Apache, PHP 8.2, and required extensions
RUN apk update && apk add --no-cache \
    apache2 \
    php82 \
    php82-apache2 \
    php82-mysqli \
    php82-pdo \
    php82-pdo_mysql \
    php82-session \
    php82-opcache \
    php82-mbstring \
    php82-zlib \
    php82-curl \
    php82-json \
    php82-phar \
    php82-dom \
    php82-fileinfo \
    php82-tokenizer \
    php82-ctype \
    php82-simplexml \
    && rm -rf /var/cache/apk/*

# Copy application files
COPY ./uptime /var/www/localhost/htdocs/

# Remove default index.html
RUN rm -f /var/www/localhost/htdocs/index.html

# Set correct permissions
RUN chown -R apache:apache /var/www/localhost/htdocs && chmod -R 755 /var/www/localhost/htdocs

# Enable mod_rewrite and PHP in Apache
RUN sed -i '/^#LoadModule rewrite_module/s/^#//' /etc/apache2/httpd.conf && \
    sed -i '/^#LoadModule php_module/s/^#//' /etc/apache2/httpd.conf && \
    echo "AddHandler php-script .php" >> /etc/apache2/httpd.conf && \
    echo "DirectoryIndex index.php index.html" >> /etc/apache2/httpd.conf && \
    echo '<Directory "/var/www/localhost/htdocs">' >> /etc/apache2/httpd.conf && \
    echo '    AllowOverride All' >> /etc/apache2/httpd.conf && \
    echo '    Require all granted' >> /etc/apache2/httpd.conf && \
    echo '</Directory>' >> /etc/apache2/httpd.conf

# Enable rewrite module in config
RUN echo "ServerName localhost" >> /etc/apache2/httpd.conf

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["/usr/sbin/httpd", "-D", "FOREGROUND"]
