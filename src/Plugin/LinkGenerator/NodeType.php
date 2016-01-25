<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\Plugin\LinkGenerator\NodeType.
 *
 * Plugin for taxonomy term entity link generation.
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
  function get_entity_bundle_links($bundle, $languages) {
    $results = db_query("SELECT nid FROM {node_field_data} WHERE status = 1 AND type = :type", array(':type' => $bundle))
      ->fetchAllAssoc('nid');

    $urls = array();
    foreach ($results as $id => $changed) {
      foreach($languages as $language) {
        $urls[$id][$language->getId()] = Url::fromRoute("entity.node.canonical", array('node' => $id), array(
          'language' => $language,
          'absolute' => TRUE
        ))->toString();
      }
    }
    return $urls;
  }
}
