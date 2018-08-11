<?php

namespace Drupal\simple_sitemap;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Path\PathValidator;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Component\Datetime\Time;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\DefaultSitemapGenerator;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorManager;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapType\SitemapTypeBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapType\SitemapTypeManager;

/**
 * Class Simplesitemap
 * @package Drupal\simple_sitemap
 */
class Simplesitemap {

  const DEFAULT_SITEMAP_TYPE = 'default_hreflang';
  const DEFAULT_SITEMAP_GENERATOR = 'default';
  const DEFAULT_SITEMAP_VARIANT = 'default';

  /**
   * @var \Drupal\simple_sitemap\EntityHelper
   */
  protected $entityHelper;

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * @var \Drupal\Core\Path\PathValidator
   */
  protected $pathValidator;

  /**
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\simple_sitemap\Batch
   */
  protected $batch;

  /**
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorManager
   */
  protected $urlGeneratorManager;

  /**
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager
   */
  protected $sitemapGeneratorManager;

  /**
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapType\SitemapTypeManager
   */
  protected $sitemapTypeManager;

  /**
   * @var UrlGeneratorBase[] $urlGenerators
   */
  protected $urlGenerators = [];

  /**
   * @var SitemapGeneratorBase[] $sitemapGenerators
   */
  protected $sitemapGenerators = [];

  /**
   * @var SitemapTypeBase[] $sitemapTypes
   */
  protected $sitemapTypes = [];

  /**
   * @var array
   */
  protected static $allowedLinkSettings = [
    'entity' => ['index', 'priority', 'changefreq', 'include_images'],
    'custom' => ['priority', 'changefreq'],
  ];

  /**
   * @var array
   */
  protected static $linkSettingDefaults = [
    'index' => FALSE,
    'priority' => 0.5,
    'changefreq' => '',
    'include_images' => FALSE,
  ];

  /**
   * Simplesitemap constructor.
   * @param \Drupal\simple_sitemap\EntityHelper $entity_helper
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   * @param \Drupal\Core\Path\PathValidator $path_validator
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   * @param \Drupal\Component\Datetime\Time $time
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   * @param \Drupal\simple_sitemap\Batch $batch
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorManager $url_generator_manager
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager $sitemap_generator_manager
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapType\SitemapTypeManager $sitemap_type_manager
   */
  public function __construct(
    EntityHelper $entity_helper,
    ConfigFactory $config_factory,
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    PathValidator $path_validator,
    DateFormatter $date_formatter,
    Time $time,
    ModuleHandler $module_handler,
    Batch $batch,
    UrlGeneratorManager $url_generator_manager,
    SitemapGeneratorManager $sitemap_generator_manager,
    SitemapTypeManager $sitemap_type_manager
  ) {
    $this->entityHelper = $entity_helper;
    $this->configFactory = $config_factory;
    $this->db = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->pathValidator = $path_validator;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
    $this->moduleHandler = $module_handler;
    $this->batch = $batch;
    $this->urlGeneratorManager = $url_generator_manager;
    $this->sitemapGeneratorManager = $sitemap_generator_manager;
    $this->sitemapTypeManager = $sitemap_type_manager;
  }

  /**
   * Returns a specific sitemap setting or a default value if setting does not
   * exist.
   *
   * @param string $name
   *  Name of the setting, like 'max_links'.
   *
   * @param mixed $default
   *  Value to be returned if the setting does not exist in the configuration.
   *
   * @return mixed
   *  The current setting from configuration or a default value.
   */
  public function getSetting($name, $default = FALSE) {
    $setting = $this->configFactory
      ->get('simple_sitemap.settings')
      ->get($name);
    return NULL !== $setting ? $setting : $default;
  }

  /**
   * Stores a specific sitemap setting in configuration.
   *
   * @param string $name
   *  Setting name, like 'max_links'.
   * @param mixed $setting
   *  The setting to be saved.
   *
   * @return $this
   */
  public function saveSetting($name, $setting) {
    $this->configFactory->getEditable('simple_sitemap.settings')
      ->set($name, $setting)->save();
    return $this;
  }

