<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\LinkGenerators\CustomLinkGenerator.
 *
 * Generates custom sitemap paths provided by the user.
 */

namespace Drupal\simplesitemap;

/**
 * CustomLinkGenerator class.
 */
class CustomLinkGenerator {

  /**
   * Returns an array of all urls of the custom paths.
   *
   * @param array $custom_paths
   *
   * @return array $urls
   *
   */
  public function get_custom_paths($custom_paths) {
    $paths = array();
    foreach($custom_paths as $i => $custom_path) {
      if (!isset($custom_path['index']) || $custom_path['index']) {
        $paths[$i]['path'] = substr($custom_path['path'], 1);
        $paths[$i]['priority'] = isset($custom_path['priority']) ? $custom_path['priority'] : NULL;
        $paths[$i]['lastmod'] = NULL; //todo: implement lastmod
        //todo: get url parameters and page fragment into $paths[$i]['settings'] so that future hook implementations can alter the array instead of having to do string processing.
      }
    }
    return $paths;
  }
}
