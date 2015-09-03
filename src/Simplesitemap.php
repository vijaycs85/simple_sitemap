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
    $config = \Drupal::config('simplesitemap.settings');
    $this->sitemap = $config->get('sitemap_' . $this->lang->getId());
    $this->content_types = $config->get('content_types');
    $this->custom = $config->get('custom');
  }

  private function save_sitemap() {
    $this->save_config('sitemap_' . $this->lang->getId(), $this->sitemap);
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
    foreach ($this->custom as $page) {
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
