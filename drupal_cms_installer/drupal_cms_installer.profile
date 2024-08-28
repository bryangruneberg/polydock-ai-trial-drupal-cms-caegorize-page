<?php

declare(strict_types=1);

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Recipe\InputCollector;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\drupal_cms_installer\Form\AccountForm;
use Drupal\drupal_cms_installer\Form\RecipesForm;
use Drupal\drupal_cms_installer\Form\SiteNameForm;
use Drupal\user\Entity\User;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Implements hook_install_tasks().
 */
function drupal_cms_installer_install_tasks(): array {
  return [
    'drupal_cms_installer_site_name_form' => [
      'display' => FALSE,
      'type' => 'form',
      'function' => SiteNameForm::class,
    ],
    'drupal_cms_installer_account_form' => [
      'display' => FALSE,
      'type' => 'form',
      'function' => AccountForm::class,
    ],
    'drupal_cms_installer_set_site_mail' => [
      // Sets the site-wide email address to a no-reply.
    ],
    'drupal_cms_installer_set_time_zone' => [
      // Sets the default time zone to UTC.
    ],
    'drupal_cms_installer_install_update_status' => [
      // Install the Update Status module and configure user 1 to receive
      // email notifications from it.
    ],
    'drupal_cms_installer_uninstall_myself' => [
      // As a final task, this profile should uninstall itself.
    ],
  ];
}

/**
 * Implements hook_install_tasks_alter().
 */
function drupal_cms_installer_install_tasks_alter(array &$tasks, array $install_state): void {
  $insert_before = function (string $key, array $additions) use (&$tasks): void {
    $key = array_search($key, array_keys($tasks), TRUE);
    if ($key === FALSE) {
      return;
    }
    // This isn't very clean, but it's the only way to positionally splice into
    // an associative (and therefore by definition unordered) array.
    $tasks_before = array_slice($tasks, 0, $key, TRUE);
    $tasks_after = array_slice($tasks, $key, NULL, TRUE);
    $tasks = $tasks_before + $additions + $tasks_after;
  };
  $insert_before('install_settings_form', [
    'drupal_cms_installer_choose_recipes' => [
      'display_name' => t('Choose template & add-ons'),
      'type' => 'form',
      'run' => array_key_exists('recipes', $install_state['parameters']) ? INSTALL_TASK_SKIP : INSTALL_TASK_RUN_IF_REACHED,
      'function' => RecipesForm::class,
    ],
  ]);

  // Bypass core's site configuration form, which is a mess.
  $tasks['install_configure_form']['run'] = INSTALL_TASK_SKIP;
  $tasks['install_configure_form']['display'] = FALSE;

  // Wrap the install_profile_modules() function, which returns a batch job, and
  // add all the necessary operations to apply the chosen template recipe.
  $tasks['install_profile_modules']['function'] = 'drupal_cms_installer_apply_recipes';
}

/**
 * Implements hook_form_alter() for install_settings_form.
 *
 * @see \Drupal\Core\Installer\Form\SiteSettingsForm
 */
function drupal_cms_installer_form_install_settings_form_alter(array &$form): void {
  // Default to SQLite, if available, because it doesn't require any additional
  // configuration.
  $sqlite = 'Drupal\sqlite\Driver\Database\sqlite';
  if (array_key_exists($sqlite, $form['driver']['#options']) && extension_loaded('pdo_sqlite')) {
    $form['driver']['#default_value'] = $sqlite;
  }
}

/**
 * Implements hook_form_alter() for install_configure_form.
 */
function drupal_cms_installer_form_install_configure_form_alter(array &$form): void {
  ['composer' => $composer, 'rsync' => $rsync] = \Drupal::configFactory()
    ->get('package_manager.settings')
    ->get('executables');

  $finder = new ExecutableFinder();
  $finder->addSuffix('.phar');
  $composer ??= $finder->find('composer');
  $rsync ??= $finder->find('rsync');

  $form['package_manager'] = [
    '#type' => 'fieldset',
    '#title' => t('Package Manager settings (advanced)'),
    '#description' => t("To install extensions in the administrative interface, Drupal needs to know where Composer and <code>rsync</code> are. This will be auto-detected if possible. If you leave these blank, you can still browse for extensions but you'll need to use the command line to install them."),
  ];
  $form['package_manager']['composer'] = [
    '#type' => 'textfield',
    '#title' => t('Full path to <code>composer</code> or <code>composer.phar</code>'),
    '#default_value' => $composer,
  ];
  $form['package_manager']['rsync'] = [
    '#type' => 'textfield',
    '#title' => t('Full path to <code>rsync</code>'),
    '#default_value' => $rsync,
  ];
  $form['#submit'][] = '_drupal_cms_installer_install_configure_form_submit';
}

/**
 * Submit callback for install_configure_form.
 *
 * Sets the full paths to Composer and rsync, if available, and enables
 * installing projects via the Project Browser UI.
 */
function _drupal_cms_installer_install_configure_form_submit(array &$form, FormStateInterface $form_state): void {
  $composer = $form_state->getValue('composer');
  $rsync = $form_state->getValue('rsync');

  if ($composer && $rsync) {
    \Drupal::configFactory()
      ->getEditable('package_manager.settings')
      ->set('executables', [
        'composer' => $composer,
        'rsync' => $rsync,
      ])
      ->save();

    \Drupal::configFactory()
      ->getEditable('project_browser.admin_settings')
      ->set('allow_ui_install', TRUE)
      ->save();
  }
}

/**
 * Runs a batch job that applies the template and add-on recipes.
 *
 * @param array $install_state
 *   An array of information about the current installation state.
 *
 * @return array
 *   The batch job definition.
 */
function drupal_cms_installer_apply_recipes(array &$install_state): array {
  $batch = install_profile_modules($install_state);

  $input_collector = \Drupal::classResolver(InputCollector::class);
  $cookbook_path = \Drupal::root() . '/recipes';

  foreach ($install_state['parameters']['recipes'] as $recipe) {
    $recipe = Recipe::createFromDirectory($cookbook_path . '/' . $recipe);
    $input_collector->prepare($recipe);

    foreach (RecipeRunner::toBatchOperations($recipe) as $operation) {
      $batch['operations'][] = $operation;
    }
  }
  return $batch;
}

/**
 * Sets the site-wide email address to a no-reply.
 */
function drupal_cms_installer_set_site_mail(): void {
  \Drupal::configFactory()
    ->getEditable('system.site')
    ->set('mail', 'no-reply@' . \Drupal::request()->getHost())
    ->save();
}

/**
 * Sets the default time zone to UTC.
 */
function drupal_cms_installer_set_time_zone(): void {
  \Drupal::configFactory()
    ->getEditable('system.date')
    ->set('timezone.default', 'UTC')
    ->save();
}

/**
 * Installs and configures core's Update Status module.
 */
function drupal_cms_installer_install_update_status(): void {
  \Drupal::service(ModuleInstallerInterface::class)->install(['update']);

  \Drupal::configFactory()
    ->getEditable('update.settings')
    ->set('notifications.emails', [
      User::load(1)->getEmail(),
    ])
    ->save();
}

/**
 * Uninstalls this install profile, as a final step.
 */
function drupal_cms_installer_uninstall_myself(): void {
  \Drupal::service(ModuleInstallerInterface::class)->uninstall([
    'drupal_cms_installer',
  ]);
}
