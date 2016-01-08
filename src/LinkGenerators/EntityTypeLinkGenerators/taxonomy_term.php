<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\LinkGenerators\EntityTypeLinkGenerators\taxonomy_term.
 *
 * Plugin for taxonomy term entity link generation.
 * See \Drupal\simplesitemap\LinkGenerators\CustomLinkGenerator\node for more
 * documentation.
 */

namespace Drupal\simplesitemap\LinkGenerators\EntityTypeLinkGenerators;

use Drupal\simplesitemap\LinkGenerators\EntityLinkGenerator;
use Drupal\Core\Url;

/**
 * taxonomy_term class.
 */
class taxonomy_term extends EntityLinkGenerator {

  function get_entity_bundle_links($entity_type, $bundle, $language) {

    $results = db_query("SELECT tid FROM {taxonomy_term_field_data} WHERE vid = :vid", array(':vid' => $bundle))
      ->fetchAllAssoc('tid');

    $urls = array();
    foreach ($results as $id => $changed) {
      $urls[$id] = Url::fromRoute("entity.$entity_type.canonical", array('taxonomy_term' => $id), array(
        'language' => $language,
        'absolute' => TRUE
      ))->toString();
    }
    return $urls;
  }
}
