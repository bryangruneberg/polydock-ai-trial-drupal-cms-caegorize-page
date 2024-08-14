<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\FunctionalTests\Installer\InstallerTestBase;

/**
 * @group drupal_cms
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
   * Tests basic expectations of a successful Drupal CMS install.
   */
  public function testPostInstallState(): void {
    // Set the site name to a randomly generated title.
    $expected_title = $this->getRandomGenerator()->name();
    $this->config('system.site')->set('name', $expected_title)->save();

    $this->drupalGet('<front>');
    $assert_session = $this->assertSession();
    $assert_session->titleEquals($expected_title);

    // The installer should have uninstalled itself.
    $this->assertFalse($this->container->getParameter('install_profile'));

    // Ensure that there are non-core extensions installed, which proves that
    // recipes were applied during site installation.
    $this->assertContribInstalled($this->container->get(ModuleExtensionList::class));
    $this->assertContribInstalled($this->container->get(ThemeExtensionList::class));

    $this->drupalLogout();
    // Antibot prevents non-JS functional tests from logging in, so disable it.
    $this->config('antibot.settings')->set('form_ids', [])->save();

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
