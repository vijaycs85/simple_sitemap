<?php

namespace Drupal\simple_sitemap\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests Simple XML sitemap functional integration.
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
  protected $generator;
  protected $node;
  protected $node2;

  /**
   * Implements setup().
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page']);
    $this->node = $this->createNode(['title' => 'Node', 'type' => 'page']);
    $this->node2 = $this->createNode(['title' => 'Node2', 'type' => 'page']);
    $this->generator = \Drupal::service('simple_sitemap.generator');
  }

  /**
   * Verify sitemap.xml has been generated on install (custom path generation).
   */
  public function testInitialGeneration() {
    $this->drupalGet('sitemap.xml');
    $this->assertRaw('urlset');
    $this->assertRaw('http');
  }

  public function testGenerateSitemap() {

    // Set up the module.
    $this->generator->setBundleSettings('node', 'page', ['index' => 1, 'priority' => '0.5'])
      ->generateSitemap('nobatch');

    // Verify the cache was flushed and node is in the sitemap.
    $this->drupalGet('sitemap.xml');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'MISS');
    $this->assertText('node/' . $this->node->id());
    $this->drupalGet('sitemap.xml');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'HIT');
    $this->assertText('node/' . $this->node->id());
  }

  /**
   * Test overriding of bundle entities.
   */
  public function testSetEntityInstanceSettings() {
    $this->generator->setBundleSettings('node', 'page', ['index' => 1, 'priority' => '0.5'])
      ->setEntityInstanceSettings('node', $this->node->id(), ['index' => 1, 'priority' => '0.1'])
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('0.1');
  }

  /**
   * Test disabling sitemap support for an entity type.
   */
  public function testDisableEntityType() {
    $this->generator->setBundleSettings('node', 'page', ['index' => 1, 'priority' => '0.5'])
      ->disableEntityType('node')
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertNoText('node/');
  }

  /**
   * Test enabling sitemap support for an entity type.
   */
  public function testEnableEntityType() {
    $this->generator->disableEntityType('node')
      ->enableEntityType('nobatch')
      ->setBundleSettings('node', 'page', ['index' => 1, 'priority' => '0.5'])
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('node/');
  }

  /**
   * Test sitemap index.
   */
  public function testSitemapIndex() {
    $this->generator->setBundleSettings('node', 'page', ['index' => 1, 'priority' => '0.5'])
      ->saveSetting('max_links', 1)
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('sitemaps/2/sitemap.xml');
  }

  /**
   * Test adding a custom link to the sitemap.
   */
  public function testAddCustomLink() {
    $this->generator->addCustomLink('/node/' . $this->node->id(), ['priority' => '0.2'])
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('0.2');
  }

  /**
   * Test removing custom links from the sitemap.
   */
  public function testRemoveCustomLink() {
    $this->generator->addCustomLink('/node/' . $this->node->id(), ['priority' => '0.2'])
      ->removeCustomLink('/node/' . $this->node->id())
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertNoText('0.2');
  }
}
