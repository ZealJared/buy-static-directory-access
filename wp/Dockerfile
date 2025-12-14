
FROM wordpress:latest

# Install WP-CLI and mariadb-client
RUN apt-get update \
    && apt-get install -y mariadb-client \
    && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp \
    && rm -rf /var/lib/apt/lists/*



# Ensure AllowOverride All for /var/www/html in apache2.conf for permalinks
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && echo '<Directory /var/www/html/>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>' >> /etc/apache2/apache2.conf


# Set PHP upload and post size limits for large file uploads
RUN echo "upload_max_filesize = 10G\npost_max_size = 10G\nmax_execution_time = 0" > /usr/local/etc/php/conf.d/99-custom-uploads.ini

# Add custom init script to the official entrypoint init directory
COPY docker-entrypoint-init.sh /docker-entrypoint-init.d/10-custom-init.sh
RUN chmod +x /docker-entrypoint-init.d/10-custom-init.sh


# Add non-root user and set permissions
RUN useradd -ms /bin/bash wpuser \
    && chown -R wpuser:wpuser /var/www/html /docker-entrypoint-init.d

# Set wrapper entrypoint to run custom logic then official entrypoint
COPY custom-entrypoint.sh /custom-entrypoint.sh
COPY post-start-setup.sh /wp/post-start-setup.sh
RUN chmod +x /custom-entrypoint.sh /wp/post-start-setup.sh
ENTRYPOINT ["/custom-entrypoint.sh"]

USER wpuser