  /**
   * @param $sitemap_generator_id
   * @return \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorBase
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getSitemapGenerator($sitemap_generator_id) {
    if (!isset($this->sitemapGenerators[$sitemap_generator_id])) {
      $this->sitemapGenerators[$sitemap_generator_id]
        = $this->sitemapGeneratorManager->createInstance($sitemap_generator_id);
    }

    return $this->sitemapGenerators[$sitemap_generator_id];
  }

  /**
   * @param $url_generator_id
   * @return \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorBase
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getUrlGenerator($url_generator_id) {
    if (!isset($this->urlGenerators[$url_generator_id])) {
      $this->urlGenerators[$url_generator_id]
        = $this->urlGeneratorManager->createInstance($url_generator_id);
    }

    return $this->urlGenerators[$url_generator_id];
  }

  /**
   * @return array
   */
  public function getSitemapTypes() {
    if (empty($this->sitemapTypes)) {
      $this->sitemapTypes = $this->sitemapTypeManager->getDefinitions();
    }

    return $this->sitemapTypes;
  }

  /**
   * Returns the whole sitemap, a requested sitemap chunk,
   * or the sitemap index file.
   *
   * @param string $variant
   *
   * @param int $delta
   *
   * @return string|false
   *  If no sitemap ID provided, either a sitemap index is returned, or the
   *  whole sitemap variant, if the amount of links does not exceed the max
   *  links setting. If a sitemap ID is provided, a sitemap chunk is returned.
   *  Returns false if the sitemap is not retrievable from the database.
   */
  public function getSitemap($variant = self::DEFAULT_SITEMAP_VARIANT, $delta = NULL) {
    $chunk_info = $this->fetchSitemapVariantInfo($variant);

    if (empty($delta) || !isset($chunk_info[$delta])) {

      if (isset($chunk_info[SitemapGeneratorBase::INDEX_DELTA])) {
        // Return sitemap index if one exists.
        return $this->fetchSitemapChunk($chunk_info[SitemapGeneratorBase::INDEX_DELTA]->id)
          ->sitemap_string;
      }
      else {
        // Return sitemap chunk if there is only one chunk.
        return isset($chunk_info[SitemapGeneratorBase::FIRST_CHUNK_DELTA])
          ? $this->fetchSitemapChunk($chunk_info[SitemapGeneratorBase::FIRST_CHUNK_DELTA]->id)
            ->sitemap_string
          : FALSE;
      }
    }
    else {
      // Return specific sitemap chunk.
      return $this->fetchSitemapChunk($chunk_info[$delta]->id)->sitemap_string;
    }
  }

  /**
   * Fetches info about all sitemap variants and their chunks.
   *
   * @param string|null $variant
   *
   * @return array
   *  An array containing all sitemap chunk IDs, deltas and creation timestamps
   * keyed by their variant ID.
   */
  protected function fetchSitemapVariantInfo($variant = NULL) {
    $query = $this->db->select('simple_sitemap', 's')
      ->fields('s', ['id', 'delta', 'sitemap_created', 'type']);

    if (NULL !== $variant) {
      $query->condition('s.type', $variant);
    }

    $result = $query->execute();

    return NULL === $variant ? $result->fetchAllAssoc('type') : $result->fetchAllAssoc('delta');
  }

  /**
   * Fetches a single sitemap chunk by ID.
   *
   * @param int $id
   *   The chunk ID.
   *
   * @return object
   *   A sitemap chunk object.
   */
  protected function fetchSitemapChunk($id) {
    return $this->db->query('SELECT * FROM {simple_sitemap} WHERE id = :id',
      [':id' => $id])->fetchObject();
  }

  /**
   * @param null $sitemap_type
   * @return array
   *
   * @todo document
   * @todo translate label
   */
  public function getSitemapVariants($sitemap_type = NULL, $attach_type_info = TRUE) {
    if (NULL === $sitemap_type) {
      $variants = [];
      foreach ($this->configFactory->listAll('simple_sitemap.variants.') as $config_name) {
        $config_name_parts = explode('.', $config_name);
        $saved_variants = $this->configFactory->get($config_name)->get('variants');
        $saved_variants = $attach_type_info ? $this->attachSitemapTypeToVariants($saved_variants, $config_name_parts[2]) : $saved_variants;
        $variants = array_merge($variants, (is_array($saved_variants) ? $saved_variants : []));
      }
      return $variants;
    }
    else {
      $variants = $this->configFactory->get("simple_sitemap.variants.$sitemap_type")->get('variants');
      $variants = is_array($variants) ? $variants : [];
      return $attach_type_info ? $this->attachSitemapTypeToVariants($variants, $sitemap_type) : $variants;
    }
  }

