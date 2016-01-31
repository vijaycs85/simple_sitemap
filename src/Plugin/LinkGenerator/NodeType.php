<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\Plugin\LinkGenerator\NodeType.
 *
 * Plugin for node entity link generation.
 */

namespace Drupal\simplesitemap\Plugin\LinkGenerator;

use Drupal\simplesitemap\Annotation\LinkGenerator;
use Drupal\simplesitemap\LinkGeneratorBase;
use Drupal\Core\Url;

/**
 * NodeType class.
 *
 * @LinkGenerator(
 *   id = "node_type"
 * )
 */
class NodeType extends LinkGeneratorBase {

  /**
   * {@inheritdoc}
   */
  function get_entity_bundle_paths($bundle) {
    $results = db_query("SELECT nid, changed FROM {node_field_data} WHERE status = 1 AND type = :type", array(':type' => $bundle))
      ->fetchAllAssoc('nid');

    $paths = array();
    foreach ($results as $id => $data) {
      $paths[$id]['path'] = Url::fromRoute("entity.node.canonical", array('node' => $id), array())->getInternalPath();
      $paths[$id]['lastmod'] = $data->changed;
    }
    return $paths;
  }
}
