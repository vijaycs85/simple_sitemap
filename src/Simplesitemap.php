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

  private $config;
  private $sitemap;
  private $lang;
  private $priority_default = 0.5;

  function __construct() {
    $this->set_current_lang();
    $this->set_config();
  }

  private function set_current_lang() {
    $this->lang = \Drupal::languageManager()->getCurrentLanguage();
  }

  private function set_config() {
    $this->config = \Drupal::config('simplesitemap.settings');
    $this->sitemap = $this->config->get('sitemap_' . $this->lang->getId());
  }

  private function save_sitemap() {
    \Drupal::service('config.factory')->getEditable('simplesitemap.settings')->set('sitemap_' . $this->lang->getId(), $this->sitemap)->save();
    $this->set_config();
  }

  private function add_xml_link_markup($url, $priority) {
    return "<url><loc>" . $url . "</loc><priority>" . $priority . "</priority></url>";
  }

  private function generate_sitemap() {

    $output = '';
    $config = $this->config;

    // Add custom links according to config file.
    $custom = $config->get('custom');
    foreach ($custom as $page) {
      if ($page['index']) {
        $output .= $this->add_xml_link_markup(Url::fromUserInput($page['path'], array('language' => $this->lang, 'absolute' => TRUE))->toString(), $page['priority']);
      }
    }

    // Add node links according to content type settings.
    $content_types = $config->get('content_types');
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
        $output .= $this->add_xml_link_markup(Url::fromRoute('entity.node.canonical', array('node' => $nid), array('language' => $this->lang, 'absolute' => TRUE))->toString(), $content_types[$node->type]['priority']);
      }
    }

    // Add sitemap markup.
    $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">" . $output . "</urlset>";

    $this->sitemap = $output;
    $this->save_sitemap();
  }

  public function set_content_types($content_types) {
    \Drupal::service('config.factory')->getEditable('simplesitemap.settings')->set('content_types', $content_types)->save();
    $this->set_config();
  }

  public function get_content_types() {
    return $this->config->get('content_types');
  }

  public function get_sitemap() {
    if (empty($this->sitemap)) {
      $this->generate_sitemap();
    }
    return $this->sitemap;
  }

  public function generate_all_sitemaps() {
    foreach(\Drupal::languageManager()->getLanguages() as $language) {
      $this->lang = $language;
      $this->generate_sitemap();
    }
  }

  public function get_priority_select_values() {
    foreach(range(0, 10) as $value) {
      $value = $value / 10;
      $options[(string)$value] = (string)$value;
    }
    return $options;
  }

  public function get_priority_default() {
    return $this->priority_default;
  }
}
