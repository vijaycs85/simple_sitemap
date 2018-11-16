<?php

namespace Drupal\simple_sitemap;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\simple_sitemap\Queue\QueueWorker;
use Drupal\Core\Path\PathValidator;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Component\Datetime\Time;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\DefaultSitemapGenerator;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorBase;

/**
 * Class Simplesitemap
 * @package Drupal\simple_sitemap
 */
class Simplesitemap {
  /**
   * @var \Drupal\simple_sitemap\EntityHelper
   */
  protected $entityHelper;

  /**
   * @var \Drupal\simple_sitemap\SimplesitemapSettings
   */
  protected $settings;

  /**
   * @var \Drupal\simple_sitemap\SimplesitemapManager
   */
  protected $manager;

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
   * @var \Drupal\simple_sitemap\Queue\QueueWorker
   */
  protected $queueWorker;

  /**
   * @var array
   */
  protected $variants;

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
    'priority' => '0.5',
    'changefreq' => '',
    'include_images' => FALSE,
  ];

  /**
   * Simplesitemap constructor.
   * @param \Drupal\simple_sitemap\EntityHelper $entity_helper
   * @param \Drupal\simple_sitemap\SimplesitemapSettings $settings
   * @param \Drupal\simple_sitemap\SimplesitemapManager $manager
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   * @param \Drupal\Core\Path\PathValidator $path_validator
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   * @param \Drupal\Component\Datetime\Time $time
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   * @param \Drupal\simple_sitemap\Queue\QueueWorker $queue_worker
   */
  public function __construct(
    EntityHelper $entity_helper,
    SimplesitemapSettings $settings,
    SimplesitemapManager $manager,
    ConfigFactory $config_factory,
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    PathValidator $path_validator,
    DateFormatter $date_formatter,
    Time $time,
    ModuleHandler $module_handler,
    QueueWorker $queue_worker
  ) {
    $this->entityHelper = $entity_helper;
    $this->settings = $settings;
    $this->manager = $manager;
    $this->configFactory = $config_factory;
    $this->db = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->pathValidator = $path_validator;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
    $this->moduleHandler = $module_handler;
    $this->queueWorker = $queue_worker;
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
    return $this->settings->getSetting($name, $default);
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
    $this->settings->saveSetting($name, $setting);
    return $this;
  }

  /**
   * @return \Drupal\simple_sitemap\Queue\QueueWorker
   */
  public function getQueueWorker() {
    return $this->queueWorker;
  }

  /**
   * @return \Drupal\simple_sitemap\SimplesitemapManager
   */
  public function getSitemapManager() {
    return $this->manager;
  }

  /**
   * @param array|string|true|null $variants
   *  array: Array of variants to be set.
   *  string: A particular variant to be set.
   *  null: Default variant will be set.
   *  true: All existing variants will be set.
   *
   * @return $this
   */
  public function setVariants($variants = NULL) {
    if (NULL === $variants) {
      $this->variants = FALSE !== ($default_variant = $this->getSetting('default_variant')) ? [$default_variant] : [];
    }
    elseif ($variants === TRUE) {
      $this->variants = array_keys(
        $this->manager->getSitemapVariants(NULL, FALSE));
    }
    else {
      $this->variants = (array) $variants;
    }

    return $this;
  }

  /**
   * @param bool $default_get_all
   * @return array
   */
  protected function getVariants($default_get_all = TRUE) {
    if (NULL === $this->variants) {
      $this->setVariants($default_get_all ? TRUE : NULL);
    }

    return $this->variants;
  }

  /**
   * Returns the whole sitemap, a requested sitemap chunk,
   * or the sitemap index file.
   *
   * @param int $delta
   *
   * @return string|false
   *  If no sitemap delta is provided, either a sitemap index is returned, or the
   *  whole sitemap variant, if the amount of links does not exceed the max
   *  links setting. If a sitemap delta is provided, a sitemap chunk is returned.
   *  Returns false if the sitemap is not retrievable from the database.
   */
  public function getSitemap($delta = NULL) {
    $chunk_info = $this->fetchSitemapVariantInfo();

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
   * Fetches info about all published sitemap variants and their chunks.
   *
   * @return array
   *  An array containing all published sitemap chunk IDs, deltas and creation
   * timestamps keyed by their variant ID.
   */
  protected function fetchSitemapVariantInfo() {
    $result = $this->db->select('simple_sitemap', 's')
      ->fields('s', ['id', 'delta', 'sitemap_created', 'type'])
      ->condition('s.status', 1)
      ->condition('s.type', $this->getVariants(), 'IN')
      ->execute();

    return count($this->getVariants()) > 1
      ? $result->fetchAllAssoc('type')
      : $result->fetchAllAssoc('delta');
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
   * @return $this
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function removeSitemap() {
    $this->manager->removeSitemap($this->getVariants(FALSE));

    return $this;
  }

  /**
   * @param string $from
   * @return $this
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *
   * @todo variants
   */
  public function generateSitemap($from = 'form') {
    switch($from) {
      case 'form':
      case 'drush':
        $this->queueWorker->batchGenerateSitemap($from);
        break;

      case 'cron':
      case 'backend':
        $this->queueWorker->generateSitemap($from);
        break;
    }

    return $this;
  }

  public function rebuildQueue() {
    $this->queueWorker->rebuildQueue($this->getVariants());

    return $this;
  }

  /**
   * Returns a 'time ago' string of last timestamp generation.
   *
   * @param string|null $variant
   *
   * @return string|array|false
   *  Formatted timestamp of last sitemap generation, otherwise FALSE.
   *
   * @todo: variants
   */
  public function getGeneratedAgo() {
    $chunks = $this->fetchSitemapVariantInfo();
    return isset($chunks[DefaultSitemapGenerator::FIRST_CHUNK_DELTA]->sitemap_created)
      ? $this->dateFormatter
        ->formatInterval($this->time->getRequestTime() - $chunks[DefaultSitemapGenerator::FIRST_CHUNK_DELTA]
            ->sitemap_created)
      : FALSE;
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
    $config_names = $this->configFactory->listAll('simple_sitemap.bundle_settings.');
    foreach ($config_names as $config_name) {
      $config_name_parts = explode('.', $config_name);
      if ($config_name_parts[3] === $entity_type_id) {
        $this->configFactory->getEditable($config_name)->delete();
      }
    }

    // Deleting entity overrides.
    $this->setVariants(TRUE)->removeEntityInstanceSettings($entity_type_id);

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
   * @todo multiple variants
   */
  public function setBundleSettings($entity_type_id, $bundle_name = NULL, $settings = ['index' => TRUE]) {
    if (empty($variants = $this->getVariants(FALSE))) {
      return $this;
    }

    $bundle_name = NULL !== $bundle_name ? $bundle_name : $entity_type_id;

    if (!empty($old_settings = $this->getBundleSettings($entity_type_id, $bundle_name))) {
      $settings = array_merge($old_settings, $settings);
    }
    self::supplementDefaultSettings('entity', $settings);

    if ($settings != $old_settings) {

      // Save new bundle settings to configuration.
      $bundle_settings = $this->configFactory
        ->getEditable("simple_sitemap.bundle_settings.$variants[0].$entity_type_id.$bundle_name");
      foreach ($settings as $setting_key => $setting) {
        $bundle_settings->set($setting_key, $setting);
      }
      $bundle_settings->save();

      // Delete entity overrides which are identical to new bundle settings.
      $entity_ids = $this->entityHelper->getEntityInstanceIds($entity_type_id, $bundle_name);
      $query = $this->db->select('simple_sitemap_entity_overrides', 'o')
        ->fields('o', ['id', 'inclusion_settings'])
        ->condition('o.entity_type', $entity_type_id)
        ->condition('o.type', $variants[0]);
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
  public function getBundleSettings($entity_type_id = NULL, $bundle_name = NULL, $supplement_defaults = TRUE, $multiple_variants = FALSE) {

    $all_bundle_settings = [];

    foreach ($variants = $this->getVariants(FALSE) as $variant) {
      if (NULL !== $entity_type_id) {
        $bundle_name = NULL !== $bundle_name ? $bundle_name : $entity_type_id;

        $bundle_settings = $this->configFactory
          ->get("simple_sitemap.bundle_settings.$variant.$entity_type_id.$bundle_name")
          ->get();

        // If not found and entity type is enabled, return default bundle settings.
        if (empty($bundle_settings) && $supplement_defaults) {
          if ($this->entityTypeIsEnabled($entity_type_id)
            && isset($this->entityTypeBundleInfo->getBundleInfo($entity_type_id)[$bundle_name])) {
            self::supplementDefaultSettings('entity', $bundle_settings);
          }
          else {
            $bundle_settings = NULL;
          }
        }
      }
      else {
        $config_names = $this->configFactory->listAll("simple_sitemap.bundle_settings.$variant.");
        $bundle_settings = [];
        foreach ($config_names as $config_name) {
          $config_name_parts = explode('.', $config_name);
          $bundle_settings[$config_name_parts[3]][$config_name_parts[4]] = $this->configFactory->get($config_name)->get();
        }

        // Supplement default bundle settings for all bundles not found in simple_sitemap.bundle_settings.*.* configuration.
        if ($supplement_defaults) {
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
      }
      if ($multiple_variants) {
        if (!empty($bundle_settings)) {
          $all_bundle_settings[$variant] = $bundle_settings;
        }
      }
      else {
        return $bundle_settings;
      }
    }

    return $all_bundle_settings;
  }

  /**
   * @param string|null $entity_type_id
   * @param string|null $bundle_name
   * @return $this
   */
  public function removeBundleSettings($entity_type_id = NULL, $bundle_name = NULL) {
    if (empty($variants = $this->getVariants(FALSE))) {
      return $this;
    }

    if (NULL !== $entity_type_id) {
      $bundle_name = NULL !== $bundle_name ? $bundle_name : $entity_type_id;

      foreach ($variants as $variant) {
        $this->configFactory
          ->getEditable("simple_sitemap.bundle_settings.$variant.$entity_type_id.$bundle_name")->delete();
      }

      $this->removeEntityInstanceSettings($entity_type_id, (
        empty($ids)
          ? NULL
          : $this->entityHelper->getEntityInstanceIds($entity_type_id, $bundle_name)
      ));
    }
    else {
      foreach ($variants as $variant) {
        $config_names = $this->configFactory->listAll("simple_sitemap.bundle_settings.$variant.");
        foreach ($config_names as $config_name) {
          $this->configFactory->getEditable($config_name)->delete();
        }
        $this->removeEntityInstanceSettings();
      }
    }

    return $this;
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
    if (empty($variants = $this->getVariants(FALSE))) {
      return $this;
    }

    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);

    $all_bundle_settings = $this->getBundleSettings(
      $entity_type_id, $this->entityHelper->getEntityInstanceBundleName($entity), TRUE, TRUE
    );

    foreach ($all_bundle_settings as $variant => $bundle_settings) {
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
            ->keys([
              'type' => $variant,
              'entity_type' => $entity_type_id,
              'entity_id' => $id])
            ->fields([
              'type' => $variant,
              'entity_type' => $entity_type_id,
              'entity_id' => $id,
              'inclusion_settings' => serialize(array_merge($bundle_settings, $settings))])
            ->execute();
        }
        // Else unset override.
        else {
          $this->removeEntityInstanceSettings($entity_type_id, $id);
        }
      }
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
   *
   * @todo multiple variants
   */
  public function getEntityInstanceSettings($entity_type_id, $id) {
    if (empty($variants = $this->getVariants(FALSE))) {
      return FALSE;
    }

    $results = $this->db->select('simple_sitemap_entity_overrides', 'o')
      ->fields('o', ['inclusion_settings'])
      ->condition('o.type', $variants[0])
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
  public function removeEntityInstanceSettings($entity_type_id = NULL, $entity_ids = NULL) {
    if (empty($variants = $this->getVariants(FALSE))) {
      return $this;
    }

    $query = $this->db->delete('simple_sitemap_entity_overrides')
      ->condition('type', $variants, 'IN');

    if (NULL !== $entity_type_id) {
      $query->condition('entity_type', $entity_type_id);

      if (NULL !== $entity_ids) {
        $query->condition('entity_id', (array) $entity_ids, 'IN');
      }
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
    if (empty($variants = $this->getVariants(FALSE))) {
      return $this;
    }

    if (!(bool) $this->pathValidator->getUrlIfValidWithoutAccessCheck($path)) {
      // todo: log error.
      return $this;
    }
    if ($path[0] !== '/') {
      // todo: log error.
      return $this;
    }

    $variant_links = $this->getCustomLinks(NULL, FALSE, TRUE);
    foreach ($variants as $variant) {
      $links = [];
      $link_key = 0;
      if (isset($variant_links[$variant])) {
        $links = $variant_links[$variant];
        $link_key = count($links);
        foreach ($links as $key => $link) {
          if ($link['path'] === $path) {
            $link_key = $key;
            break;
          }
        }
      }

      $links[$link_key] = ['path' => $path] + $settings;
      $this->configFactory->getEditable("simple_sitemap.custom_links.$variant")
        ->set('links', $links)->save();
    }

    return $this;
  }

  /**
   * Returns an array of custom paths and their sitemap settings.
   *
   * @param bool $supplement_defaults
   * @return array
   */
  public function getCustomLinks($path = NULL, $supplement_defaults = TRUE, $multiple_variants = FALSE) {
    $all_custom_links = [];
    foreach ($variants = $this->getVariants(FALSE) as $variant) {
      $custom_links = $this->configFactory
        ->get("simple_sitemap.custom_links.$variant")
        ->get('links');

      $custom_links = !empty($custom_links) ? $custom_links : [];

      if (!empty($custom_links) && $path !== NULL) {
        foreach ($custom_links as $key => $link) {
          if ($link['path'] !== $path) {
            unset($custom_links[$key]);
          }
        }
      }

      if (!empty($custom_links) && $supplement_defaults) {
        foreach ($custom_links as $i => $link_settings) {
          self::supplementDefaultSettings('custom', $link_settings);
          $custom_links[$i] = $link_settings;
        }
      }

      $custom_links = $path !== NULL && !empty($custom_links)
        ? array_values($custom_links)[0]
        : array_values($custom_links);


      if (!empty($custom_links)) {
        if ($multiple_variants) {
          $all_custom_links[$variant] = $custom_links;
        }
        else {
          return $custom_links;
        }
      }
    }

    return $all_custom_links;
  }

  /**
   * Removes all custom paths from the sitemap settings.
   *
   * @return $this
   */
  public function removeCustomLinks($paths = NULL) {
    if (empty($variants = $this->getVariants(FALSE))) {
      return $this;
    }

    if (NULL === $paths) {
      foreach ($variants as $variant) {
        $this->configFactory
          ->getEditable("simple_sitemap.custom_links.$variant")->delete();
      }
    }
    else {
      $variant_links = $this->getCustomLinks(NULL, FALSE, TRUE);
      foreach ($variant_links as $variant => $links) {
        $custom_links = $links;
        $save = FALSE;
        foreach ((array) $paths  as $path) {
          foreach ($custom_links as $key => $link) {
            if ($link['path'] === $path) {
              unset($custom_links[$key]);
              $save = TRUE;
              break 2;
            }
          }
        }
        if ($save) {
          $this->configFactory->getEditable("simple_sitemap.custom_links.$variant")
            ->set('links', array_values($custom_links))->save();
        }
      }
    }

    return $this;
  }
}
