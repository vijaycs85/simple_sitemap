<?php

namespace Drupal\simple_sitemap;

use XMLWriter;
use Drupal\simple_sitemap\Batch\Batch;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Class SitemapGenerator.
 *
 * @package Drupal\simple_sitemap
 */
class SitemapGenerator {

  const XML_VERSION = '1.0';
  const ENCODING = 'UTF-8';
  const XMLNS = 'http://www.sitemaps.org/schemas/sitemap/0.9';
  const XMLNS_XHTML = 'http://www.w3.org/1999/xhtml';
  const GENERATED_BY = 'Generated by the Simple XML sitemap Drupal module: https://drupal.org/project/simple_sitemap.';

  private $batch;
  private $db;
  private $moduleHandler;
  private $defaultLanguageId;
  private $generateFrom = 'form';
  private $isHreflangSitemap;
  private $generator;

  /**
   * SitemapGenerator constructor.
   * @param \Drupal\simple_sitemap\Batch\Batch $batch
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   */
  public function __construct(
    Batch $batch,
    Connection $database,
    ModuleHandler $module_handler,
    LanguageManagerInterface $language_manager
  ) {
    $this->batch = $batch;
    $this->db = $database;
    $this->moduleHandler = $module_handler;
    $this->defaultLanguageId = $language_manager->getDefaultLanguage()->getId();
    $this->isHreflangSitemap = count($language_manager->getLanguages()) > 1;
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
    $this->batch->addOperation('generateCustomUrls', $this->getCustomUrlsData());

    // Add entity link generating operations.
    foreach ($this->getEntityTypeData() as $data) {
      $this->batch->addOperation('generateBundleUrls', $data);
    }
    $this->batch->start();
  }

  /**
   * Returns a batch-ready data array for custom link generation.
   *
   * @return array
   *   Data to be processed.
   */
  private function getCustomUrlsData() {
    $paths = [];
    foreach ($this->generator->getCustomLinks() as $i => $custom_path) {
      $paths[$i]['path'] = $custom_path['path'];
      $paths[$i]['priority'] = isset($custom_path['priority']) ? $custom_path['priority'] : NULL;
      // todo: implement lastmod.
      $paths[$i]['lastmod'] = NULL;
    }
    return $paths;
  }

  /**
   * Collects entity metadata for entities that are set to be indexed
   * and returns an array of batch-ready data sets for entity link generation.
   *
   * @return array
   */
  private function getEntityTypeData() {
    $data_sets = [];
    $sitemap_entity_types = $this->generator->getSitemapEntityTypes();
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
   * Wrapper method which takes links along with their options, lets other
   * modules alter the links and then generates and saves the sitemap.
   *
   * @param array $links
   *   All links with their multilingual versions and settings.
   * @param bool $remove_sitemap
   *   Remove old sitemap from database before inserting the new one.
   */
  public function generateSitemap(array $links, $remove_sitemap = FALSE) {
    // Invoke alter hook.
    $this->moduleHandler->alter('simple_sitemap_links', $links);

    $values = [
      'id' => $remove_sitemap ? 1 : $this->db->query('SELECT MAX(id) FROM {simple_sitemap}')->fetchField() + 1,
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
   * @param array $chunks
   *   All sitemap chunks keyed by the chunk ID.
   *
   * @return string sitemap index
   */
  public function generateSitemapIndex(array $chunks) {
    $writer = new XMLWriter();
    $writer->openMemory();
    $writer->setIndent(TRUE);
    $writer->startDocument(self::XML_VERSION, self::ENCODING);
    $writer->writeComment(self::GENERATED_BY);
    $writer->startElement('sitemapindex');
    $writer->writeAttribute('xmlns', self::XMLNS);

    foreach ($chunks as $chunk_id => $chunk_data) {
      $writer->startElement('sitemap');
      $writer->writeElement('loc', $this->getCustomBaseUrl() . '/sitemaps/' . $chunk_id . '/' . 'sitemap.xml');
      $writer->writeElement('lastmod', date_iso8601($chunk_data->sitemap_created));
      $writer->endElement();
    }
    $writer->endElement();
    $writer->endDocument();
    return $writer->outputMemory();
  }

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
  private function generateSitemapChunk(array $links) {
    $writer = new XMLWriter();
    $writer->openMemory();
    $writer->setIndent(TRUE);
    $writer->startDocument(self::XML_VERSION, self::ENCODING);
    $writer->writeComment(self::GENERATED_BY);
    $writer->startElement('urlset');
    $writer->writeAttribute('xmlns', self::XMLNS);
    if ($this->isHreflangSitemap) {
      $writer->writeAttribute('xmlns:xhtml', self::XMLNS_XHTML);
    }

    foreach ($links as $link) {

      // Add each translation variant URL as location to the sitemap.
      $writer->startElement('url');
      $writer->writeElement('loc', $link['url']);

      // If more than one language is enabled, add all translation variant URLs
      // as alternate links to this location turning the sitemap into a hreflang
      // sitemap.
      if ($this->isHreflangSitemap) {
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

      //todo: Implement changefreq here.

      // Add priority if any.
      if (isset($link['priority'])) {
        $writer->writeElement('priority', $link['priority']);
      }

      $writer->endElement();
    }
    $writer->endElement();
    $writer->endDocument();
    return $writer->outputMemory();
  }

}
