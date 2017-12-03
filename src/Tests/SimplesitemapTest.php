<?php

namespace Drupal\simple_sitemap\Tests;

/**
 * Tests Simple XML sitemap functional integration.
 *
 * @package Drupal\simple_sitemap\Tests
 * @group simple_sitemap
 */
class SimplesitemapTest extends SimplesitemapTestBase {

  /**
   * Verify sitemap.xml has the link to the front page after first generation.
   */
  public function testInitialGeneration() {
    $this->generator->generateSitemap('nobatch');
    $this->drupalGet('sitemap.xml');
    $this->assertRaw('urlset');
    $this->assertText($GLOBALS['base_url']);
    $this->assertText('1.0');
    $this->assertText('daily');
  }

  /**
   * Test adding a custom link to the sitemap.
   */
  public function testAddCustomLink() {
    $this->generator->addCustomLink('/node/' . $this->node->id(), ['priority' => 0.2, 'changefreq' => 'monthly'])
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('node/' . $this->node->id());
    $this->assertText('0.2');
    $this->assertText('monthly');
  }

  /**
   * Test default settings of custom links.
   */
  public function testAddCustomLinkDefaults() {
    $this->generator->removeCustomLinks()
      ->addCustomLink('/node/' . $this->node->id())
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('node/' . $this->node->id());
    $this->assertText('0.5');
    $this->assertNoRaw('changefreq');
  }

  /**
   * Test removing custom links from the sitemap.
   */
  public function testRemoveCustomLink() {
    $this->generator->addCustomLink('/node/' . $this->node->id())
      ->removeCustomLink('/node/' . $this->node->id())
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertNoText('node/' . $this->node->id());
  }

  /**
   * Test removing all custom paths from the sitemap settings.
   */
  public function testRemoveCustomLinks() {
    $this->generator->removeCustomLinks()
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertNoText($GLOBALS['base_url']);
  }

  /**
   * Tests setting bundle settings.
   */
  public function testSetBundleSettings() {

    $this->assertFalse($this->generator->bundleIsIndexed('node', 'page'));

    // Index new bundle.
    $this->generator->removeCustomLinks()
      ->setBundleSettings('node', 'page', ['index' => TRUE, 'priority' => 0.5, 'changefreq' => 'hourly'])
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('node/' . $this->node->id());
    $this->assertText('0.5');
    $this->assertText('hourly');

    $this->assertTrue($this->generator->bundleIsIndexed('node', 'page'));

    // Only change bundle priority.
    $this->generator->setBundleSettings('node', 'page', ['priority' => 0.9])
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('node/' . $this->node->id());
    $this->assertNoText('0.5');
    $this->assertText('0.9');

    // Only change bundle changefreq.
    $this->generator->setBundleSettings('node', 'page', ['changefreq' => 'daily'])
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('node/' . $this->node->id());
    $this->assertNoText('hourly');
    $this->assertText('daily');

    // Remove changefreq setting.
    $this->generator->setBundleSettings('node', 'page', ['changefreq' => ''])
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('node/' . $this->node->id());
    $this->assertNoRaw('changefreq');
    $this->assertNoText('daily');

    // Index two bundles.
    $this->drupalCreateContentType(['type' => 'blog']);

    $node3 = $this->createNode(['title' => 'Node3', 'type' => 'blog']);
    $this->generator->setBundleSettings('node', 'page', ['index' => TRUE])
      ->setBundleSettings('node', 'blog', ['index' => TRUE])
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('node/' . $this->node->id());
    $this->assertText('node/' . $node3->id());

    // Set bundle 'index' setting to false.
    $this->generator->setBundleSettings('node', 'page', ['index' => FALSE])
      ->setBundleSettings('node', 'blog', ['index' => FALSE])
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertNoText('node/' . $this->node->id());
    $this->assertNoText('node/' . $node3->id());
  }

  /**
   * Test default settings of bundles.
   */
  public function testSetBundleSettingsDefaults() {

    $this->generator->setBundleSettings('node', 'page')
      ->removeCustomLinks()
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('node/' . $this->node->id());
    $this->assertText('0.5');
    $this->assertNoRaw('changefreq');
  }

  /**
   * Test the lastmod parameter in different scenarios.
   */
  public function testLastmod() {

    // Entity links should have 'lastmod'.
    $this->generator->setBundleSettings('node', 'page')
      ->removeCustomLinks()
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertRaw('lastmod');

    // Entity custom links should have 'lastmod'.
    $this->generator->setBundleSettings('node', 'page', ['index' => FALSE])
      ->addCustomLink('/node/' . $this->node->id())
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertRaw('lastmod');

    // Non-entity custom links should not have 'lastmod'.
    $this->generator->removeCustomLinks()
      ->addCustomLink('/')
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertNoRaw('lastmod');
  }

