<?php
/**
 * @file
 * Contains \Drupal\simple_sitemap\Simplesitemap.
 */

namespace Drupal\simple_sitemap;
use Drupal\Core\Config\ConfigFactoryInterface;

use Drupal\Core\Cache\Cache;

/**
 * Simplesitemap class.
 *
 * Main module class.
 */
class Simplesitemap {

  private $config;
  private $sitemap;

  /**
   * Simplesitemap constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory from the container.
   */
  function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('simple_sitemap.settings');
    $this->sitemap = db_query("SELECT * FROM {simple_sitemap}")->fetchAllAssoc('id');
  }

  /**
   * Gets the entity_type_id and bundle_name of the form object if available and only
   * if the sitemap supports this entity type through an existing plugin.
   *
   * @param object $form_state
   * @param string $form_id
   *
   * @return array containing the entity_type_id and the bundle_name of the
   *  form object or FALSE if none found or not supported by an existing plugin.
   */
  public static function getSitemapFormEntityData($form_state, $form_id) {

    // Get all simple_sitemap plugins.
    $manager = \Drupal::service('plugin.manager.simple_sitemap');
    $plugins = $manager->getDefinitions();

    // Go through simple_sitemap plugins and check if one of them declares usage
    // of this particular form. If that's the case, get the entity type id of the
    // plugin definition and assume the bundle to be of the same name as the
    // entity type id.
    foreach($plugins as $plugin) {
      if (isset($plugin['form_id']) && $plugin['form_id'] === $form_id) {
        return array(
          'entity_type_id' => $plugin['id'],
          'bundle_name' => $plugin['id'],
          'entity_id' => NULL,
        );
      }
    }

    $form_entity = self::getFormEntity($form_state);
    if ($form_entity !== FALSE) {
      $entity_type = $form_entity->getEntityType();

      // If this entity is of a bundle, this will be an entity add/edit page.
      // If a simple_sitemap plugin of this entity_type exists, return the
      // entity type ID, the bundle name and ethe entity ID.
      if (!empty($entity_type->getBundleEntityType())) {
        $bundle_entity_type = $entity_type->getBundleEntityType();
        if (isset($plugins[$bundle_entity_type])) {
          return array(
            'entity_type_id' => $bundle_entity_type,
            'bundle_name' => $form_entity->bundle(),
            'entity_id' => $form_entity->Id(),
          );
        }
      }

      // Else if this entity has an entity type ID, it means it is a bundle
      // configuration form. If a simple_sitemap plugin of this entity_type
      // exists, return the entity type ID, the bundle name and ethe entity ID.
      else {
        $entity_type_id = $form_entity->getEntityTypeId();
        if (isset($plugins[$entity_type_id])) {
          if (!isset($plugins[$entity_type_id]['form_id'])
            || $plugins[$entity_type_id]['form_id'] === $form_id) {
            return array(
              'entity_type_id' => $entity_type_id,
              'bundle_name' => $form_entity->Id(),
              'entity_id' => NULL,
            );
          }
        }
      }
    }

    // If all methods of getting simple_sitemap entity data for this form
    // failed, return FALSE.
    return FALSE;
  }

  /**
   * Gets the object entity of the form if available.
   *
   * @param object $form_state
   *
   * @return object $entity or FALSE if non-existent or if form operation is
   *  'delete'.
   */
  private static function getFormEntity($form_state) {
    $form_object = $form_state->getFormObject();
    if (!is_null($form_object)
      && method_exists($form_state->getFormObject(), 'getEntity')
      && $form_object->getOperation() !== 'delete') {
      $entity = $form_state->getFormObject()->getEntity();
      return $entity;
    }
    return FALSE;
  }

  /**
   * Gets a specific sitemap configuration from the configuration storage.
   *
   * @param string $key
   *  Configuration key, like 'entity_links'.
   * @return mixed
   *  The requested configuration.
   */
  public function getConfig($key) {
    return $this->config->get($key);
  }

  /**
   * Saves a specific sitemap configuration to db.
   *
   * @param string $key
   *  Configuration key, like 'entity_links'.
   * @param mixed $value
   *  The configuration to be saved.
   */
  public function saveConfig($key, $value) {
    \Drupal::service('config.factory')->getEditable('simple_sitemap.settings')
      ->set($key, $value)->save();
  }

  /**
   * Returns the whole sitemap, a requested sitemap chunk,
   * or the sitemap index file.
   *
   * @param int $sitemap_id
   *
   * @return string $sitemap
   *  If no sitemap id provided, either a sitemap index is returned, or the
   *  whole sitemap, if the amount of links does not exceed the max links setting.
   *  If a sitemap id is provided, a sitemap chunk is returned.
   */
  public function getSitemap($sitemap_id = NULL) {
    if (is_null($sitemap_id) || !isset($this->sitemap[$sitemap_id])) {

      // Return sitemap index, if there are multiple sitemap chunks.
      if (count($this->sitemap) > 1) {
        return $this->getSitemapIndex();
      }

      // Return sitemap if there is only one chunk.
      else {
        if (isset($this->sitemap[1])) {
          return $this->sitemap[1]->sitemap_string;
        }
        return FALSE;
      }
    }
    // Return specific sitemap chunk.
    else {
      return $this->sitemap[$sitemap_id]->sitemap_string;
    }
  }

  /**
   * Generates the sitemap for all languages and saves it to the db.
   */
  public function generateSitemap($from = 'form') {
    Cache::invalidateTags(array('simple_sitemap'));
    db_truncate('simple_sitemap')->execute();
    $generator = new SitemapGenerator($from);
    $generator->setCustomLinks($this->getConfig('custom'));
    $generator->setEntityTypes($this->getConfig('entity_types'));
    $generator->startBatch();
  }

  /**
   * Generates and returns the sitemap index as string.
   *
   * @return string
   *  The sitemap index.
   */
  private function getSitemapIndex() {
    $generator = new SitemapGenerator();
    return $generator->generateSitemapIndex($this->sitemap);
  }

  /**
   * Gets a specific sitemap setting.
   *
   * @param string $name
   *  Name of the setting, like 'max_links'.
   *
   * @return mixed
   *  The current setting from db or FALSE if setting does not exist.
   */
  public function getSetting($name) {
    $settings = $this->getConfig('settings');
    return isset($settings[$name]) ? $settings[$name] : FALSE;
  }

  /**
   * Saves a specific sitemap setting to db.
   *
   * @param $name
   *  Setting name, like 'max_links'.
   * @param $setting
   *  The setting to be saved.
   */
  public function saveSetting($name, $setting) {
    $settings = $this->getConfig('settings');
    $settings[$name] = $setting;
    $this->saveConfig('settings', $settings);
  }

  /**
   * Returns a 'time ago' string of last timestamp generation.
   *
   * @return mixed
   *  Formatted timestamp of last sitemap generation, otherwise FALSE.
   */
  public function getGeneratedAgo() {
    if (isset($this->sitemap[1]->sitemap_created)) {
      return \Drupal::service('date.formatter')
        ->formatInterval(REQUEST_TIME - $this->sitemap[1]->sitemap_created);
    }
    return FALSE;
  }

  public static function getDefaultLangId() {
    return \Drupal::languageManager()->getDefaultLanguage()->getId();
  }
}
