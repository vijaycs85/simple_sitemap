<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\LinkGenerators\EntityTypeLinkGenerators\menu.
 *
 * Plugin for menu entity link generation.
 * See \Drupal\simplesitemap\LinkGenerators\CustomLinkGenerator\node for more
 * documentation.
 */

namespace Drupal\simplesitemap\LinkGenerators\EntityTypeLinkGenerators;

use Drupal\simplesitemap\LinkGenerators\EntityLinkGenerator;
use Drupal\Core\Url;

/**
 * menu class.
 */
class menu extends EntityLinkGenerator {

  function get_entity_bundle_links($entity_type, $bundle, $language) {
    $routes = db_query("SELECT mlid, route_name, route_parameters FROM {menu_tree} WHERE menu_name = :menu_name and enabled = 1", array(':menu_name' => $bundle))
      ->fetchAllAssoc('mlid');

    $urls = array();
    foreach ($routes as $id => $entity) {
      if (empty($entity->route_name))
        continue;
      if (!empty($route_parameters = unserialize($entity->route_parameters))) {
        $key = key($route_parameters);
        $urls[] = Url::fromRoute($entity->route_name, array($key => $route_parameters[$key]), array(
          'language' => $language,
          'absolute' => TRUE
        ))->toString();
      }
      else {
        $urls[] = Url::fromRoute($entity->route_name, array(), array(
          'language' => $language,
          'absolute' => TRUE
        ))->toString();
      }
    }
    return $urls;
  }
}
