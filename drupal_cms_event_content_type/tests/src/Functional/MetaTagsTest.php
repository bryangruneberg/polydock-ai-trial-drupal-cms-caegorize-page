<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_event_content_type\Functional;

use Composer\InstalledVersions;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\Entity\File;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\media\Entity\Media;
use Drupal\Tests\BrowserTestBase;

/**
 * @group drupal_cms_event_content_type
 */
class MetaTagsTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function test(): void {
    $this->applyRecipe(InstalledVersions::getInstallPath('drupal/drupal_cms_event_content_type'));

    $random = $this->getRandomGenerator();

    $file_uri = uniqid('public://') . '.png';
    $file_uri = $random->image($file_uri, '100x100', '200x200');
    $this->assertFileExists($file_uri);
    $file = File::create(['uri' => $file_uri]);
    $file->save();
    $file_url = $this->container->get(FileUrlGeneratorInterface::class)
      ->generateAbsoluteString($file_uri);

    $media = Media::create([
      'name' => $random->word(16),
      'bundle' => 'image',
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'Not random alt text...',
      ],
    ]);
    $media->save();

    // If we create an event, all the expected meta tags should be there.
    $node = $this->createNode([
      'type' => 'event',
      'body' => [
        'summary' => 'Not a random summary...',
        'value' => $random->paragraphs(1),
      ],
      'moderation_state' => 'published',
      'field_location' => [
        'country_code' => 'US',
        'administrative_area' => 'DC',
        'locality' => 'Washington',
        'postal_code' => 20560,
        'address_line1' => '1000 Jefferson Dr SW',
      ],
      'field_image' => $media,
    ]);
    $this->drupalGet($node->toUrl());
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->elementAttributeContains('css', 'meta[name="description"]', 'content', 'Not a random summary...');
    $assert_session->elementAttributeContains('css', 'meta[property="og:description"]', 'content', 'Not a random summary...');
    $assert_session->elementAttributeContains('css', 'meta[property="og:title"]', 'content', $node->getTitle());
    $assert_session->elementAttributeContains('css', 'meta[property="og:type"]', 'content', $node->type->entity->label());
    $assert_session->elementAttributeContains('css', 'link[rel="image_src"]', 'href', $file_url);
    $assert_session->elementAttributeContains('css', 'meta[property="og:image"]', 'content', $file_url);
    $assert_session->elementAttributeContains('css', 'meta[property="og:image:alt"]', 'content', 'Not random alt text...');
    $assert_session->elementAttributeContains('css', 'meta[property="og:country_name"]', 'content', 'United States');
    $assert_session->elementAttributeContains('css', 'meta[property="og:locality"]', 'content', 'Washington');
    $assert_session->elementAttributeContains('css', 'meta[property="og:region"]', 'content', 'DC');
    $assert_session->elementAttributeContains('css', 'meta[property="og:postal_code"]', 'content', '20560');
    $assert_session->elementAttributeContains('css', 'meta[property="og:street_address"]', 'content', '1000 Jefferson Dr SW');
  }

}