  protected function attachSitemapTypeToVariants(array $variants, $type) {
    return array_map(function($variant) use ($type) { return $variant + ['type' => $type]; }, $variants);
  }

  protected function detachSitemapTypeFromVariants(array $variants) {
    return array_map(function($variant) { unset($variant['type']); return $variant; }, $variants);
  }

  /**
   * @param $name
   * @param $definition
   * @return $this
   *
   * @todo document
   * @todo exceptions
   */
  public function addSitemapVariant($name, $definition = []) {
    if (empty($definition['label'])) {
      $definition['label'] = $name;
    }

    if (empty($definition['type'])) {
      $definition['type'] = self::DEFAULT_SITEMAP_TYPE;
    }
    else {
      $types = $this->getSitemapTypes();
      if (!isset($types[$definition['type']])) {
        // todo: exception
      }
    }
    $all_variants = $this->getSitemapVariants();
    if (isset($all_variants[$name])) {
      //todo: exception
    }
    else {
      $variants = array_merge($this->getSitemapVariants($definition['type'], FALSE), [$name => ['label' => $definition['label']]]);
      $this->configFactory->getEditable('simple_sitemap.variants.' . $definition['type'])
        ->set('variants', $variants)
        ->save();
    }

    return $this;
  }

  public function removeSitemapVariants($variant_names = NULL) {
    $this->removeSitemap($variant_names);
    if (NULL === $variant_names) {
      foreach ($this->configFactory->listAll('simple_sitemap.variants.') as $config_name) {
        $this->configFactory->getEditable($config_name)->delete();
      }
    }
    else {
      $remove_variants = [];
      $variants = $this->getSitemapVariants();
      foreach ((array) $variant_names as $variant_name) {
        if (isset($variants[$variant_name])) {
          $remove_variants[$variants[$variant_name]['type']][$variant_name] = $variant_name;
        }
      }

      foreach ($remove_variants as $type => $variants_per_type) {
        $this->configFactory->getEditable("simple_sitemap.variants.$type")
          ->set('variants', array_diff_key($this->getSitemapVariants($type, FALSE), $variants_per_type))
          ->save();
      }
    }

    return $this;
  }

  /**
   * @param null $variants
   * @return $this
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *
   * @todo document
   */
  public function removeSitemap($variants = NULL) {
    $saved_variants = $this->getSitemapVariants();
    $this->moduleHandler->alter('simple_sitemap_variants', $saved_variants);
    $remove_variants = NULL !== $variants
      ? array_intersect_key($saved_variants, array_flip((array) $variants))
      : $saved_variants;

    if (!empty($remove_variants)) {
      $type_definitions = $this->getSitemapTypes();
      $this->moduleHandler->alter('simple_sitemap_types', $type_definitions);
      foreach ($remove_variants as $variant_name => $variant_definition) {
        $this->getSitemapGenerator($type_definitions[$variant_definition['type']]['sitemapGenerator'])
          ->setSitemapVariant($variant_name)
          ->remove()
          ->invalidateCache();
      }
    }

    return $this;
  }

