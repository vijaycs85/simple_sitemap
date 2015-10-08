<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\Simplesitemap.
 */

namespace Drupal\simplesitemap;

use Drupal\Core\Url;

/**
 * Simplesitemap class.
 */
class Simplesitemap {

  private $sitemap;
  private $content_types;
  private $custom;
  private $lang;

  function __construct() {
    $this->set_current_lang();
    $this->set_config();
  }

  private function set_current_lang($language = NULL) {
    $this->lang = is_null($language) ? \Drupal::languageManager()->getCurrentLanguage() : $language;
  }

  private function set_config() {
    $this->get_config_from_db();
    $this->get_sitemap_from_db();
  }

  // Get sitemap from database.
  private function get_sitemap_from_db() {
    $result = db_select('simplesitemap', 's')
      ->fields('s', array('sitemap_string'))
      ->condition('language_code', $this->lang->getId())
      ->execute()->fetchAll();
    $this->sitemap = !empty($result[0]->sitemap_string) ? $result[0]->sitemap_string : NULL;
  }

  // Get sitemap settings from configuration storage.
  private function get_config_from_db() {
    $config = \Drupal::config('simplesitemap.settings');
    $this->content_types = $config->get('content_types');
    $this->custom = $config->get('custom');
  }

  private function save_sitemap() {

    //todo: db_merge not working in D8(?), this is why the following queries are needed:
//    db_merge('simplesitemap')
//      ->key(array('language_code', $this->lang->getId()))
//      ->fields(array(
//        'language_code' => $this->lang->getId(),
//        'sitemap_string' => $this->sitemap,
//      ))
//      ->execute();
    $exists_query = db_select('simplesitemap')
      ->condition('language_code', $this->lang->getId())
      ->countQuery()->execute()->fetchField();

    if ($exists_query > 0) {
      db_update('simplesitemap')
        ->fields(array(
          'sitemap_string' => $this->sitemap,
        ))
        ->condition('language_code', $this->lang->getId())
        ->execute();
    }
    else {
      db_insert('simplesitemap')
        ->fields(array(
          'language_code' => $this->lang->getId(),
          'sitemap_string' => $this->sitemap,
        ))
        ->execute();
    }
  }

  public function save_content_types($content_types) {
    $this->save_config('content_types', $content_types);
  }

  private function save_config($key, $value) {
    \Drupal::service('config.factory')->getEditable('simplesitemap.settings')->set($key, $value)->save();
    $this->set_config();
  }

  private function add_xml_link_markup($url, $priority) {
    return "<url><loc>" . $url . "</loc><priority>" . $priority . "</priority></url>";
  }

  private function generate_sitemap() {

    $output = '';

    // Add custom links according to config file.
    $custom = $this->custom;
    foreach ($custom as $page) {
      if ($page['index']) {
        $output .= $this->add_xml_link_markup(Url::fromUserInput($page['path'], array(
          'language' => $this->lang,
          'absolute' => TRUE
        ))->toString(), $page['priority']);
      }
    }

    // Add node links according to content type settings.
    $content_types = $this->content_types;
    if (count($content_types) > 0) {

      //todo: D8 entityQuery doesn't seem to take multiple OR conditions, that's why that ugly db_select.
//        $query = \Drupal::entityQuery('node')
//          ->condition('status', 1)
//          ->condition('type', array_keys($content_types));
//        $nids = $query->execute();
      $query = db_select('node_field_data', 'n')
        ->fields('n', array('nid', 'type'))
        ->condition('status', 1);
      $db_or = db_or();
      foreach ($content_types as $machine_name => $options) {
        if ($options['index']) {
          $db_or->condition('type', $machine_name);
        }
      }
      $query->condition($db_or);
      $nids = $query->execute()->fetchAllAssoc('nid');

      foreach ($nids as $nid => $node) {
        $output .= $this->add_xml_link_markup(Url::fromRoute('entity.node.canonical', array('node' => $nid), array(
          'language' => $this->lang,
          'absolute' => TRUE
        ))->toString(), $content_types[$node->type]['priority']);
      }
    }

    // Add sitemap markup.
    $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">" . $output . "</urlset>";

    $this->sitemap = $output;
    $this->save_sitemap();
  }

  public function get_content_types() {
    return $this->content_types;
  }

  public function get_sitemap() {
    if (empty($this->sitemap)) {
      $this->generate_sitemap();
    }
    return $this->sitemap;
  }

  public function generate_all_sitemaps() {
    foreach(\Drupal::languageManager()->getLanguages() as $language) {
      $this->set_current_lang($language);
      $this->generate_sitemap();
    }
    //todo: Delete sitemaps the languages of which have been disabled/removed.
  }

  public static function get_priority_select_values() {
    foreach(range(0, 10) as $value) {
      $value = $value / 10;
      $options[(string)$value] = (string)$value;
    }
    return $options;
  }

  public static function get_priority_default() {
    return $priority_default = 0.5;
  }
}
