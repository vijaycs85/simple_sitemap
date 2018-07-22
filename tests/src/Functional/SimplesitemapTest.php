<?php

namespace Drupal\Tests\simple_sitemap\Functional;

use Drupal\Core\Url;

/**
 * Tests Simple XML sitemap functional integration.
 *
 * @group simple_sitemap
 */
class SimplesitemapTest extends SimplesitemapTestBase {

  /**
   * Verify sitemap.xml has the link to the front page after first generation.
   */
  public function testInitialGeneration() {
    $this->generator->generateSitemap('nobatch');
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('urlset');
    $this->assertSession()->responseContains(Url::fromRoute('<front>')->setAbsolute()->toString());
    $this->assertSession()->responseContains('1.0');
    $this->assertSession()->responseContains('daily');
  }

  /**
   * Test custom link.
   */
  public function testAddCustomLink() {
    $this->generator->addCustomLink('/node/' . $this->node->id(), ['priority' => 0.2, 'changefreq' => 'monthly'])
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.2');
    $this->assertSession()->responseContains('monthly');

    $this->drupalLogin($this->privilegedUser);

    $this->drupalGet('admin/config/search/simplesitemap/custom');
    $this->assertSession()->pageTextContains('/node/' . $this->node->id() . ' 0.2 monthly');

    $this->generator->addCustomLink('/node/' . $this->node->id(), ['changefreq' => 'yearly'])
      ->generateSitemap('nobatch');

    $this->drupalGet('admin/config/search/simplesitemap/custom');
    $this->assertSession()->pageTextContains('/node/' . $this->node->id() . ' yearly');
  }

  /**
   * Test default settings of custom links.
   */
  public function testAddCustomLinkDefaults() {
    $this->generator->removeCustomLinks()
      ->addCustomLink('/node/' . $this->node->id())
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseNotContains('changefreq');
  }

  /**
   * Test removing custom paths from the sitemap settings.
   */
  public function testRemoveCustomLinks() {

    // Test removing one custom path from the sitemap.
    $this->generator->addCustomLink('/node/' . $this->node->id())
      ->removeCustomLinks('/node/' . $this->node->id())
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseNotContains('node/' . $this->node->id());

    // Test removing all custom paths from the sitemap.
    $this->generator->removeCustomLinks()
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseNotContains(Url::fromRoute('<front>')->setAbsolute()->toString());
  }

  /**
   * Tests setting bundle settings.
   *
   * @todo Add form tests
   */
  public function testSetBundleSettings() {
    $this->assertFalse($this->generator->bundleIsIndexed('node', 'page'));

    // Index new bundle.
    $this->generator->removeCustomLinks()
      ->setBundleSettings('node', 'page', [
        'index' => TRUE,
        'priority' => 0.5,
        'changefreq' => 'hourly',
      ])
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseContains('hourly');

    $this->assertTrue($this->generator->bundleIsIndexed('node', 'page'));

    // Only change bundle priority.
    $this->generator->setBundleSettings('node', 'page', ['priority' => 0.9])
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseNotContains('0.5');
    $this->assertSession()->responseContains('0.9');

    // Only change bundle changefreq.
    $this->generator->setBundleSettings('node', 'page', ['changefreq' => 'daily'])
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseNotContains('hourly');
    $this->assertSession()->responseContains('daily');

    // Remove changefreq setting.
    $this->generator->setBundleSettings('node', 'page', ['changefreq' => ''])
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseNotContains('changefreq');
    $this->assertSession()->responseNotContains('daily');

    // Index two bundles.
    $this->drupalCreateContentType(['type' => 'blog']);

    $node3 = $this->createNode(['title' => 'Node3', 'type' => 'blog']);
    $this->generator->setBundleSettings('node', 'page', ['index' => TRUE])
      ->setBundleSettings('node', 'blog', ['index' => TRUE])
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('node/' . $node3->id());

    // Set bundle 'index' setting to false.
    $this->generator->setBundleSettings('node', 'page', ['index' => FALSE])
      ->setBundleSettings('node', 'blog', ['index' => FALSE])
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);