  /**
   * Tests the duplicate setting.
   *
   * @todo On second generation too many links in XML output here?
   */
  public function testRemoveDuplicatesSetting() {
    $this->generator->setBundleSettings('node', 'page', ['index' => true])
      ->addCustomLink('/node/1')
      ->saveSetting('remove_duplicates', TRUE)
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertUniqueText('node/' . $this->node->id());

    $this->generator->saveSetting('remove_duplicates', FALSE)
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertNoUniqueText('node/' . $this->node->id());
  }

  /**
   * Test max links setting and the sitemap index.
   */
  public function testMaxLinksSetting() {
    $this->generator->setBundleSettings('node', 'page')
      ->saveSetting('max_links', 1)
      ->removeCustomLinks()
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('sitemaps/1/sitemap.xml');
    $this->assertText('sitemaps/2/sitemap.xml');

    $this->drupalGet('sitemaps/1/sitemap.xml');
    $this->assertText('node/' . $this->node->id());
    $this->assertText('0.5');
    $this->assertNoText('node/' . $this->node2->id());

    $this->drupalGet('sitemaps/2/sitemap.xml');
    $this->assertText('node/' . $this->node2->id());
    $this->assertText('0.5');
    $this->assertNoText('node/' . $this->node->id());
  }

  /**
   * Test setting the base URL.
   */
  public function testBaseUrlSetting() {
    $this->generator->setBundleSettings('node', 'page')
      ->saveSetting('base_url', 'http://base_url_test')
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('http://base_url_test');

    // Set base URL in the sitemap index.
    $this->generator->saveSetting('max_links', 1)
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('http://base_url_test/sitemaps/1/sitemap.xml');
  }


  /**
   * @todo testSkipNonExistentTranslations
   */

  /**
   * Test cacheability of the response.
   */
  public function testCacheability() {
    $this->generator->setBundleSettings('node', 'page')
      ->generateSitemap('nobatch');

    // Verify the cache was flushed and node is in the sitemap.
    $this->drupalGet('sitemap.xml');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'MISS');
    $this->assertText('node/' . $this->node->id());

    // Verify the sitemap is taken from cache on second call and node is in the sitemap.
    $this->drupalGet('sitemap.xml');
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'HIT');
    $this->assertText('node/' . $this->node->id());
  }

  /**
   * Test overriding of bundle settings for a single entity.
   *
   * @todo Test if overrides are removed if bundle settings are identical.
   */
  public function testSetEntityInstanceSettings() {
    $this->generator->setBundleSettings('node', 'page')
      ->removeCustomLinks()
      ->setEntityInstanceSettings('node', $this->node->id(), ['priority' => 0.1, 'changefreq' => 'never'])
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('node/' . $this->node->id());
    $this->assertText('0.1');
    $this->assertText('never');
  }

  /**
   * Test indexing an atomic entity (here: a user)
   * @todo Not working
   */
/*  public function testAtomicEntityIndexation() {
    $user = $this->createPrivilegedUser();
    $this->generator->setBundleSettings('user')
      ->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertNoText('user/' . $user->id());

    user_role_grant_permissions(0, ['access user profiles']);
    $this->generator->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('user/' . $user->id());
  }*/

  /**
   * @todo Test indexing menu.
   */

  /**
   * @todo Test deleting a bundle.
   */

  /**
   * Test disabling sitemap support for an entity type.
   */
  public function testDisableEntityType() {
    $this->generator->setBundleSettings('node', 'page')
      ->disableEntityType('node');

    $this->drupalLogin($this->createPrivilegedUser());
    $this->drupalGet('admin/structure/types/manage/page');
    $this->assertNoText('Simple XML sitemap');

    $this->generator->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertNoText('node/' . $this->node->id());

    $this->assertFalse($this->generator->entityTypeIsEnabled('node'));
  }

  /**
   * Test enabling sitemap support for an entity type.
   */
  public function testEnableEntityType() {
    $this->generator->disableEntityType('node')
      ->enableEntityType('node')
      ->setBundleSettings('node', 'page');

    $this->drupalLogin($this->createPrivilegedUser());
    $this->drupalGet('admin/structure/types/manage/page');
    $this->assertText('Simple XML sitemap');

    $this->generator->generateSitemap('nobatch');

    $this->drupalGet('sitemap.xml');
    $this->assertText('node/' . $this->node->id());

    $this->assertTrue($this->generator->entityTypeIsEnabled('node'));
  }
}
