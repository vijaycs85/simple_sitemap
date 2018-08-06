<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\simple_sitemap\EntityHelper;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ArbitraryUrlGenerator
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 *
 * @UrlGenerator(
 *   id = "arbitrary",
 *   label = @Translation("Arbitrary URL generator"),
 *   description = @Translation("Generates URLs from data sets collected in the hook_arbitrary_links_alter hook."),
 * )
 */
class ArbitraryUrlGenerator extends UrlGeneratorBase {

  protected $moduleHandler;

  /**
   * ArbitraryUrlGenerator constructor.
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager $sitemap_generator_manager
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\simple_sitemap\Logger $logger
   * @param \Drupal\simple_sitemap\EntityHelper $entityHelper
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Simplesitemap $generator,
    SitemapGeneratorManager $sitemap_generator_manager,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    Logger $logger,
    EntityHelper $entityHelper,
    ModuleHandler $module_handler
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $generator,
      $sitemap_generator_manager,
      $language_manager,
      $entity_type_manager,
      $logger,
      $entityHelper
    );
    $this->moduleHandler = $module_handler;
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
      $container->get('simple_sitemap.generator'),
      $container->get('plugin.manager.simple_sitemap.sitemap_generator'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('simple_sitemap.logger'),
      $container->get('simple_sitemap.entity_helper'),
      $container->get('module_handler')
    );
  }


  /**
   * @inheritdoc
   */
  public function getDataSets() {
    $data_sets = [];
    $arbitrary_links = [];
    $sitemap_variant = $this->sitemapVariant;
    $this->moduleHandler->alter('simple_sitemap_arbitrary_links', $arbitrary_links, $sitemap_variant);
    $data_sets[] = array_values($arbitrary_links);
    return $data_sets;
  }

  /**
   * @inheritdoc
   */
  protected function processDataSet($data_set) {
    return $data_set;
  }
}