    $this->assertSession()->responseNotContains('node/' . $this->node->id());
    $this->assertSession()->responseNotContains('node/' . $node3->id());
  }

  /**
   * Test default settings of bundles.
   */
  public function testSetBundleSettingsDefaults() {
    $this->generator->setBundleSettings('node', 'page')
      ->removeCustomLinks()
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseNotContains('changefreq');
  }

  /**
   * Test the lastmod parameter in different scenarios.
   */
  public function testLastmod() {
    // Entity links should have 'lastmod'.
    $this->generator->setBundleSettings('node', 'page')
      ->removeCustomLinks()
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('lastmod');

    // Entity custom links should have 'lastmod'.
    $this->generator->setBundleSettings('node', 'page', ['index' => FALSE])
      ->addCustomLink('/node/' . $this->node->id())
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('lastmod');

    // Non-entity custom links should not have 'lastmod'.
    $this->generator->removeCustomLinks()
      ->addCustomLink('/')
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
//    $this->assertSession()->responseNotContains('lastmod'); // todo: Not passing.
  }

  /**
   * Tests the duplicate setting.
   *
   * @todo On second generation too many links in XML output here?
   */
  public function testRemoveDuplicatesSetting() {
    $this->generator->setBundleSettings('node', 'page', ['index' => TRUE])
      ->addCustomLink('/node/1')
      ->saveSetting('remove_duplicates', TRUE)
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertUniqueTextWorkaround('node/' . $this->node->id());

    $this->generator->saveSetting('remove_duplicates', FALSE)
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertNoUniqueTextWorkaround('node/' . $this->node->id());
  }

  /**
   * Test max links setting and the sitemap index.
   */
  public function testMaxLinksSetting() {
    $this->generator->setBundleSettings('node', 'page')
      ->saveSetting('max_links', 1)
      ->removeCustomLinks()
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('sitemap.xml?page=1');
    $this->assertSession()->responseContains('sitemap.xml?page=2');

    $this->drupalGet('sitemap.xml?page=1');
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseNotContains('node/' . $this->node2->id());

    $this->drupalGet('sitemap.xml?page=2');
    $this->assertSession()->responseContains('node/' . $this->node2->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseNotContains('node/' . $this->node->id());
  }

  /**
   * Test batch process limit setting.
   */
  public function testBatchProcessLimitSetting() {
    // Create some nodes.
    for ($i = 3; $i <= 50; $i++) {
      $this->createNode(['title' => "Node{$i}", 'type' => 'page']);
    }

    // Test batch_process_limit setting.
    $sitemap = $this->generator->setBundleSettings('node', 'page')
      ->generateSitemap('nobatch')
      ->getSitemap();

    $sitemap2 = $this->generator->saveSetting('batch_process_limit', 1)
      ->generateSitemap('nobatch')
      ->getSitemap();

    $sitemap3 = $this->generator->saveSetting('batch_process_limit', 10)
      ->generateSitemap('nobatch')
      ->getSitemap();

    $this->assertEquals($sitemap2, $sitemap);
    $this->assertEquals($sitemap3, $sitemap);

    // Test batch_process_limit setting in combination with max_links setting.
    $sitemap_index = $this->generator->setBundleSettings('node', 'page')
      ->saveSetting('batch_process_limit', 1500)
      ->saveSetting('max_links', 30)
      ->generateSitemap('nobatch')
      ->getSitemap();

    $sitemap_chunk = $this->generator->getSitemap('default', 1);

    $sitemap_index2 = $this->generator->saveSetting('batch_process_limit', 1)
      ->generateSitemap('nobatch')
      ->getSitemap();

    $sitemap_chunk2 = $this->generator->getSitemap('default', 1);

    $sitemap_index3 = $this->generator->saveSetting('batch_process_limit', 10)
      ->generateSitemap('nobatch')
      ->getSitemap();

    $sitemap_chunk3 = $this->generator->getSitemap('default', 1);

    $this->assertSame($sitemap_index2, $sitemap_index);
    $this->assertSame($sitemap_chunk2, $sitemap_chunk);
    $this->assertSame($sitemap_index3, $sitemap_index);
    $this->assertSame($sitemap_chunk3, $sitemap_chunk);
  }

  /**
   * Test setting the base URL.
   */
  public function testBaseUrlSetting() {
    $this->generator->setBundleSettings('node', 'page')
      ->saveSetting('base_url', 'http://base_url_test')
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('http://base_url_test');

    // Set base URL in the sitemap index.
    $this->generator->saveSetting('max_links', 1)
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('http://base_url_test/sitemap.xml?page=1');
  }

  /**
   * @todo testSkipUntranslatedSetting
   */

  /**
   * @todo testSkipNonExistentTranslations
   */

  /**
   * Test overriding of bundle settings for a single entity.
   *
   * @todo: Use form testing instead of responseContains().
   */
  public function testSetEntityInstanceSettings() {
    $this->generator->setBundleSettings('node', 'page')
      ->removeCustomLinks()
      ->setEntityInstanceSettings('node', $this->node->id(), ['priority' => 0.1, 'changefreq' => 'never'])
      ->setEntityInstanceSettings('node', $this->node2->id(), ['index' => FALSE])
      ->generateSitemap('nobatch');

    // Test sitemap result.
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.1');
    $this->assertSession()->responseContains('never');
    $this->assertSession()->responseNotContains('node/' . $this->node2->id());
    $this->assertSession()->responseNotContains('0.5');

    $this->drupalLogin($this->privilegedUser);

    // Test UI changes.
    $this->drupalGet('node/' . $this->node->id() . '/edit');
    $this->assertSession()->responseContains('<option value="0.1" selected="selected">0.1</option>');
    $this->assertSession()->responseContains('<option value="never" selected="selected">never</option>');

    // Test database changes.
    $result = $this->database->select('simple_sitemap_entity_overrides', 'o')
      ->fields('o', ['inclusion_settings'])
      ->condition('o.entity_type', 'node')
      ->condition('o.entity_id', $this->node->id())
      ->execute()
      ->fetchField();
    $this->assertFalse(empty($result));

    $this->generator->setBundleSettings('node', 'page', ['priority' => 0.1, 'changefreq' => 'never'])
      ->generateSitemap('nobatch');

    // Test sitemap result.
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.1');
    $this->assertSession()->responseContains('never');
    $this->assertSession()->responseNotContains('node/' . $this->node2->id());
    $this->assertSession()->responseNotContains('0.5');

    // Test UI changes.
    $this->drupalGet('node/' . $this->node->id() . '/edit');
    $this->assertSession()->responseContains('<option value="0.1" selected="selected">0.1 (default)</option>');
    $this->assertSession()->responseContains('<option value="never" selected="selected">never (default)</option>');

    // Test if entity override has been removed from database after its equal to
    // its bundle settings.
    $result = $this->database->select('simple_sitemap_entity_overrides', 'o')
      ->fields('o', ['inclusion_settings'])
      ->condition('o.entity_type', 'node')
      ->condition('o.entity_id', $this->node->id())
      ->execute()
      ->fetchField();
    $this->assertTrue(empty($result));
  }

  /**
   * Test indexing an atomic entity (here: a user)
   * @todo Not working
   */
  /*public function testAtomicEntityIndexation() {
    $user_id = $this->privilegedUser->id();
    $this->generator->setBundleSettings('user')
      ->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseNotContains('user/' . $user_id);

    user_role_grant_permissions(0, ['access user profiles']);
    $this->generator->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('user/' . $user_id);
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

    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('admin/structure/types/manage/page');
    $this->assertSession()->pageTextNotContains('Simple XML sitemap');

    $this->generator->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseNotContains('node/' . $this->node->id());

    $this->assertFalse($this->generator->entityTypeIsEnabled('node'));
  }

  /**
   * Test enabling sitemap support for an entity type.
   *
   * @todo Test admin/config/search/simplesitemap/entities form.
   */
  public function testEnableEntityType() {
    $this->generator->disableEntityType('node')
      ->enableEntityType('node')
      ->setBundleSettings('node', 'page');

    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('admin/structure/types/manage/page');
    $this->assertSession()->pageTextContains('Simple XML sitemap');

    $this->generator->generateSitemap('nobatch');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());

    $this->assertTrue($this->generator->entityTypeIsEnabled('node'));
  }

  public function testSitemapVariants() {

    // Test adding a default variant.
    $this->generator
      ->addSitemapVariant('test')
      ->setBundleSettings('node', 'page')
      ->generateSitemap('nobatch');

    $variants = $this->generator->getSitemapVariants();
    $this->assertTrue(isset($variants['test']));

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());

    $this->drupalGet('test/sitemap.xml');
    $this->assertSession()->responseContains('node/' . $this->node->id());

    $this->generator->removeSitemapVariants('test');

    $variants = $this->generator->getSitemapVariants();
    $this->assertFalse(isset($variants['test']));

    $this->generator->generateSitemap('nobatch');

    // Test if sitemap has been removed along with the variant.
    $this->drupalGet('test/sitemap.xml');
