<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\Simplesitemap.
 */

namespace Drupal\simplesitemap;

/**
 * Simplesitemap class.
 *
 * Main module class.
 */
class Simplesitemap {

  const SITEMAP_PLUGIN_PATH = 'src/LinkGenerators/EntityTypeLinkGenerators';

  private $config;
  private $sitemap;

  function __construct() {
    $this->initialize();
  }

  /**
   * Returns an the form entity object.
   *
   * @param object $form_state
   * @return object $entity or FALSE if non-existent.
   */
  public static function get_form_entity($form_state) {
    if (!is_null($form_state->getFormObject())
      && method_exists($form_state->getFormObject(), 'getEntity')) {
      $entity = $form_state->getFormObject()->getEntity();
      return $entity;
    }
    return FALSE;
  }

  /**
   * Returns path of a sitemap plugin.
   *
   * @param string $entity_type_name
   * @return string $class_path or FALSE if non-existent.
   */
  public static function get_plugin_path($entity_type_name) {
    $class_path = drupal_get_path('module', 'simplesitemap')
      . '/' . self::SITEMAP_PLUGIN_PATH . '/' . $entity_type_name . '.php';
    if (file_exists($class_path)) {
      return $class_path;
    }
    return FALSE;
  }

  private function initialize() {
    $this->get_config_from_db();
    $this->get_sitemap_from_db();
  }

  // Get sitemap from database.
  private function get_sitemap_from_db() {
    $this->sitemap = db_query("SELECT * FROM {simplesitemap}")->fetchAllAssoc('id');
  }
  /**
   * Gets sitemap settings from configuration storage.
   */
  private function get_config_from_db() {
    $this->config = \Drupal::config('simplesitemap.settings');
  }

  /**
   * Saves entity type sitemap settings to db.
   *
   * @param array $entity_types
   */
  public function save_entity_types($entity_types) {
    $this->save_config('entity_types', $entity_types);
  }

  /**
   * Saves the sitemap custom links settings to db.
   *
   * @param array $custom_links
   */
  public function save_custom_links($custom_links) {
    $this->save_config('custom', $custom_links);
  }

  /**
   * Saves other sitemap settings to db.
   *
   * @param array $settings
   */
  public function save_settings($settings) {
    $this->save_config('settings', $settings);
  }

  private function save_config($key, $value) {
    \Drupal::service('config.factory')->getEditable('simplesitemap.settings')
      ->set($key, $value)->save();
    $this->initialize();
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
  public function get_sitemap($sitemap_id = NULL) {
    if (empty($this->sitemap)) {
      $this->generate_sitemap();
    }

    if (is_null($sitemap_id) || !isset($this->sitemap[$sitemap_id])) {

      // Return sitemap index, if there are multiple sitemap chunks.
      if (count($this->sitemap) > 1) {
        return $this->get_sitemap_index();
      }

      // Return sitemap if there is only one chunk.
      else {
        return $this->sitemap[0]->sitemap_string;
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
  public function generate_sitemap() {
    $generator = new SitemapGenerator();
    $generator->set_custom_links($this->config->get('custom'));
    $generator->set_entity_types($this->config->get('entity_types'));
    $settings = $this->get_settings();
    $this->sitemap = $generator->generate_sitemap($settings['max_links']);
    $this->save_sitemap();
    drupal_set_message(t("The <a href='@url' target='_blank'>XML sitemap</a> has been regenerated for all languages.",
      array('@url' => $GLOBALS['base_url'] . '/sitemap.xml')));
  }

  /**
   * Saves the sitemap to the db.
   */
  private function save_sitemap() {
    db_truncate('simplesitemap')->execute();
    $values = array();
    foreach($this->sitemap as $chunk_id => $chunk_data) {
      $values[] = array(
        'id' => $chunk_id,
        'sitemap_string' => $chunk_data->sitemap_string,
        'generated' =>  $chunk_data->generated,
      );
    }

    $query = db_insert('simplesitemap')
      ->fields(array('id', 'sitemap_string', 'generated'));
    foreach ($values as $record) {
      $query->values($record);
    }
    $query->execute();
  }

  /**
   * Gets the sitemap index.
   */
  private function get_sitemap_index() {
    $generator = new SitemapGenerator();
    return $generator->generate_sitemap_index($this->sitemap);
  }

  /**
   * Gets the sitemap entity type settings.
   */
  public function get_entity_types() {
    return $this->config->get('entity_types');
  }

  /**
   * Gets the sitemap custom links settings.
   */
  public function get_custom_links() {
    return $this->config->get('custom');
  }

  /**
   * Gets other sitemap settings.
   */
  public function get_settings() {
    return $this->config->get('settings');
  }
}
