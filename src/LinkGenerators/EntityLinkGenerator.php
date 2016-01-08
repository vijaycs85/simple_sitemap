<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\LinkGenerators\EntityLinkGenerator.
 *
 * Abstract class to be extended for plugin creation.
 * See \Drupal\simplesitemap\LinkGenerators\CustomLinkGenerator\node for more
 * documentation.
 */

namespace Drupal\simplesitemap\LinkGenerators;
use Drupal\simplesitemap\SitemapGenerator;

/**
 * EntityLinkGenerator abstract class.
 */
abstract class EntityLinkGenerator {

  private $entity_links = array();

  public function get_entity_links($entity_type, $bundles, $language) {
    foreach($bundles as $bundle => $bundle_settings) {
      if (!$bundle_settings['index']) {
        continue;
      }
      $links = $this->get_entity_bundle_links($entity_type, $bundle, $language);
      $lastmod = NULL;
      foreach ($links as $id => &$link) {
        switch ($entity_type) {
          case 'node':
            $lastmod = db_query("SELECT changed FROM {node_field_data} WHERE nid = :nid LIMIT 1", array(':nid' => $id))->fetchCol();
            break;
          case 'taxonomy_term':
            $lastmod = db_query("SELECT changed FROM {taxonomy_term_field_data} WHERE tid = :tid LIMIT 1", array(':tid' => $id))->fetchCol();
            break;
          case 'menu':
            //todo: to be implemented
        }
        $this->entity_links[] = SitemapGenerator::add_xml_link_markup($link, $bundle_settings['priority'], isset($lastmod[0]) ? date_iso8601($lastmod[0]) : NULL);
      }
    }
    return $this->entity_links;
  }

  abstract function get_entity_bundle_links($entity_type, $bundle, $language);
}