  /**
   * Generates the XML sitemap and saves it to the database.
   *
   * @param string $from
   *  Can be 'form', 'backend', 'drush' or 'nobatch'.
   *  This decides how the batch process is to be run.
   *
   * @param array|string|null $variants
   *
   * @return bool|\Drupal\simple_sitemap\Simplesitemap
   */
  public function generateSitemap($from = 'form', $variants = NULL) {
    $variants = NULL === $variants ? NULL : (array) $variants;

    $settings = [
      'base_url' => $this->getSetting('base_url', ''),
      'batch_process_limit' => $this->getSetting('batch_process_limit', 1500),
      'max_links' => $this->getSetting('max_links', 2000),
      'skip_untranslated' => $this->getSetting('skip_untranslated', FALSE),
      'remove_duplicates' => $this->getSetting('remove_duplicates', TRUE),
      'excluded_languages' => $this->getSetting('excluded_languages', []),
    ];

    $this->batch->setBatchMeta(['from' => $from]);

    $operations = [];

    $type_definitions = $this->getSitemapTypes();
    $this->moduleHandler->alter('simple_sitemap_types', $type_definitions);

    $sitemap_variants = $this->getSitemapVariants();
    $this->moduleHandler->alter('simple_sitemap_variants', $sitemap_variants);

    foreach ($sitemap_variants as $variant_name => $variant_definition) {

      // Skipping unwanted sitemap variants.
      if (NULL !== $variants && !in_array($variant_name, $variants)) {
        continue;
      }

      $type = $variant_definition['type'];

      // Adding a remove_sitemap operation for all sitemap variants.
      $operations[] = [
        'operation' => 'removeSitemap',
        'arguments' => [
          'sitemap_generator' => $type_definitions[$type]['sitemapGenerator'],
          'variant' => $variant_name,
        ]
      ];

      // Adding generate_sitemap operations for all data sets.
      foreach ($type_definitions[$type]['urlGenerators'] as $url_generator_id) {

        foreach ($this->getUrlGenerator($url_generator_id)
                   ->setSitemapVariant($variant_name)
                   ->getDataSets() as $data_set) {
          if (!empty($data_set)) {
            $operations[] = [
              'operation' => 'generateSitemap',
              'arguments' => [
                'url_generator' => $url_generator_id,
                'data_set' => $data_set,
                'variant' => $variant_name,
                'sitemap_generator' => $type_definitions[$type]['sitemapGenerator'],
                'settings' => $settings,
              ],
            ];
          }
        }
      }

      // Adding generate_index operations for all sitemap variants.
      $operations[] = [
        'operation' => 'generateIndex',
        'arguments' => [
          'sitemap_generator' => $type_definitions[$type]['sitemapGenerator'],
          'variant' => $variant_name,
          'settings' => $settings,
        ],
      ];
    }

    // Adding operations to and starting batch.
    if (!empty($operations)) {
      foreach ($operations as $operation_data) {
        $this->batch->addOperation($operation_data['operation'], $operation_data['arguments']);
      }
      $success = $this->batch->start();
    }

    return $from === 'nobatch' ? $this : (isset($success) ? $success : FALSE);
  }

  /**
   * Returns a 'time ago' string of last timestamp generation.
   *
   * @param string|null $variant
   *
   * @return string|array|false
   *  Formatted timestamp of last sitemap generation, otherwise FALSE.
   */
  public function getGeneratedAgo($variant = NULL) {
    $chunks = $this->fetchSitemapVariantInfo($variant);
    if ($variant !== NULL) {
      return isset($chunks[DefaultSitemapGenerator::FIRST_CHUNK_DELTA]->sitemap_created)
        ? $this->dateFormatter
          ->formatInterval($this->time->getRequestTime() - $chunks[DefaultSitemapGenerator::FIRST_CHUNK_DELTA]
              ->sitemap_created)
        : FALSE;
    }
    else {
      $time_strings = [];
//      foreach ($chunks as $sitemap_type => $type_chunks) {
//        $time_strings[$sitemap_type] = isset($type_chunks[DefaultSitemapGenerator::FIRST_DELTA_INDEX]->sitemap_created)
//          ? $type_chunks[DefaultSitemapGenerator::FIRST_DELTA_INDEX]->sitemap_created
//          : FALSE;
//    }
      // todo: Implement.
      return $time_strings;
    }
  }

  /**
   * Enables sitemap support for an entity type. Enabled entity types show
   * sitemap settings on their bundle setting forms. If an enabled entity type
   * features bundles (e.g. 'node'), it needs to be set up with
   * setBundleSettings() as well.
   *
   * @param string $entity_type_id
   *  Entity type id like 'node'.
   *
   * @return $this
   */
  public function enableEntityType($entity_type_id) {
    $enabled_entity_types = $this->getSetting('enabled_entity_types');
    if (!in_array($entity_type_id, $enabled_entity_types)) {
      $enabled_entity_types[] = $entity_type_id;
      $this->saveSetting('enabled_entity_types', $enabled_entity_types);
    }
    return $this;
  }

