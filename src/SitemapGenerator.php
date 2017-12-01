<?php

namespace Drupal\simple_sitemap;

use XMLWriter;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Component\Datetime\Time;

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
   * @var bool
   */
  protected $isHreflangSitemap;

  /**
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * @var array
   */
  protected $settings;

  /**
   * @var \XMLWriter
   */
  protected $writer;

  /**
   * @var array
   */
  protected static $attributes = [
    'xmlns' => self::XMLNS,
    'xmlns:xhtml' => self::XMLNS_XHTML,
    'xmlns:image' => self::XMLNS_IMAGE,
  ];

  /**
   * @var array
   */
  protected static $indexAttributes = [
    'xmlns' => self::XMLNS,
  ];

  /**
   * SitemapGenerator constructor.
   * @param \Drupal\simple_sitemap\EntityHelper $entityHelper
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Component\Datetime\Time $time
   * @param \Drupal\simple_sitemap\SitemapWriter $sitemapWriter
   */
  public function __construct(
    EntityHelper $entityHelper,
    Connection $database,
    ModuleHandler $module_handler,
    LanguageManagerInterface $language_manager,
    Time $time,
    SitemapWriter $sitemapWriter
  ) {
    $this->entityHelper = $entityHelper;
    $this->db = $database;
    $this->moduleHandler = $module_handler;
    $this->languageManager = $language_manager;
    $this->time = $time;
    $this->writer = $sitemapWriter;
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
   * @param array $settings
   * @return $this
   */
  public function setSettings(array $settings) {
    $this->settings = $settings;
    return $this;
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
      'sitemap_created' => $this->time->getRequestTime(),
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
    $this->writer->openMemory();
    $this->writer->setIndent(TRUE);
    $this->writer->startDocument(self::XML_VERSION, self::ENCODING);
    $this->writer->writeComment(self::GENERATED_BY);
    $this->writer->startElement('sitemapindex');

    // Add attributes to document.
    $this->moduleHandler->alter('simple_sitemap_index_attributes', self::$indexAttributes);
    foreach (self::$indexAttributes as $name => $value) {
      $this->writer->writeAttribute($name, $value);
    }

    // Add sitemap locations to document.
    foreach ($chunk_info as $chunk_id => $chunk_data) {
      $this->writer->startElement('sitemap');
      $this->writer->writeElement('loc', $this->getCustomBaseUrl() . '/sitemaps/' . $chunk_id . '/' . 'sitemap.xml');
      $this->writer->writeElement('lastmod', date_iso8601($chunk_data->sitemap_created));
      $this->writer->endElement();
    }

    $this->writer->endElement();
    $this->writer->endDocument();
    return $this->writer->outputMemory();
  }

  /**
   * @return string
   */
  public function getCustomBaseUrl() {
    $customBaseUrl = $this->settings['base_url'];
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
    $this->writer->openMemory();
    $this->writer->setIndent(TRUE);
    $this->writer->startDocument(self::XML_VERSION, self::ENCODING);
    $this->writer->writeComment(self::GENERATED_BY);
    $this->writer->startElement('urlset');

    // Add attributes to document.
    if (!$this->isHreflangSitemap()) {
      unset(self::$attributes['xmlns:xhtml']);
    }
    $this->moduleHandler->alter('simple_sitemap_attributes', self::$attributes);
    foreach (self::$attributes as $name => $value) {
      $this->writer->writeAttribute($name, $value);
    }

    // Add URLs to document.
    $this->moduleHandler->alter('simple_sitemap_links', $links);
    foreach ($links as $link) {

      // Add each translation variant URL as location to the sitemap.
      $this->writer->startElement('url');
      $this->writer->writeElement('loc', $link['url']);

      // If more than one language is enabled, add all translation variant URLs
      // as alternate links to this location turning the sitemap into a hreflang
      // sitemap.
      if (isset($link['alternate_urls']) && $this->isHreflangSitemap()) {
        foreach ($link['alternate_urls'] as $language_id => $alternate_url) {
          $this->writer->startElement('xhtml:link');
          $this->writer->writeAttribute('rel', 'alternate');
          $this->writer->writeAttribute('hreflang', $language_id);
          $this->writer->writeAttribute('href', $alternate_url);
          $this->writer->endElement();
        }
      }

      // Add lastmod if any.
      if (isset($link['lastmod'])) {
        $this->writer->writeElement('lastmod', $link['lastmod']);
      }

      // Add changefreq if any.
      if (isset($link['changefreq'])) {
        $this->writer->writeElement('changefreq', $link['changefreq']);
      }

      // Add priority if any.
      if (isset($link['priority'])) {
        $this->writer->writeElement('priority', $link['priority']);
      }

      // Add images if any.
      if (!empty($link['images'])) {
        foreach ($link['images'] as $image) {
          $this->writer->startElement('image:image');
          $this->writer->writeElement('image:loc', $image['path']);
          $this->writer->endElement();
        }
      }

      $this->writer->endElement();
    }
    $this->writer->endElement();
    $this->writer->endDocument();
    return $this->writer->outputMemory();
  }

}