//    $this->assertSession()->responseNotContains('urlset'); //todo

    // Test adding a variant of new sitemap type.
    $this->generator->removeSitemap()
      ->setSitemapTypeDefinition('test_type', [
      'label' => 'Test type',
      'description' => 'Test description.',
      'sitemap_generator' => 'default',
      'url_generators' => ['custom'],
    ])
      ->addSitemapVariant('test2', ['type' => 'test_type']);

    $types = $this->generator->getSitemapTypeDefinitions();
    $this->assertTrue(isset($types['test_type']));

    $this->generator->generateSitemap('nobatch', 'test2');

    // Test if default variant has been successfully excluded.
    $this->drupalGet($this->defaultSitemapUrl);
//    $this->assertSession()->responseNotContains('urlset'); //todo

    // Test if custom generator has been successfully included.
    $this->drupalGet('test2/sitemap.xml');
    $this->assertSession()->responseContains(Url::fromRoute('<front>')->setAbsolute()->toString());

    // Test if entity generator has been successfully excluded.
    $this->assertSession()->responseNotContains('node/' . $this->node->id());

    // Test removing sitemap type.
    $this->generator->removeSitemapTypeDefinition('test_type');
    $types = $this->generator->getSitemapTypeDefinitions();
    $this->assertFalse(isset($types['test_type']));

    // Test if variants have been removed along with the sitemap type.
    $variants = $this->generator->getSitemapVariants();
    $this->assertFalse(isset($variants['test2']));

    $this->generator->generateSitemap('nobatch');

    // Test if sitemap has been removed along with the sitemap type.
