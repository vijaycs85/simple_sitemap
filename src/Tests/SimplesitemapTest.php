<?php

/**
 * @file
 * Contains \Drupal\simple_sitemap\Tests\SimplesitemapTest
 */

namespace Drupal\simple_sitemap\Tests;

use Drupal\simple_sitemap\Simplesitemap;
use Drupal\simpletest\WebTestBase;

/**
 * Tests Simple XML sitemap integration.
 *
 * @group Simplesitemap
 */
class SimplesitemapTest extends WebTestBase {

  protected $dumpHeaders = TRUE;
  protected $strictConfigSchema = FALSE;

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
    $this->config('simple_sitemap.settings')
      ->set('entity_types', ['node_type' => ['page' =>  ['index' => 1, 'priority' => '0.5']]])
      ->save();
  }

  /**
   * Test Simple sitemap integration.
   */
  public function testSimplesitemap() {
    $sitemap = new Simplesitemap;
    $sitemap->generate_sitemap();

    // Verify sitemap.xml can be cached.
    $this->drupalGet('sitemap.xml');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'MISS');
    $this->drupalGet('sitemap.xml');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'HIT');

    /* @var $node \Drupal\Node\NodeInterface */
    $node = $this->createNode(['title' => 'A new page']);

    // Generate new sitemap.
    $sitemap->generate_sitemap();

    // Verify the cache was flushed and node is in the sitemap.
    $this->drupalGet('sitemap.xml');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'MISS');
    $this->assertText('node/' . $node->id());
    $this->drupalGet('sitemap.xml');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'HIT');
    $this->assertText('node/' . $node->id());
  }
}
