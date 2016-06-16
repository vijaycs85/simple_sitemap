<?php
/**
 * @file
 * Contains \Drupal\simple_sitemap\Tests\SimplesitemapTest
 */

namespace Drupal\simple_sitemap\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests Simple XML sitemap integration.
 *
 * @group simple_sitemap
 */
class SimplesitemapTest extends WebTestBase {

  protected $dumpHeaders = TRUE;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['simple_sitemap', 'node'];

  /**
   * Implements setup().
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page']);
  }

  /**
   * Test Simple sitemap integration.
   */
  public function testSimplesitemap() {

    // Verify sitemap.xml has been generated on install (custom path generation).
    $this->drupalGet('sitemap.xml');
    $this->assertRaw('urlset');
    $this->assertRaw('http');

    /* @var $node \Drupal\Node\NodeInterface */
    $this->createNode(['title' => 'Node 1', 'type' => 'page']);
    $node = $this->createNode(['title' => 'Node 2', 'type' => 'page']);

    // Set up the module.
    $sitemap = \Drupal::service('simple_sitemap.generator');
    $sitemap->setBundleSettings('node', 'page', ['index' => 1, 'priority' => '0.5']);

    // Verify the cache was flushed and node is in the sitemap.
    $sitemap->generateSitemap('nobatch');
    $this->drupalGet('sitemap.xml');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'MISS');
    $this->assertText('node/' . $node->id());
    $this->drupalGet('sitemap.xml');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'HIT');
    $this->assertText('node/' . $node->id());

    // Test overriding of bundle entities.
    $sitemap->setEntityInstanceSettings('node', $node->id(), ['index' => 1, 'priority' => '0.1']);
    $sitemap->generateSitemap('nobatch');
    $this->drupalGet('sitemap.xml');
    $this->assertText('0.1');

    // Test sitemap index.
    $sitemap->saveSetting('max_links', 1);
    $sitemap->generateSitemap('nobatch');
    $this->drupalGet('sitemap.xml');
    $this->assertText('sitemaps/2/sitemap.xml');

    $sitemap->saveSetting('max_links', 2000);

    // Test disabling sitemap support for an entity type.
    $sitemap->disableEntityType('node');
    $sitemap->generateSitemap('nobatch');
    $this->drupalGet('sitemap.xml');
    $this->assertNoText('node/');

    // Test adding a custom link to the sitemap.
    $sitemap->addCustomLink('/node/' . $node->id(), ['priority' => '0.2']);
    $sitemap->generateSitemap('nobatch');
    $this->drupalGet('sitemap.xml');
    $this->assertText('0.2');

    // Test removing custom links from the sitemap.
    $sitemap->removeCustomLink('/node/' . $node->id());
    $sitemap->generateSitemap('nobatch');
    $this->drupalGet('sitemap.xml');
    $this->assertNoText('0.2');
  }
}
