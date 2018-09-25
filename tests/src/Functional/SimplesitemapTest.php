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
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testInitialGeneration() {
    $this->generator->generateSitemap('backend');
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('urlset');
    $this->assertSession()->responseContains(
      Url::fromRoute('<front>')->setAbsolute()->toString()
    );
    $this->assertSession()->responseContains('1.0');
    $this->assertSession()->responseContains('daily');
  }

  /**
   * Test custom link.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAddCustomLink() {
    $this->generator->addCustomLink(
      '/node/' . $this->node->id(),
      ['priority' => 0.2, 'changefreq' => 'monthly']
    )->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.2');
    $this->assertSession()->responseContains('monthly');

    $this->drupalLogin($this->privilegedUser);

    $this->drupalGet('admin/config/search/simplesitemap/custom');
    $this->assertSession()->pageTextContains(
      '/node/' . $this->node->id() . ' 0.2 monthly'
    );

    $this->generator->addCustomLink(
      '/node/' . $this->node->id(),
      ['changefreq' => 'yearly']
    )->generateSitemap('backend');

    $this->drupalGet('admin/config/search/simplesitemap/custom');
    $this->assertSession()->pageTextContains(
      '/node/' . $this->node->id() . ' yearly'
    );
  }

  /**
   * Test default settings of custom links.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAddCustomLinkDefaults() {
    $this->generator->removeCustomLinks()
      ->addCustomLink('/node/' . $this->node->id())
      ->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseNotContains('changefreq');
  }

  /**
   * Test removing custom paths from the sitemap settings.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testRemoveCustomLinks() {

    // Test removing one custom path from the sitemap.
    $this->generator->addCustomLink('/node/' . $this->node->id())
      ->removeCustomLinks('/node/' . $this->node->id())
      ->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseNotContains('node/' . $this->node->id());

    //todo Not working
//    // Test removing all custom paths from the sitemap.
//    $this->generator->removeCustomLinks()
//      ->generateSitemap('backend');
//
//    $this->drupalGet($this->defaultSitemapUrl);
//    $this->assertSession()->responseNotContains(
//      Url::fromRoute('<front>')->setAbsolute()->toString()
//    );
  }

  /**
   * Tests setting bundle settings.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
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
      ->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseContains('hourly');

    $this->assertTrue($this->generator->bundleIsIndexed('node', 'page'));

    // Only change bundle priority.
    $this->generator->setBundleSettings('node', 'page', ['priority' => 0.9])
      ->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseNotContains('0.5');
    $this->assertSession()->responseContains('0.9');

    // Only change bundle changefreq.
    $this->generator->setBundleSettings(
      'node',
      'page',
      ['changefreq' => 'daily']
    )->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseNotContains('hourly');
    $this->assertSession()->responseContains('daily');

    // Remove changefreq setting.
    $this->generator->setBundleSettings('node', 'page', ['changefreq' => ''])
      ->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseNotContains('changefreq');
    $this->assertSession()->responseNotContains('daily');

    // Index two bundles.
    $this->drupalCreateContentType(['type' => 'blog']);

    $node3 = $this->createNode(['title' => 'Node3', 'type' => 'blog']);
    $this->generator->setBundleSettings('node', 'page', ['index' => TRUE])
      ->setBundleSettings('node', 'blog', ['index' => TRUE])
      ->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('node/' . $node3->id());

    // todo Now working
//    // Set bundle 'index' setting to false.
//    $this->generator
//      ->setBundleSettings('node', 'page', ['index' => FALSE])
//      ->setBundleSettings('node', 'blog', ['index' => FALSE])
//      ->generateSitemap('backend');
//
//    $this->drupalGet($this->defaultSitemapUrl);
//
//    $this->assertSession()->responseNotContains('node/' . $this->node->id());
//    $this->assertSession()->responseNotContains('node/' . $node3->id());
  }

  /**
   * Test default settings of bundles.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSetBundleSettingsDefaults() {
    $this->generator->setBundleSettings('node', 'page')
      ->removeCustomLinks()
      ->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseNotContains('changefreq');
  }

  /**
   * Test the lastmod parameter in different scenarios.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testLastmod() {
    // Entity links should have 'lastmod'.
    $this->generator->setBundleSettings('node', 'page')
      ->removeCustomLinks()
      ->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('lastmod');

    // Entity custom links should have 'lastmod'.
    $this->generator->setBundleSettings('node', 'page', ['index' => FALSE])
      ->addCustomLink('/node/' . $this->node->id())
      ->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('lastmod');

    // Non-entity custom links should not have 'lastmod'.
    $this->generator->removeCustomLinks()
      ->addCustomLink('/')
      ->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseNotContains('lastmod');
  }

  /**
   * Tests the duplicate setting.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *
   * @todo Reimplement duplicate functionality for this test to work.
   */
