<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\LinkGenerators\CustomLinkGenerator.
 *
 * Generates custom sitemap links provided by the user.
 */

namespace Drupal\simplesitemap\LinkGenerators;

use Drupal\Core\Url;
use Drupal\simplesitemap\SitemapGenerator;

/**
 * CustomLinkGenerator class.
 */
class CustomLinkGenerator {

  public function get_custom_links($custom_paths, $language) {
    $links = array();
    foreach($custom_paths as $custom_path) {
      if (!isset($custom_path['index']) || $custom_path['index']) {
        $links[] = SitemapGenerator::add_xml_link_markup(Url::fromUserInput($custom_path['path'], array(
          'language' => $language,
          'absolute' => TRUE
        ))->toString(), isset($custom_path['priority']) ? $custom_path['priority'] : SitemapGenerator::PRIORITY_DEFAULT);
      }
    }
    return $links;
  }
}
