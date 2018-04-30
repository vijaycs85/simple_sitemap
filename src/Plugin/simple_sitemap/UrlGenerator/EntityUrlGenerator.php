<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\simple_sitemap\EntityHelper;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandler;

/**
 * Class EntityUrlGenerator
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 *
 * @UrlGenerator(
 *   id = "entity",
 *   title = @Translation("Entity URL generator"),
 *   description = @Translation("Generates URLs for entity bundles and bundle overrides."),
 * )
 */
class EntityUrlGenerator extends UrlGeneratorBase {

  /**
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorManager
   */
  protected $urlGeneratorManager;

  /**
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * EntityUrlGenerator constructor.
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager $sitemap_generator_manager
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\simple_sitemap\Logger $logger
   * @param \Drupal\simple_sitemap\EntityHelper $entityHelper
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorManager $url_generator_manager
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
    UrlGeneratorManager $url_generator_manager,
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
    $this->urlGeneratorManager = $url_generator_manager;
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
      $container->get('plugin.manager.simple_sitemap.url_generator'),
      $container->get('module_handler')
    );
  }

  /**
   * @inheritdoc
   */
  public function getDataSets() {
    $data_sets = [];
    $sitemap_entity_types = $this->entityHelper->getSupportedEntityTypes();

    foreach ($this->generator->getBundleSettings() as $entity_type_name => $bundles) {
      if (isset($sitemap_entity_types[$entity_type_name])) {

        // Skip this entity type if another plugin is written to override its generation.
        foreach ($this->urlGeneratorManager->getDefinitions() as $plugin) {
          if (!empty($plugin['settings']['overrides_entity_type'])
            && $plugin['settings']['overrides_entity_type'] === $entity_type_name) {
            continue 2;
          }
        }

        $entity_type_name_alterable = $entity_type_name;
        foreach ($bundles as $bundle_name => $bundle_settings) {
          $bundle_name_alterable = $bundle_name;

          $this->moduleHandler->alter('simple_sitemap_bundle_settings', $bundle_settings, $entity_type_name_alterable, $bundle_name_alterable);

          // Skip this bundle if it is to be generated in a different sitemap variant.
          if (NULL !== $this->sitemapVariant && isset($bundle_settings['variant'])
            && $bundle_settings['variant'] !== $this->sitemapVariant) {
            continue;
          }

          if ($bundle_settings['index']) {
            $data_sets[] = [
              'bundle_settings' => $bundle_settings,
              'bundle_name' => $bundle_name,
              'entity_type_name' => $entity_type_name,
              'keys' => $sitemap_entity_types[$entity_type_name]->getKeys(),
            ];
          }
        }
      }
    }

    return $data_sets;
  }

  /**
   * @inheritdoc
   */
  protected function processDataSet($entity) {

    $entity_id = $entity->id();
    $entity_type_name = $entity->getEntityTypeId();

    $entity_settings = $this->generator->getEntityInstanceSettings($entity_type_name, $entity_id);

    if (empty($entity_settings['index'])) {
      return FALSE;
    }

    $url_object = $entity->toUrl();

    // Do not include external paths.
    if (!$url_object->isRouted()) {
      return FALSE;
    }

    $path = $url_object->getInternalPath();

    // Do not include paths that have been already indexed.
    if ($this->settings['remove_duplicates'] && $this->pathProcessed($path)) {
      return FALSE;
    }

    $url_object->setOption('absolute', TRUE);

    return [
      'url' => $url_object,
      'lastmod' => method_exists($entity, 'getChangedTime') ? date_iso8601($entity->getChangedTime()) : NULL,
      'priority' => isset($entity_settings['priority']) ? $entity_settings['priority'] : NULL,
      'changefreq' => !empty($entity_settings['changefreq']) ? $entity_settings['changefreq'] : NULL,
      'images' => !empty($entity_settings['include_images'])
        ? $this->getImages($entity_type_name, $entity_id)
        : [],

      // Additional info useful in hooks.
      'meta' => [
        'path' => $path,
        'entity_info' => [
          'entity_type' => $entity_type_name,
          'id' => $entity_id,
        ],
      ]
    ];
  }

  /**
   * @inheritdoc
   */
  protected function getBatchIterationElements($entity_info) {
    $query = $this->entityTypeManager->getStorage($entity_info['entity_type_name'])->getQuery();

    if (!empty($entity_info['keys']['id'])) {
      $query->sort($entity_info['keys']['id'], 'ASC');
    }
    if (!empty($entity_info['keys']['bundle'])) {
      $query->condition($entity_info['keys']['bundle'], $entity_info['bundle_name']);
    }
    if (!empty($entity_info['keys']['status'])) {
      $query->condition($entity_info['keys']['status'], 1);
    }

    if ($this->batchOperationNeedsInitialization()) {
      $count_query = clone $query;
      $this->initializeBatchOperation($count_query->count()->execute());
    }

    if ($this->isDrupalBatch()) {
      $query->range($this->context['sandbox']['progress'], $this->settings['batch_process_limit']);
    }

    return $this->entityTypeManager
      ->getStorage($entity_info['entity_type_name'])
      ->loadMultiple($query->execute());
  }
}
