<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_installer\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form to set user 1's login credentials.
 */
final class AccountForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly bool $superUserAccessPolicy,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(EntityTypeManagerInterface::class),
      $container->getParameter('security.enable_super_user') ?? TRUE,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupal_cms_installer_account_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $install_state = NULL): array {
    $form['#title'] = $this->t('Create your user account');

    $form['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#required' => TRUE,
      '#default_value' => $install_state['forms']['install_configure_form']['account']['mail'] ?? '',
    ];
    $form['password'] = [
      '#type' => 'password_confirm',
      '#required' => TRUE,
      '#default_value' => $install_state['forms']['install_configure_form']['account']['pass'] ?? [],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Continue'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('user');

    $account = $storage->load(1)
      ->setUsername('admin')
      ->setPassword($form_state->getValue('password'))
      ->setEmail($form_state->getValue('mail'))
      ->activate();

    // Ensure user 1 has an administrator role if one exists. This is adapted
    // from \Drupal\Core\Installer\Form\SiteConfigureForm.
    $admin_roles = $this->entityTypeManager->getStorage('user_role')
      ->getQuery()
      ->condition('is_admin', TRUE)
      ->execute();
    if (array_intersect($account->getRoles(), $admin_roles) === []) {
      if ($admin_roles) {
        $account->addRole(reset($admin_roles));
      }
      elseif ($this->superUserAccessPolicy === FALSE) {
        $this->messenger()->addWarning($this->t(
          'The user %username does not have administrator access. For more information, see the documentation on <a href="@secure-user-1-docs">securing the admin super user</a>.',
          [
            '%username' => $account->getDisplayName(),
            '@secure-user-1-docs' => 'https://www.drupal.org/docs/administering-a-drupal-site/security-in-drupal/securing-the-admin-super-user-1#s-disable-the-super-user-access-policy',
          ]
        ));
      }
    }
    $storage->save($account);
  }

}
