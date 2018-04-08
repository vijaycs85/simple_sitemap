<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Component\Datetime\Time;

/**
 * Class DefaultSitemapGenerator
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator
 *
 * @SitemapGenerator(
 *   id = "index",
 *   title = @Translation("Sitemap index generator"),
 *   description = @Translation("Generates the sitemap index."),
 *   weight = 0,
 *   settings = {
 *     "list" = false,
 *   },
 * )
 *
 * @todo Save index in DB instead of creating on the fly.
 */
class IndexSitemapGenerator extends SitemapGeneratorBase {

  /**
   * @var array
   */
  protected static $indexAttributes = [
    'xmlns' => self::XMLNS,
  ];

  /**
   * DefaultSitemapGenerator constructor.
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Component\Datetime\Time $time
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapWriter $sitemapWriter
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Connection $database,
    ModuleHandler $module_handler,
    LanguageManagerInterface $language_manager,
    Time $time,
    SitemapWriter $sitemapWriter
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $database,
      $module_handler,
      $language_manager,
      $time,
      $sitemapWriter
    );
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
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

//  public function generateSitemapIndex(array $chunk_info) {
//    $values = [
//      'id' => $this->configuration['id'],
//      'sitemap_string' => $this->getXml($chunk_info),
//    ];
//    $this->db->insert('simple_sitemap')->fields($values)->execute();
//  }

  public function getSitemapIndex($chunk_info) {
    return $this->getXml($chunk_info);
  }

  /**
   * Generates and returns the sitemap index for all sitemap chunks.
   *
   * @param array $chunk_info
   *   Array containing chunk creation timestamps keyed by chunk ID.
   *
   * @return string sitemap index
   */
  protected function getXml(array $chunk_info) {
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

    // Add sitemap chunk locations to document.
    foreach ($chunk_info as $chunk_data) {
      $this->writer->startElement('sitemap');
      $this->writer->writeElement('loc', $this->getCustomBaseUrl()
        . '/sitemaps/' . $chunk_data->type . '/' . $chunk_data->delta . '/sitemap.xml');
      $this->writer->writeElement('lastmod', date_iso8601($chunk_data->sitemap_created));
      $this->writer->endElement();
    }

    $this->writer->endElement();
    $this->writer->endDocument();

    return $this->writer->outputMemory();
  }

}
