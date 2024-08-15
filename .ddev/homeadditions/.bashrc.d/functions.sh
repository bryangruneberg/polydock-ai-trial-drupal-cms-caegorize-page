refresh_repositories () {
  for recipe in $(find /var/www/html -maxdepth 1 -type d -name 'drupal_cms*'); do
    composer config --global repositories.$(basename $recipe) path $recipe
  done
}

create_recipe () {
  dir=/var/www/html/$1

  if [ -d $dir ]; then
    echo "This recipe already exists at $dir."
    return 1
  fi
  mkdir $dir
  composer init --no-interaction --working-dir=$dir --name=drupal/$1 --type=drupal-recipe --require="drupal/core:>=10.3"
  composer config version dev-main --working-dir=$dir

  refresh_repositories
  composer require --no-update --working-dir=$(dirname $dir)/drupal_cms "drupal/$1:*"
  composer update drupal/drupal_cms
}

apply_recipe () {
  cd $DDEV_DOCROOT && php core/scripts/drupal recipe $1
}
