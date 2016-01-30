<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\LinkGeneratorBase.
 */

namespace Drupal\simplesitemap;

use Drupal\Component\Plugin\PluginBase;

abstract class LinkGeneratorBase extends PluginBase implements LinkGeneratorInterface {

  private $entity_paths = array();

  /**
   * {@inheritdoc}
   */
  public function get_entity_paths($entity_type, $bundles) {
    $i = 0;
    foreach($bundles as $bundle => $bundle_settings) {
      if (!$bundle_settings['index']) {
        continue;
      }
      $paths = $this->get_entity_bundle_paths($bundle);
      foreach ($paths as $id => $link) {
        $this->entity_paths[$i]['path'] = $link;
        $this->entity_paths[$i]['priority'] = $bundle_settings['priority'];
        $this->entity_paths[$i]['lastmod'] = $this->get_lastmod($entity_type, $id);
        $i++;
      }
    }
    return $this->entity_paths;
  }

  /**
   * Gets lastmod date for an entity.
   *
   * @param string $entity_type
   *  E.g. 'node_type', 'taxonomy_vocabulary'.
   * @param int $id
   *  ID of the entity.
   *
   * @return string
   *  Lastmod date or NULL if none.
   */
  private function get_lastmod($entity_type, $id) {
    switch ($entity_type) {
      case 'node_type':
        $lastmod = db_query("SELECT changed FROM {node_field_data} WHERE nid = :nid LIMIT 1", array(':nid' => $id))->fetchCol();
        break;
      case 'taxonomy_vocabulary':
        $lastmod = db_query("SELECT changed FROM {taxonomy_term_field_data} WHERE tid = :tid LIMIT 1", array(':tid' => $id))->fetchCol();
        break;
      case 'menu':
        //todo: to be implemented
    }
    return isset($lastmod[0]) ? date_iso8601($lastmod[0]) : NULL;
  }

  /**
   * Returns an array of all urls to this bundle.
   *
   * @param string $bundle
   *  Machine name of the bundle, eg. 'page'.
   *
   * @return array $paths
   *  A numeric array of Drupal internal paths like node/1/edit or user/1
   *
   * @abstract
   */
  abstract function get_entity_bundle_paths($bundle);
}
