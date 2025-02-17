#!/bin/sh

FLAG_FILE="/app/scaffolding_done.flag"

cd /app

# Check if the flag file exists
if [ ! -f "$FLAG_FILE" ]; then
    git config --global --add safe.directory /app
    composer install --no-dev
    cp -r /app/vendor/drupal/cms/web/profiles/drupal_cms_installer /app/web/profiles/
    cp /app/drush/Commands/contrib/drupal_integrations/assets/* /app/web/sites/default
    mv /app/web/sites/default/initial.settings.php /app/web/sites/default/settings.php
    
    # Create the flag file to indicate the script has run
    touch "$FLAG_FILE"
else
    echo "The initialization script has already run."
fi