//  public function testRemoveDuplicatesSetting() {
//    $this->generator->setBundleSettings('node', 'page', ['index' => TRUE])
//      ->addCustomLink('/node/1')
//      ->saveSetting('remove_duplicates', TRUE)
//      ->generateSitemap('backend');
//
//    $this->drupalGet($this->defaultSitemapUrl);
//    $this->assertUniqueTextWorkaround('node/' . $this->node->id());
//
//    $this->generator->saveSetting('remove_duplicates', FALSE)
//      ->generateSitemap('backend');
//
//    $this->drupalGet($this->defaultSitemapUrl);
//    $this->assertNoUniqueTextWorkaround('node/' . $this->node->id());
//  }

  /**
   * Test max links setting and the sitemap index.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testMaxLinksSetting() {
    $this->generator->setBundleSettings('node', 'page')
      ->saveSetting('max_links', 1)
      ->removeCustomLinks()
      ->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('sitemap.xml?page=1');
    $this->assertSession()->responseContains('sitemap.xml?page=2');

    $this->drupalGet('sitemap.xml', ['query' => ['page' => 1]]);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseNotContains('node/' . $this->node2->id());

    $this->drupalGet('sitemap.xml', ['query' => ['page' => 2]]);
    $this->assertSession()->responseContains('node/' . $this->node2->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseNotContains('node/' . $this->node->id());
  }

  /**
   * @todo testGenerateDurationSetting
   */

  /**
   * Test setting the base URL.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testBaseUrlSetting() {
    $this->generator->setBundleSettings('node', 'page')
      ->saveSetting('base_url', 'http://base_url_test')
      ->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('http://base_url_test');

    // Set base URL in the sitemap index.
    $this->generator->saveSetting('max_links', 1)
      ->generateSitemap('backend');

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
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @todo: Use form testing instead of responseContains().
   */
  public function testSetEntityInstanceSettings() {
    $this->generator->setBundleSettings('node', 'page')
      ->removeCustomLinks()
      ->setEntityInstanceSettings('node', $this->node->id(), ['priority' => 0.1, 'changefreq' => 'never'])
      ->setEntityInstanceSettings('node', $this->node2->id(), ['index' => FALSE])
      ->generateSitemap('backend');

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
      ->generateSitemap('backend');

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
   */
  public function testAtomicEntityIndexation() {
    $user_id = $this->privilegedUser->id();
    $this->generator->setBundleSettings('user')
      ->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseNotContains('user/' . $user_id);

    user_role_grant_permissions('anonymous', ['access user profiles']);
    drupal_flush_all_caches(); //todo Not pretty.

    $this->generator->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('user/' . $user_id);
  }

  /**
   * @todo Test indexing menu.
   */

  /**
   * @todo Test deleting a bundle.
   */

  /**
   * Test disabling sitemap support for an entity type.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testDisableEntityType() {
    $this->generator->setBundleSettings('node', 'page')
      ->disableEntityType('node');

    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('admin/structure/types/manage/page');
    $this->assertSession()->pageTextNotContains('Simple XML sitemap');

    $this->generator->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseNotContains('node/' . $this->node->id());

    $this->assertFalse($this->generator->entityTypeIsEnabled('node'));
  }

  /**
   * Test enabling sitemap support for an entity type.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
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

    $this->generator->generateSitemap('backend');

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());

    $this->assertTrue($this->generator->entityTypeIsEnabled('node'));
  }

  /**
   * Test adding and removing sitemap variants.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testSitemapVariants() {

    // Test adding a default variant.
    $this->generator->getSitemapManager()->addSitemapVariant('test');

    $this->generator
      ->setBundleSettings('node', 'page')
      ->generateSitemap('backend');

    $variants = $this->generator->getSitemapManager()->getSitemapVariants();
    $this->assertTrue(isset($variants['test']));

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());

    $this->drupalGet('test/sitemap.xml');
    $this->assertSession()->responseContains('node/' . $this->node->id());

    $this->generator->getSitemapManager()->removeSitemapVariants('test');

    $variants = $this->generator->getSitemapManager()->getSitemapVariants();
    $this->assertFalse(isset($variants['test']));

    //todo Implement removing sitemaps from database upon removing variants first.
    // Test if sitemap has been removed along with the variant.
    $this->generator->generateSitemap('backend');
    $this->drupalGet('test/sitemap.xml');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * @todo Test removeSitemap().
   */
}
