<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_installer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form to set the site name.
 */
final class SiteNameForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupal_cms_installer_site_name_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['system.site'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array &$install_state = NULL) {
    $form['site_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site name'),
      '#title_display' => 'invisible',
      '#required' => TRUE,
      '#default_value' => $install_state['forms']['install_configure_form']['site_name'] ?? '',
      '#config_target' => 'system.site:name',
    ];
    $form['#title'] = $this->t('Give your site a name');

    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = $this->t('Continue');

    return $form;
  }

}
