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
  function get_entity_bundle_paths($bundle) {
    $routes = db_query("SELECT mlid, route_name, route_parameters FROM {menu_tree} WHERE menu_name = :menu_name and enabled = 1", array(':menu_name' => $bundle))
      ->fetchAllAssoc('mlid');

    $paths = array();
    foreach ($routes as $id => $entity) {
      if (empty($entity->route_name))
        continue;

      //todo: Use Url::getRouteParameters()?
      $options = !empty($route_parameters = unserialize($entity->route_parameters))
        ? array(key($route_parameters) => $route_parameters[key($route_parameters)]) : array();

      $paths[$id]['path'] = Url::fromRoute($entity->route_name, $options, array())->getInternalPath();
      //todo: Implement lastmod for menu items.
    }
    return $paths;
  }
}