//    $this->assertFalse($this->generator->getSitemap('test2')); //todo
  }

  /**
   * Test cacheability of the response.
   */
  public function testCacheability() {
    $this->generator
      ->addSitemapVariant('test')
      ->setBundleSettings('node', 'page')
      ->generateSitemap('nobatch');

    // Verify the cache is not set.
    $this->drupalGet('sitemap.xml');
    $this->assertEquals('MISS', $this->drupalGetHeader('X-Drupal-Cache'));
    $this->assertSession()->responseContains('node/' . $this->node->id());

    $this->drupalGet('test/sitemap.xml');
    $this->assertEquals('MISS', $this->drupalGetHeader('X-Drupal-Cache'));
    $this->assertSession()->responseContains('node/' . $this->node->id());

    // Verify the sitemap is taken from cache on second call
    $this->drupalGet('sitemap.xml');
    $this->assertEquals('HIT', $this->drupalGetHeader('X-Drupal-Cache'));

    $this->drupalGet('test/sitemap.xml');
    $this->assertEquals('HIT', $this->drupalGetHeader('X-Drupal-Cache'));

    $this->generator->generateSitemap('nobatch', 'default');

    // Verify the cache is not set for default variant as it as just regenerated.
    $this->drupalGet('sitemap.xml');
    $this->assertEquals('MISS', $this->drupalGetHeader('X-Drupal-Cache'));

    // Verify the cache is still set for test variant.
    $this->drupalGet('test/sitemap.xml');
//    $this->assertEquals('HIT', $this->drupalGetHeader('X-Drupal-Cache')); //todo

    $this->generator->removeSitemap('test');

    // Verify the cache is unset for test variant after removing it explicitly.
    $this->drupalGet('test/sitemap.xml');
//    $this->assertEquals('MISS', $this->drupalGetHeader('X-Drupal-Cache')); //todo
  }

  /**
   * @todo Test removeSitemap().
   */
}
