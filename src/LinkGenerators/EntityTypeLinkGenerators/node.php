<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\LinkGenerators\EntityTypeLinkGenerators\node.
 *
 * Plugin for node entity link generation.
 *
 * This can be used as a template to create new plugins for other entity types.
 * To create a plugin simply create a new class file in the
 * EntityTypeLinkGenerators folder. Name this file after the entity type (eg.
 * 'node' or 'taxonomy_term'.
 * This class needs to extend the EntityLinkGenerator class and include
 * the get_entity_bundle_links() method. - as shown here. This method has to
 * return an array of pure urls to the entities of the entity type in question.
 */

namespace Drupal\simplesitemap\LinkGenerators\EntityTypeLinkGenerators;

use Drupal\simplesitemap\LinkGenerators\EntityLinkGenerator;
use Drupal\Core\Url;

/**
 * node class.
 */
class node extends EntityLinkGenerator {

  function get_entity_bundle_links($entity_type, $bundle, $language) {
    $results = db_query("SELECT nid FROM {node_field_data} WHERE status = 1 AND type = :type", array(':type' => $bundle))
      ->fetchAllAssoc('nid');

    $urls = array();
    foreach ($results as $id => $changed) {

      $urls[$id] = Url::fromRoute("entity.$entity_type.canonical", array('node' => $id), array(
        'language' => $language,
        'absolute' => TRUE
      ))->toString();
    }
    return $urls;
  }
}
