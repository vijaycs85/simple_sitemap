<?php

namespace Drupal\simple_sitemap;

use XMLWriter;
use Drupal\simple_sitemap\Batch\Batch;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Class SitemapGenerator
 * @package Drupal\simple_sitemap
 */
class SitemapGenerator {

  const XML_VERSION = '1.0';
  const ENCODING = 'UTF-8';
  const XMLNS = 'http://www.sitemaps.org/schemas/sitemap/0.9';
  const XMLNS_XHTML = 'http://www.w3.org/1999/xhtml';
  const GENERATED_BY = 'Generated by the Simple XML sitemap Drupal module: https://drupal.org/project/simple_sitemap.';
  const FIRST_CHUNK_INDEX = 1;
  const XMLNS_IMAGE = 'http://www.google.com/schemas/sitemap-image/1.1';

  /**
   * @var \Drupal\simple_sitemap\Batch\Batch
   */
  protected $batch;

  /**
   * @var \Drupal\simple_sitemap\EntityHelper
   */
  protected $entityHelper;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;


  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * @var string
   */
  protected $generateFrom = 'form';

  /**
   * @var bool
   */
  protected $isHreflangSitemap;

  /**
   * @var \Drupal\simple_sitemap\Simplesitemap
   */
  protected $generator;

  protected static $attributes = [
    'xmlns' => self::XMLNS,
    'xmlns:xhtml' => self::XMLNS_XHTML,
    'xmlns:image' => self::XMLNS_IMAGE,
  ];

  protected static $indexAttributes = [
    'xmlns' => self::XMLNS,
  ];

  /**
   * SitemapGenerator constructor.
   * @param \Drupal\simple_sitemap\Batch\Batch $batch
   * @param \Drupal\simple_sitemap\EntityHelper $entityHelper
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   */
  public function __construct(
    Batch $batch,
    EntityHelper $entityHelper,
    Connection $database,
    ModuleHandler $module_handler,
    LanguageManagerInterface $language_manager
  ) {
    $this->batch = $batch;
    $this->entityHelper = $entityHelper;
    $this->db = $database;
    $this->moduleHandler = $module_handler;
    $this->languageManager = $language_manager;
    $this->setIsHreflangSitemap();
  }

  protected function setIsHreflangSitemap() {
    $this->isHreflangSitemap = count($this->languageManager->getLanguages()) > 1;
  }

  /**
   * @return bool
   */
  public function isHreflangSitemap() {
    return $this->isHreflangSitemap;
  }

  /**
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   * @return $this
   */
  public function setGenerator(Simplesitemap $generator) {
    $this->generator = $generator;
    return $this;
  }

  /**
   * @param string $from
   * @return $this
   */
  public function setGenerateFrom($from) {
    $this->generateFrom = $from;
    return $this;
  }

  /**
   * Adds all operations to the batch and starts it.
   */
  public function startGeneration() {
    $this->batch->setBatchInfo([
      'from' => $this->generateFrom,
      'batch_process_limit' => !empty($this->generator->getSetting('batch_process_limit'))
        ? $this->generator->getSetting('batch_process_limit') : NULL,
      'max_links' => $this->generator->getSetting('max_links', 2000),
      'skip_untranslated' => $this->generator->getSetting('skip_untranslated', FALSE),
      'remove_duplicates' => $this->generator->getSetting('remove_duplicates', TRUE),
      'entity_types' => $this->generator->getBundleSettings(),
      'base_url' => $this->generator->getSetting('base_url', ''),
    ]);

    // Add custom link generating operation.
    $this->batch->addOperation('simple_sitemap.custom_url_generator', $this->getCustomUrlsData());

    // Add entity link generating operations.
    foreach ($this->getEntityTypeData() as $data) {
      $this->batch->addOperation('simple_sitemap.entity_url_generator', $data);
    }

    // Add arbitrary links generating operation.
    $arbitrary_links = [];
    $this->moduleHandler->alter('simple_sitemap_arbitrary_links', $arbitrary_links);
    if (!empty($arbitrary_links)) {
      $this->batch->addOperation('simple_sitemap.arbitrary_url_generator', $arbitrary_links);
    }

    $this->batch->start();
  }

  /**
   * Returns a batch-ready data array for custom link generation.
   *
   * @return array
   *   Data to be processed.
   */
  protected function getCustomUrlsData() {
    $paths = [];
    foreach ($this->generator->getCustomLinks() as $i => $custom_path) {
      $paths[$i]['path'] = $custom_path['path'];
      $paths[$i]['priority'] = isset($custom_path['priority']) ? $custom_path['priority'] : NULL;
      $paths[$i]['changefreq'] = isset($custom_path['changefreq']) ? $custom_path['changefreq'] : NULL;
    }
    return $paths;
  }

  /**
   * Collects entity metadata for entities that are set to be indexed
   * and returns an array of batch-ready data sets for entity link generation.
   *
   * @return array
   */
  protected function getEntityTypeData() {
    $data_sets = [];
    $sitemap_entity_types = $this->entityHelper->getSupportedEntityTypes();
    $entity_types = $this->generator->getBundleSettings();
    foreach ($entity_types as $entity_type_name => $bundles) {
      if (isset($sitemap_entity_types[$entity_type_name])) {
        $keys = $sitemap_entity_types[$entity_type_name]->getKeys();

        // Menu fix.
        $keys['bundle'] = $entity_type_name == 'menu_link_content' ? 'menu_name' : $keys['bundle'];

        foreach ($bundles as $bundle_name => $bundle_settings) {
          if ($bundle_settings['index']) {
            $data_sets[] = [
              'bundle_settings' => $bundle_settings,
              'bundle_name' => $bundle_name,
              'entity_type_name' => $entity_type_name,
              'keys' => $keys,
            ];
          }
        }
      }
    }
    return $data_sets;
  }