  /**
   * Disables sitemap support for an entity type. Disabling support for an
   * entity type deletes its sitemap settings permanently and removes sitemap
   * settings from entity forms.
   *
   * @param string $entity_type_id
   *  Entity type id like 'node'.
   *
   * @return $this
   */
  public function disableEntityType($entity_type_id) {

    // Updating settings.
    $enabled_entity_types = $this->getSetting('enabled_entity_types');
    if (FALSE !== ($key = array_search($entity_type_id, $enabled_entity_types))) {
      unset ($enabled_entity_types[$key]);
      $this->saveSetting('enabled_entity_types', array_values($enabled_entity_types));
    }

    // Deleting inclusion settings.
    $config_names = $this->configFactory->listAll("simple_sitemap.bundle_settings.$entity_type_id.");
    foreach ($config_names as $config_name) {
      $this->configFactory->getEditable($config_name)->delete();
    }

    // Deleting entity overrides.
    $this->removeEntityInstanceSettings($entity_type_id);
    return $this;
  }

  /**
   * Sets sitemap settings for a non-bundle entity type (e.g. user) or a bundle
   * of an entity type (e.g. page).
   *
   * @param string $entity_type_id
   *  Entity type id like 'node' the bundle belongs to.
   * @param string $bundle_name
   *  Name of the bundle. NULL if entity type has no bundles.
   * @param array $settings
   *  An array of sitemap settings for this bundle/entity type.
   *  Example: ['index' => TRUE, 'priority' => 0.5, 'changefreq' => 'never', 'include_images' => FALSE].
   *
   * @return $this
   *
   * @todo: enableEntityType automatically
   */
  public function setBundleSettings($entity_type_id, $bundle_name = NULL, $settings = ['index' => TRUE]) {
    $bundle_name = NULL !== $bundle_name ? $bundle_name : $entity_type_id;

    if (!empty($old_settings = $this->getBundleSettings($entity_type_id, $bundle_name))) {
      $settings = array_merge($old_settings, $settings);
    }
    self::supplementDefaultSettings('entity', $settings);

    if ($settings != $old_settings) {

      // Save new bundle settings to configuration.
      $bundle_settings = $this->configFactory
        ->getEditable("simple_sitemap.bundle_settings.$entity_type_id.$bundle_name");
      foreach ($settings as $setting_key => $setting) {
        $bundle_settings->set($setting_key, $setting);
      }
      $bundle_settings->save();

      // Delete entity overrides which are identical to new bundle settings.
      $sitemap_entity_types = $this->entityHelper->getSupportedEntityTypes();
      if (isset($sitemap_entity_types[$entity_type_id])) {
        $entity_type = $sitemap_entity_types[$entity_type_id];
        $keys = $entity_type->getKeys();

        // Menu fix.
        $keys['bundle'] = $entity_type_id === 'menu_link_content' ? 'menu_name' : $keys['bundle'];

        $query = $this->entityTypeManager->getStorage($entity_type_id)->getQuery();
        if (!$this->entityHelper->entityTypeIsAtomic($entity_type_id)) {
          $query->condition($keys['bundle'], $bundle_name);
        }
        $entity_ids = $query->execute();

        $query = $this->db->select('simple_sitemap_entity_overrides', 'o')
          ->fields('o', ['id', 'inclusion_settings'])
          ->condition('o.entity_type', $entity_type_id);
        if (!empty($entity_ids)) {
          $query->condition('o.entity_id', $entity_ids, 'IN');
        }

        $delete_instances = [];
        foreach ($query->execute()->fetchAll() as $result) {
          $delete = TRUE;
          $instance_settings = unserialize($result->inclusion_settings);
          foreach ($instance_settings as $setting_key => $instance_setting) {
            if ($instance_setting != $settings[$setting_key]) {
              $delete = FALSE;
              break;
            }
          }
          if ($delete) {
            $delete_instances[] = $result->id;
          }
        }
        if (!empty($delete_instances)) {
          $this->db->delete('simple_sitemap_entity_overrides')
            ->condition('id', $delete_instances, 'IN')
            ->execute();
        }
      }
      else {
        //todo: log error
      }
    }

    return $this;
  }

