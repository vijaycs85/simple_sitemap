<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

use Drupal\simple_sitemap\Plugin\simple_sitemap\SimplesitemapPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Component\Datetime\Time;

/**
 * Class SitemapGeneratorBase
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 */
abstract class SitemapGeneratorBase extends SimplesitemapPluginBase implements SitemapGeneratorInterface {

  const FIRST_CHUNK_DELTA = 1;
  const INDEX_DELTA = 0;

  const GENERATED_BY = 'Generated by the Simple XML sitemap Drupal module: https://drupal.org/project/simple_sitemap.';
  const XML_VERSION = '1.0';
  const ENCODING = 'UTF-8';
  const XMLNS = 'http://www.sitemaps.org/schemas/sitemap/0.9';

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
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * @var array
   */
  protected $settings;

  /**
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapWriter
   */
  protected $writer;

  /**
   * @var string
   */
  protected $sitemapVariant;

  /**
   * @var array
   */
  protected static $indexAttributes = [
    'xmlns' => self::XMLNS,
  ];

  /**
   * SitemapGeneratorBase constructor.
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Component\Datetime\Time $time
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapWriter $sitemap_writer
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Connection $database,
    ModuleHandler $module_handler,
    LanguageManagerInterface $language_manager,
    Time $time,
    SitemapWriter $sitemap_writer
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->db = $database;
    $this->moduleHandler = $module_handler;
    $this->languageManager = $language_manager;
    $this->time = $time;
    $this->writer = $sitemap_writer;
    $this->sitemapVariant = $this->settings['default_variant'];
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('module_handler'),
      $container->get('language_manager'),
      $container->get('datetime.time'),
      $container->get('simple_sitemap.sitemap_writer')
    );
  }

  /**
   * @param $sitemap_variant
   * @return $this
   */
  public function setSitemapVariant($sitemap_variant) {
    $this->sitemapVariant = $sitemap_variant;
    return $this;
  }

  /**
   * @return bool
   */
  protected function isDefaultVariant() {
    return $this->sitemapVariant === $this->settings['default_variant'];
  }

  /**
   * @param array $links
   * @return string
   */
  abstract protected function getXml(array $links);

  protected function getChunkInfo() {
    return $this->db->select('simple_sitemap', 's')
      ->fields('s', ['delta', 'sitemap_created', 'type'])
      ->condition('s.type', $this->sitemapVariant)
      ->condition('s.delta', self::INDEX_DELTA, '<>')
      ->condition('s.status', 0)
      ->execute()
      ->fetchAllAssoc('delta');
  }

  /**
   * Returns the sitemap index for all sitemap chunks of this type.
   *
   * @return string
   */
  protected function getIndexXml(array $chunk_info) {
    $this->writer->openMemory();
    $this->writer->setIndent(TRUE);
    $this->writer->startDocument(self::XML_VERSION, self::ENCODING);
    $this->writer->writeComment(self::GENERATED_BY);
    $this->writer->startElement('sitemapindex');

    // Add attributes to document.
    $attributes = self::$indexAttributes;
    $sitemap_variant = $this->sitemapVariant;
    $this->moduleHandler->alter('simple_sitemap_index_attributes', $attributes, $sitemap_variant);
    foreach ($attributes as $name => $value) {
      $this->writer->writeAttribute($name, $value);
    }

    // Add sitemap chunk locations to document.
    foreach ($chunk_info as $chunk_data) {
      $this->writer->startElement('sitemap');
      $this->writer->writeElement('loc', $this->getCustomBaseUrl()
        . '/' . (!$this->isDefaultVariant() ? ($chunk_data->type . '/') : '') . 'sitemap.xml?page=' . $chunk_data->delta);
      $this->writer->writeElement('lastmod', date_iso8601($chunk_data->sitemap_created));
      $this->writer->endElement();
    }

    $this->writer->endElement();
    $this->writer->endDocument();

    return $this->writer->outputMemory();
  }

  /**
   * @param string $mode
   * @return $this
   */
  public function remove($mode = 'all') {
    self::purgeSitemapVariants($this->sitemapVariant, $mode);

    return $this;
  }

  public static function purgeSitemapVariants($variants = NULL, $mode = 'all') {
    if (NULL === $variants || !empty((array) $variants)) {
      $delete_query = \Drupal::database()->delete('simple_sitemap');

      switch($mode) {
        case 'published':
          $delete_query->condition('status', 1);
          break;

        case 'unpublished':
          $delete_query->condition('status', 0);
          break;

        case 'all':
          break;

        default:
          //todo: throw error
      }

      if (NULL !== $variants) {
        $delete_query->condition('type', (array) $variants, 'IN');
      }

      $delete_query->execute();
    }
  }

  /**
   * Takes links along with their options and then generates and saves the
   * sitemap.
   *
   * @param array $links
   *   All links with their multilingual versions and settings.
   *
   */
  public function generate(array $links) {
    $highest_id = $this->db->query('SELECT MAX(id) FROM {simple_sitemap}')->fetchField();
    $highest_delta = $this->db->query('SELECT MAX(delta) FROM {simple_sitemap} WHERE type = :type AND status = :status', [':type' => $this->sitemapVariant, ':status' => 0])
      ->fetchField();

    $this->db->insert('simple_sitemap')->fields([
      'id' => NULL === $highest_id ? 0 : $highest_id + 1,
      'delta' => NULL === $highest_delta ? self::FIRST_CHUNK_DELTA : $highest_delta + 1,
      'type' =>  $this->sitemapVariant,
      'sitemap_string' => $this->getXml($links),
      'sitemap_created' => $this->time->getRequestTime(),
      'status' => 0,
    ])->execute();

    return $this;
  }

  /**
   * @throws \Exception
   */
  public function generateIndex() {
    if (!empty($chunk_info = $this->getChunkInfo()) && count($chunk_info) > 1) {
      $index_xml = $this->getIndexXml($chunk_info);
      $highest_id = $this->db->query('SELECT MAX(id) FROM {simple_sitemap}')->fetchField();
      $this->db->merge('simple_sitemap')
        ->keys([
          'delta' => self::INDEX_DELTA,
          'type' => $this->sitemapVariant,
          'status' => 0
        ])
        ->insertFields([
          'id' => NULL === $highest_id ? 0 : $highest_id + 1,
          'delta' => self::INDEX_DELTA,
          'type' =>  $this->sitemapVariant,
          'sitemap_string' => $index_xml,
          'sitemap_created' => $this->time->getRequestTime(),
          'status' => 0,
        ])
        ->updateFields([
          'sitemap_string' => $index_xml,
          'sitemap_created' => $this->time->getRequestTime(),
        ])
        ->execute();
    }

    return $this;
  }

  public function publish() {
    $unpublished_chunk = $this->db->query('SELECT MAX(id) FROM {simple_sitemap} WHERE type = :type AND status = :status', [':type' => $this->sitemapVariant, ':status' => 0])
      ->fetchField();

    // Only allow publishing a sitemap variant if there is an unpublished
    // sitemap variant, as publishing involves deleting the currently published
    // variant.
    if (FALSE !== $unpublished_chunk) {
      $this->remove('published');
      $this->db->query('UPDATE {simple_sitemap} SET status = :status WHERE type = :type', [':type' => $this->sitemapVariant, ':status' => 1]);
    }

    return $this;
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
   * @return string
   */
  protected function getCustomBaseUrl() {
    $customBaseUrl = $this->settings['base_url'];
    return !empty($customBaseUrl) ? $customBaseUrl : $GLOBALS['base_url']; //todo use service
  }
}
