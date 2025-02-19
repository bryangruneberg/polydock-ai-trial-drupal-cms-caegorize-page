FROM uselagoon/php-8.4-cli-drupal:latest AS cli

COPY composer.* /app/
COPY assets /app/assets
COPY .lagoon /app/.lagoon
RUN composer install --no-dev \
    && cp -r /app/vendor/drupal/cms/web/profiles/drupal_cms_installer /app/web/profiles/
RUN mkdir -p -v -m775 /app/web/sites/default/

# set up scaffolding for drupal-cms

COPY .lagoon/scripts/scaffold.sh /lagoon/entrypoints/999-drupal-cms-scaffold.sh
RUN /lagoon/entrypoints/999-drupal-cms-scaffold.sh

# Define where the Drupal Root is located
ENV WEBROOT=web

FROM uselagoon/nginx-drupal:latest AS nginx

COPY --from=cli /app /app

ENV WEBROOT=web

FROM uselagoon/php-8.4-fpm:latest AS php
 
COPY --from=cli /app /app
# php-fpm needs to have the composer binary and rsync available
COPY --from=cli /usr/local/bin/composer /usr/local/bin/composer