  /**
   * Gets sitemap settings for an entity bundle, a non-bundle entity type or for
   * all entity types and their bundles.
   *
   * @param string|null $entity_type_id
   *  If set to null, sitemap settings for all entity types and their bundles
   *  are fetched.
   * @param string|null $bundle_name
   *
   * @return array|false
   *  Array of sitemap settings for an entity bundle, a non-bundle entity type
   *  or for all entity types and their bundles.
   *  False if entity type does not exist.
   */
  public function getBundleSettings($entity_type_id = NULL, $bundle_name = NULL) {
    if (NULL !== $entity_type_id) {

      // Get bundle settings saved in simple_sitemap.bundle_settings.*.* configuration.
      $bundle_name = NULL !== $bundle_name ? $bundle_name : $entity_type_id;
      $bundle_settings = $this->configFactory
        ->get("simple_sitemap.bundle_settings.$entity_type_id.$bundle_name")
        ->get();

      // If not found and entity type is enabled, return default bundle settings.
      if (empty($bundle_settings)) {
        if ($this->entityTypeIsEnabled($entity_type_id)
          && isset($this->entityTypeBundleInfo->getBundleInfo($entity_type_id)[$bundle_name])) {
          self::supplementDefaultSettings('entity', $bundle_settings);
        }
        else {
          $bundle_settings = FALSE;
        }
      }
    }
    else {
      // Get all bundle settings saved in simple_sitemap.bundle_settings.*.* configuration.
      $config_names = $this->configFactory->listAll('simple_sitemap.bundle_settings.');
      $bundle_settings = [];
      foreach ($config_names as $config_name) {
        $config_name_parts = explode('.', $config_name);
        $bundle_settings[$config_name_parts[2]][$config_name_parts[3]] = $this->configFactory->get($config_name)->get();
      }

      // Supplement default bundle settings for all bundles not found in simple_sitemap.bundle_settings.*.* configuration.
      foreach ($this->entityHelper->getSupportedEntityTypes() as $type_id => $type_definition) {
        if ($this->entityTypeIsEnabled($type_id)) {
          foreach($this->entityTypeBundleInfo->getBundleInfo($type_id) as $bundle => $bundle_definition) {
            if (!isset($bundle_settings[$type_id][$bundle])) {
              self::supplementDefaultSettings('entity', $bundle_settings[$type_id][$bundle]);
            }
          }
        }
      }
    }
    return $bundle_settings;
  }

  /**
   * Supplements all missing link setting with default values.
   *
   * @param string $type
   *  'entity'|'custom'
   * @param array &$settings
   * @param array $overrides
   */
  public static function supplementDefaultSettings($type, &$settings, $overrides = []) {
    foreach (self::$allowedLinkSettings[$type] as $allowed_link_setting) {
      if (!isset($settings[$allowed_link_setting])
        && isset(self::$linkSettingDefaults[$allowed_link_setting])) {
        $settings[$allowed_link_setting] = isset($overrides[$allowed_link_setting])
          ? $overrides[$allowed_link_setting]
          : self::$linkSettingDefaults[$allowed_link_setting];
      }
    }
  }

  /**
   * Overrides entity bundle/entity type sitemap settings for a single entity.
   *
   * @param string $entity_type_id
   * @param int $id
   * @param array $settings
   *
   * @return $this
   */
  public function setEntityInstanceSettings($entity_type_id, $id, $settings) {
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
    $bundle_settings = $this->getBundleSettings(
      $entity_type_id, $this->entityHelper->getEntityInstanceBundleName($entity)
    );
    if (!empty($bundle_settings)) {

      // Check if overrides are different from bundle setting before saving.
      $override = FALSE;
      foreach ($settings as $key => $setting) {
        if (!isset($bundle_settings[$key]) || $setting != $bundle_settings[$key]) {
          $override = TRUE;
          break;
        }
      }

      // Save overrides for this entity if something is different.
      if ($override) {
        $this->db->merge('simple_sitemap_entity_overrides')
          ->key([
            'entity_type' => $entity_type_id,
            'entity_id' => $id])
          ->fields([
            'entity_type' => $entity_type_id,
            'entity_id' => $id,
            'inclusion_settings' => serialize(array_merge($bundle_settings, $settings)),])
          ->execute();
      }
      // Else unset override.
      else {
        $this->removeEntityInstanceSettings($entity_type_id, $id);
      }
    }
    else {
      //todo: log error
    }
    return $this;
  }

  /**
   * Gets sitemap settings for an entity instance which overrides the sitemap
   * settings of its bundle, or bundle settings, if they are not overridden.
   *
   * @param string $entity_type_id
   * @param int $id
   *
   * @return array|false
   */
  public function getEntityInstanceSettings($entity_type_id, $id) {
    $results = $this->db->select('simple_sitemap_entity_overrides', 'o')
      ->fields('o', ['inclusion_settings'])
      ->condition('o.entity_type', $entity_type_id)
      ->condition('o.entity_id', $id)
      ->execute()
      ->fetchField();

    if (!empty($results)) {
      return unserialize($results);
    }
    else {
      $entity = $this->entityTypeManager->getStorage($entity_type_id)
        ->load($id);
      return $this->getBundleSettings(
        $entity_type_id,
        $this->entityHelper->getEntityInstanceBundleName($entity)
      );
    }
  }

