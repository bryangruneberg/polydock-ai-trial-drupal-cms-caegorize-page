<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_installer\Functional;

use Behat\Mink\Element\ElementInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\FunctionalTests\Installer\InstallerTestBase;
use Drupal\user\Entity\User;

/**
 * @group drupal_cms_installer
 */
class InstallerTest extends InstallerTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'drupal_cms_installer';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUpSettings(): void {
    // Drupal CMS inserts a page here to select a template recipe, and add-ons.
    // Right now, there's only one option.
    $this->assertSession()->fieldValueEquals('template', 'drupal_cms');

    // Choose all the add-ons!
    $page = $this->getSession()->getPage();
    $page->checkField('add_ons[drupal_cms_accessibility_tools]');
    $page->checkField('add_ons[drupal_cms_multilingual]');

    // Continue with the normal database settings form.
    $page->pressButton('Save and continue');
    parent::setUpSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpProfile(): void {
    // Nothing to do here; Drupal CMS marks itself as a distribution so that the
    // installer will automatically select it.
  }

  /**
   * {@inheritdoc}
   */
  protected function visitInstaller(): void {
    parent::visitInstaller();

    $tasks = array_map(
      fn (ElementInterface $item) => $item->getText(),
      $this->assertSession()->elementExists('css', 'ol.task-list')->findAll('css', 'li'),
    );
    // Core's "Configure site" step should be hidden.
    $this->assertNotContains('Configure site', $tasks);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpSite(): void {
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Give your site a name');
    $assert_session->elementAttributeExists('named', ['field', 'Site name'], 'required');
    // We have to use submitForm() to ensure that batch operations, redirects,
    // and so forth in the remaining install tasks get done.
    $this->submitForm(['Site name' => 'Drupal CMS'], 'Continue');

    $assert_session->pageTextContains('Create your user account');
    $assert_session->elementAttributeExists('named', ['field', 'Email address'], 'required');
    $assert_session->elementAttributeExists('named', ['field', 'Password'], 'required');
    $assert_session->elementAttributeExists('named', ['field', 'Confirm password'], 'required');
    $this->submitForm([
      'mail' => 'test@drupal.cms',
      'password[pass1]' => 'pastafazoul',
      'password[pass2]' => 'pastafazoul',
    ], 'Continue');

    $this->isInstalled = TRUE;
  }

  /**
   * Tests basic expectations of a successful Drupal CMS install.
   */
  public function testPostInstallState(): void {
    // The site name and site-wide email address should have been set.
    // @see \Drupal\drupal_cms_installer\Form\SiteNameForm
    // @see drupal_cms_installer_set_site_mail()
    $site_config = $this->config('system.site');
    $this->assertSame('Drupal CMS', $site_config->get('name'));
    $this->assertStringStartsWith('no-reply@', $site_config->get('mail'));

    // The default time zone should be UTC.
    // @see drupal_cms_installer_set_time_zone()
    $this->assertSame('UTC', $this->config('system.date')->get('timezone.default'));

    // Update Status should be installed, and user 1 should be getting its
    // notifications.
    // @see drupal_cms_installer_install_update_status()
    $this->assertTrue($this->container->get(ModuleHandlerInterface::class)->moduleExists('update'));
    $account = User::load(1);
    $this->assertContains($account->getEmail(), $this->config('update.settings')->get('notifications.emails'));
    // User 1 should have an administrator role.
    // @see \Drupal\drupal_cms_installer\Form\AccountForm::submitForm()
    $this->assertContains('administrator', $account->getRoles());

    // The installer should have uninstalled itself.
    // @see drupal_cms_installer_uninstall_myself()
    $this->assertFalse($this->container->getParameter('install_profile'));

    // Ensure that there are non-core extensions installed, which proves that
    // recipes were applied during site installation.
    $this->assertContribInstalled($this->container->get(ModuleExtensionList::class));
    $this->assertContribInstalled($this->container->get(ThemeExtensionList::class));

    // Antibot prevents non-JS functional tests from logging in, so disable it.
    $this->config('antibot.settings')->set('form_ids', [])->save();
    // Log out so we can test that user 1's credentials were properly saved.
    $this->drupalLogout();

    // It should be possible to log in with your email address.
    $page = $this->getSession()->getPage();
    $page->fillField('name', 'test@drupal.cms');
    $page->fillField('pass', 'pastafazoul');
    $page->pressButton('Log in');
    $assert_session = $this->assertSession();
    $assert_session->addressEquals('/user/1');
    $this->drupalLogout();

    // It should also be possible to log in with the username, which is
    // defaulted to `admin` by the installer.
    $page->fillField('name', 'admin');
    $page->fillField('pass', 'pastafazoul');
    $page->pressButton('Log in');
    $assert_session->addressEquals('/user/1');
    $this->drupalLogout();

    $editor = $this->drupalCreateUser();
    $editor->addRole('content_editor')->save();
    $this->drupalLogin($editor);

    // Test basic configuration of the content types.
    $node_types = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node_type')
      ->getQuery()
      ->execute();
    $this->assertNotEmpty($node_types);

    foreach ($node_types as $node_type) {
      $node = $this->createNode(['type' => $node_type]);
      $url = $node->toUrl();

      // Content editors should be able to clone all content types.
      $this->drupalGet($url);
      $this->getSession()->getPage()->clickLink('Clone');
      $assert_session->statusCodeEquals(200);
      // All content types should have pretty URLs.
      $this->assertNotSame('/node/' . $node->id(), $url->toString());
    }
  }

  /**
   * Asserts that any number of contributed extensions are installed.
   *
   * @param \Drupal\Core\Extension\ExtensionList $list
   *   An extension list.
   */
  private function assertContribInstalled(ExtensionList $list): void {
    $core_dir = $this->container->getParameter('app.root') . '/core';

    foreach (array_keys($list->getAllInstalledInfo()) as $name) {
      // If the extension isn't part of core, great! We're done.
      if (!str_starts_with($list->getPath($name), $core_dir)) {
        return;
      }
    }
    $this->fail('No contributed extensions are installed.');
  }

}
