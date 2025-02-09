FROM uselagoon/php-8.4-cli-drupal:latest

COPY composer.* /app/
COPY assets /app/assets
RUN composer install --no-dev \
    && cp -r /app/vendor/drupal/cms/web/profiles/drupal_cms_installer /app/web/profiles/
RUN mkdir -p -v -m775 /app/web/sites/default/files

# Define where the Drupal Root is located
ENV WEBROOT=web