  /**
   * Wrapper method which takes links along with their options and then
   * generates and saves the sitemap.
   *
   * @param array $links
   *   All links with their multilingual versions and settings.
   * @param bool $remove_sitemap
   *   Remove old sitemap from database before inserting the new one.
   */
  public function generateSitemap(array $links, $remove_sitemap = FALSE) {
    $values = [
      'id' => $remove_sitemap ? self::FIRST_CHUNK_INDEX
        : $this->db->query('SELECT MAX(id) FROM {simple_sitemap}')
          ->fetchField() + 1,
      'sitemap_string' => $this->generateSitemapChunk($links),
      'sitemap_created' => REQUEST_TIME,
    ];
    if ($remove_sitemap) {
      $this->db->truncate('simple_sitemap')->execute();
    }
    $this->db->insert('simple_sitemap')->fields($values)->execute();
  }

  /**
   * Generates and returns the sitemap index for all sitemap chunks.
   *
   * @param array $chunk_info
   *   Array containing chunk creation timestamps keyed by chunk ID.
   *
   * @return string sitemap index
   */
  public function generateSitemapIndex(array $chunk_info) {
    $writer = new XMLWriter();
    $writer->openMemory();
    $writer->setIndent(TRUE);
    $writer->startDocument(self::XML_VERSION, self::ENCODING);
    $writer->writeComment(self::GENERATED_BY);
    $writer->startElement('sitemapindex');

    // Add attributes to document.
    $this->moduleHandler->alter('simple_sitemap_index_attributes', self::$indexAttributes);
    foreach (self::$indexAttributes as $name => $value) {
      $writer->writeAttribute($name, $value);
    }

    // Add sitemap locations to document.
    foreach ($chunk_info as $chunk_id => $chunk_data) {
      $writer->startElement('sitemap');
      $writer->writeElement('loc', $this->getCustomBaseUrl() . '/sitemaps/' . $chunk_id . '/' . 'sitemap.xml');
      $writer->writeElement('lastmod', date_iso8601($chunk_data->sitemap_created));
      $writer->endElement();
    }

    $writer->endElement();
    $writer->endDocument();
    return $writer->outputMemory();
  }

  /**
   * @return string
   */
  public function getCustomBaseUrl() {
    $customBaseUrl = $this->generator->getSetting('base_url', '');
    return !empty($customBaseUrl) ? $customBaseUrl : $GLOBALS['base_url'];
  }

  /**
   * Generates and returns a sitemap chunk.
   *
   * @param array $links
   *   All links with their multilingual versions and settings.
   *
   * @return string
   *   Sitemap chunk
   */
  protected function generateSitemapChunk(array $links) {
    $writer = new XMLWriter();
    $writer->openMemory();
    $writer->setIndent(TRUE);
    $writer->startDocument(self::XML_VERSION, self::ENCODING);
    $writer->writeComment(self::GENERATED_BY);
    $writer->startElement('urlset');

    // Add attributes to document.
    if (!$this->isHreflangSitemap()) {
      unset(self::$attributes['xmlns:xhtml']);
    }
    $this->moduleHandler->alter('simple_sitemap_attributes', self::$attributes);
    foreach (self::$attributes as $name => $value) {
      $writer->writeAttribute($name, $value);
    }

    // Add URLs to document.
    $this->moduleHandler->alter('simple_sitemap_links', $links);
    foreach ($links as $link) {

      // Add each translation variant URL as location to the sitemap.
      $writer->startElement('url');
      $writer->writeElement('loc', $link['url']);

      // If more than one language is enabled, add all translation variant URLs
      // as alternate links to this location turning the sitemap into a hreflang
      // sitemap.
      if (isset($link['alternate_urls']) && $this->isHreflangSitemap()) {
        foreach ($link['alternate_urls'] as $language_id => $alternate_url) {
          $writer->startElement('xhtml:link');
          $writer->writeAttribute('rel', 'alternate');
          $writer->writeAttribute('hreflang', $language_id);
          $writer->writeAttribute('href', $alternate_url);
          $writer->endElement();
        }
      }

      // Add lastmod if any.
      if (isset($link['lastmod'])) {
        $writer->writeElement('lastmod', $link['lastmod']);
      }

      // Add changefreq if any.
      if (isset($link['changefreq'])) {
        $writer->writeElement('changefreq', $link['changefreq']);
      }

      // Add priority if any.
      if (isset($link['priority'])) {
        $writer->writeElement('priority', $link['priority']);
      }

      // Add images if any.
      if (!empty($link['images'])) {
        foreach ($link['images'] as $image_url) {
          $writer->startElement('image:image');
          $writer->writeElement('image:loc', $image_url);
          $writer->endElement();
        }
      }

      $writer->endElement();
    }
    $writer->endElement();
    $writer->endDocument();
    return $writer->outputMemory();
  }

}
