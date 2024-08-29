<?php

namespace Drupal\drupal_cms_installer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Provides a form to choose the site template and optional add-on recipes.
 *
 * @todo Present this as a mini project browser once
 *   https://www.drupal.org/i/3450629 is fixed.
 */
final class RecipesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupal_cms_installer_recipes_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#title'] = $this->t('What are your top goals?');
    $form['help']['#markup'] = $this->t('You can change your mind later.');

    $options = [
      'drupal_cms_multilingual' => $this->t('Multilingual support'),
      'drupal_cms_accessibility_tools' => $this->t('Accessibility tools'),
    ];

    $form['add_ons'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => [],
    ];
    $form['actions'] = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#button_type' => 'primary',
      ],
      'skip' => [
        '#type' => 'submit',
        '#value' => $this->t('Skip this step'),
      ],
      '#type' => 'actions',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    global $install_state;
    $install_state['parameters']['recipes'] = ['drupal_cms'];

    $pressed_button = $form_state->getTriggeringElement();
    // Only choose add-ons if the Next button was pressed.
    if ($pressed_button && end($pressed_button['#array_parents']) === 'submit') {
      $add_ons = $form_state->getValue('add_ons', []);
      $add_ons = array_filter($add_ons);
      array_push($install_state['parameters']['recipes'], ...array_values($add_ons));
    }
  }

}
