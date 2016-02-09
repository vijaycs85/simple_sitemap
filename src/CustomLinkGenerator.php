<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\LinkGenerators\CustomLinkGenerator.
 *
 * Generates custom sitemap paths provided by the user.
 */

namespace Drupal\simplesitemap;

use Drupal\Core\Url;
use \Drupal\user\Entity\User;

/**
 * CustomLinkGenerator class.
 */
class CustomLinkGenerator {
  const ANONYMOUS_USER_ID = 0;

  /**
   * Returns an array of all urls of the custom paths.
   *
   * @param array $custom_paths
   *
   * @return array $urls
   *
   */
  public function get_custom_paths($custom_paths) {
    $anonymous_account = User::load(self::ANONYMOUS_USER_ID);
    $paths = array();
    foreach($custom_paths as $i => $custom_path) {
      if (!isset($custom_path['index']) || $custom_path['index']) {
        $url_object = Url::fromUserInput($custom_path['path'], array());
        if ($url_object->access($anonymous_account)) {
          $paths[$i]['path'] = $url_object->getInternalPath();
          $paths[$i]['options'] = $url_object->getOptions();
          $paths[$i]['priority'] = isset($custom_path['priority']) ? $custom_path['priority'] : NULL;
          $paths[$i]['lastmod'] = NULL; //todo: implement lastmod
        }
      }
    }
    return $paths;
  }
}
