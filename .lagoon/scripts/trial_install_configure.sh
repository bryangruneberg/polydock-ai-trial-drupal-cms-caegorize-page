#!/bin/sh

LOCKFILE="/app/web/sites/default/files/.lagoon_trial_installed"

if [ -f "$LOCKFILE" ]; then
  echo "Site has already been installed"
elif [ -z "$AI_LLM_API_URL" ]; then
  echo "Please configure the AI_LLM_API_URL variable"
elif [ -z "$AI_LLM_API_TOKEN" ]; then
  echo "Please configure the AI_LLM_API_TOKEN variable"
else
  # Install site demo
  echo "Installing the site basics"
  drush site:install -y

  # Install the provider.
  echo "Installing the amazee.io AI provider"

  drush recipe /app/web/recipes/ai_provider_amazeeio_recipe \
    --input=ai_provider_amazeeio_recipe.llm_host=$AI_LLM_API_URL \
    --input=ai_provider_amazeeio_recipe.llm_api_key=$AI_LLM_API_TOKEN
  
  # Install the recipe.
  echo "Installing the amazee.io categorize page recipe"
  drush recipe /app/web/recipes/amazeeio_categorize_page

  # Clear the cache
  echo "Rebuilding of the Drupal cache"
  drush cr

  touch $LOCKFILE
  echo "Site install complete."
fi
