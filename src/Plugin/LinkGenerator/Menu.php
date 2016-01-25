<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\LinkGenerator\Menu.
 *
 * Plugin for menu entity link generation.
 */

namespace Drupal\simplesitemap\Plugin\LinkGenerator;

use Drupal\simplesitemap\Annotation\LinkGenerator;
use Drupal\simplesitemap\LinkGeneratorBase;
use Drupal\Core\Url;

/**
 * Menu class.
 *
 * @LinkGenerator(
 *   id = "menu"
 * )
 */
class Menu extends LinkGeneratorBase {

  /**
   * {@inheritdoc}
   */
  function get_entity_bundle_links($bundle, $languages) {
    $routes = db_query("SELECT mlid, route_name, route_parameters FROM {menu_tree} WHERE menu_name = :menu_name and enabled = 1", array(':menu_name' => $bundle))
      ->fetchAllAssoc('mlid');

    $urls = array();
    foreach ($routes as $id => $entity) {
      if (empty($entity->route_name))
        continue;
      $options = !empty($route_parameters = unserialize($entity->route_parameters)) ? array(key($route_parameters) => $route_parameters[key($route_parameters)]) : array();
      foreach($languages as $language) {
        $urls[$id][$language->getId()] = Url::fromRoute($entity->route_name, $options, array(
          'language' => $language,
          'absolute' => TRUE
        ))->toString();
      }
    }
    return $urls;
  }
}
