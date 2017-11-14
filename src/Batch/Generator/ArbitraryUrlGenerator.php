<?php

namespace Drupal\simple_sitemap\Batch\Generator;

use Drupal\simple_sitemap\EntityHelper;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\simple_sitemap\SitemapGenerator;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;

/**
 * Class ArbitraryUrlGenerator
 * @package Drupal\simple_sitemap\Batch\Generator
 */
class ArbitraryUrlGenerator extends UrlGeneratorBase implements UrlGeneratorInterface {

  protected $moduleHandler;

  /**
   * ArbitraryUrlGenerator constructor.
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   * @param \Drupal\simple_sitemap\SitemapGenerator $sitemap_generator
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\simple_sitemap\Logger $logger
   * @param \Drupal\simple_sitemap\EntityHelper $entityHelper
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   */
  public function __construct(
    Simplesitemap $generator,
    SitemapGenerator $sitemap_generator,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    Logger $logger,
    EntityHelper $entityHelper,
    ModuleHandler $module_handler
  ) {
    parent::__construct(
      $generator,
      $sitemap_generator,
      $language_manager,
      $entity_type_manager,
      $logger,
      $entityHelper
    );
    $this->moduleHandler = $module_handler;
  }

  /**
   * @return array
   */
  protected function getData() {
    $arbitrary_links = [];
    $this->moduleHandler->alter('simple_sitemap_arbitrary_links', $arbitrary_links);
    return $arbitrary_links;
  }

  /**
   * Batch function that adds arbitrary URLs to the sitemap.
   */
  public function generate() {
    foreach ($this->getBatchIterationElements(array_values($this->getData())) as $i => $path_data) {
      $this->setCurrentId($i);
      $this->addUrl($path_data);
    }
    $this->processSegment();
  }
}
