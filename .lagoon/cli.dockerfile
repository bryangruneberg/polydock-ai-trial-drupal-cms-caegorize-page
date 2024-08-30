FROM uselagoon/php-8.3-cli-drupal:latest

COPY . /app

RUN /app/.lagoon/scripts/refresh-components \
    # && ln -s -f ./project_template/composer.json \
    # && composer require amazeeio/drupal_integrations \
    # && composer config extra.drupal-scaffold --json '{ "allowed-packages": [ "amazeeio/drupal_integrations" ] }' \
    # && composer config allow-plugins true \
    && composer install --no-dev

RUN chmod 775 /app/web/sites/default/files \
    && mkdir -p -v -m775 /app/web/sites/default/files/translations

# RUN /app/.lagoon/scripts/refresh-components \
#     && ln -s -f ./project_template/composer.json \
#     && composer install --no-dev \
#     && install -m775 /app/web/sites/default/default.settings.php /app/web/sites/default/settings.php \
#     && cp /app/.lagoon/assets/settings.local.php /app/web/sites/default/settings.local.php

# RUN chmod 775 /app/web/sites/default/files \
#     && mkdir -p -v -m775 /app/web/sites/default/files/translations

# Define where the Drupal Root is located
ENV WEBROOT=web
