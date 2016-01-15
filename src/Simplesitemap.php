<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\Simplesitemap.
 */

namespace Drupal\simplesitemap;

/**
 * Simplesitemap class.
 */
class Simplesitemap {

  const SITEMAP_PLUGIN_PATH = 'src/LinkGenerators/EntityTypeLinkGenerators';

  private $config;
  private $sitemap;

  function __construct() {
    $this->initialize();
  }

  public static function get_form_entity($form_state) {
    if (!is_null($form_state->getFormObject())
      && method_exists($form_state->getFormObject(), 'getEntity')) {
      $entity = $form_state->getFormObject()->getEntity();
      return $entity;
    }
    return FALSE;
  }

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
    //todo: update for chunked sitemaps
    $result = db_query("SELECT id, sitemap_string FROM {simplesitemap}")->fetchAllAssoc('id');
    foreach ($result as $sitemap_id => $sitemap) {
      $this->sitemap[$sitemap_id] = $sitemap->sitemap_string;
    }
  }

  // Get sitemap settings from configuration storage.
  private function get_config_from_db() {
    $this->config = \Drupal::config('simplesitemap.settings');
  }

  public function save_entity_types($entity_types) {
    $this->save_config('entity_types', $entity_types);
  }

  public function save_custom_links($custom_links) {
    $this->save_config('custom', $custom_links);
  }

  public function save_settings($settings) {
    $this->save_config('settings', $settings);
  }

  private function save_config($key, $value) {
    \Drupal::service('config.factory')->getEditable('simplesitemap.settings')
      ->set($key, $value)->save();
    $this->initialize();
  }

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
        return $this->sitemap[0];
      }
    }

    // Return specific sitemap chunk.
    else {
      return $this->sitemap[$sitemap_id];
    }
  }

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

  private function save_sitemap() {

    db_truncate('simplesitemap')->execute();
    $values = array();
    foreach($this->sitemap as $sitemap_id => $sitemap_string) {
      $values[] = array(
        'id' => $sitemap_id,
        'sitemap_string' => $sitemap_string,
        //todo: add 'changed' info in new column?
      );
    }

    $query = db_insert('simplesitemap')->fields(array('id', 'sitemap_string'));
    foreach ($values as $record) {
      $query->values($record);
    }
    $query->execute();
  }

  private function get_sitemap_index() {
    $generator = new SitemapGenerator();
    return $generator->generate_sitemap_index($this->sitemap);
  }

  public function get_entity_types() {
    return $this->config->get('entity_types');
  }

  public function get_custom_links() {
    return $this->config->get('custom');
  }

  public function get_settings() {
    return $this->config->get('settings');
  }
}