  /**
   * Removes sitemap settings for an entity that overrides the sitemap settings
   * of its bundle.
   *
   * @param string $entity_type_id
   * @param string|null $entity_ids
   *
   * @return $this
   */
  public function removeEntityInstanceSettings($entity_type_id, $entity_ids = NULL) {
    $query = $this->db->delete('simple_sitemap_entity_overrides')
      ->condition('entity_type', $entity_type_id);
    if (NULL !== $entity_ids) {
      $entity_ids = !is_array($entity_ids) ? [$entity_ids] : $entity_ids;
      $query->condition('entity_id', $entity_ids, 'IN');
    }
    $query->execute();
    return $this;
  }

  /**
   * Checks if an entity bundle (or a non-bundle entity type) is set to be
   * indexed in the sitemap settings.
   *
   * @param string $entity_type_id
   * @param string|null $bundle_name
   *
   * @return bool
   */
  public function bundleIsIndexed($entity_type_id, $bundle_name = NULL) {
    $settings = $this->getBundleSettings($entity_type_id, $bundle_name);
    return !empty($settings['index']);
  }

  /**
   * Checks if an entity type is enabled in the sitemap settings.
   *
   * @param string $entity_type_id
   *
   * @return bool
   */
  public function entityTypeIsEnabled($entity_type_id) {
    return in_array($entity_type_id, $this->getSetting('enabled_entity_types', []));
  }

  /**
   * Stores a custom path along with its sitemap settings to configuration.
   *
   * @param string $path
   * @param array $settings
   *
   * @return $this
   *
   * @todo Validate $settings and throw exceptions
   */
  public function addCustomLink($path, $settings = []) {
    if (!(bool) $this->pathValidator->getUrlIfValidWithoutAccessCheck($path)) {
      // todo: log error.
      return $this;
    }
    if ($path[0] !== '/') {
      // todo: log error.
      return $this;
    }

    $custom_links = $this->getCustomLinks(FALSE);
    foreach ($custom_links as $key => $link) {
      if ($link['path'] === $path) {
        $link_key = $key;
        break;
      }
    }
    $link_key = isset($link_key) ? $link_key : count($custom_links);
    $custom_links[$link_key] = ['path' => $path] + $settings;
    $this->configFactory->getEditable('simple_sitemap.custom')
      ->set('links', $custom_links)->save();

    return $this;
  }

  /**
   * Returns an array of custom paths and their sitemap settings.
   *
   * @param bool $supplement_default_settings
   * @return array
   */
  public function getCustomLinks($supplement_default_settings = TRUE) {
    $custom_links = $this->configFactory
      ->get('simple_sitemap.custom')
      ->get('links');

    if ($supplement_default_settings) {
      foreach ($custom_links as $i => $link_settings) {
        self::supplementDefaultSettings('custom', $link_settings);
        $custom_links[$i] = $link_settings;
      }
    }

    return !empty($custom_links) ? $custom_links : [];
  }

  /**
   * Returns settings for a custom path added to the sitemap settings.
   *
   * @param string $path
   *
   * @return array|false
   */
  public function getCustomLink($path) {
    foreach ($this->getCustomLinks() as $key => $link) {
      if ($link['path'] === $path) {
        return $link;
      }
    }
    return FALSE;
  }

  /**
   * Removes all custom paths from the sitemap settings.
   *
   * @return $this
   */
  public function removeCustomLinks($paths = NULL) {
    if (NULL === $paths) {
      $custom_links = [];
      $save = TRUE;
    }
    else {
      $custom_links = $this->getCustomLinks(FALSE);
      foreach ((array) $paths as $path) {
        foreach ($custom_links as $key => $link) {
          if ($link['path'] === $path) {
            unset($custom_links[$key]);
            $save = TRUE;
          }
        }
      }
    }
    if (!empty($save)) {
      $this->configFactory->getEditable('simple_sitemap.custom')
        ->set('links', array_values($custom_links))->save();
    }

    return $this;
  }
}
