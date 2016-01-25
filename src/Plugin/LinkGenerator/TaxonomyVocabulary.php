<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\LinkGenerator\TaxonomyVocabulary.
 *
 * Plugin for taxonomy term entity link generation.
 */

namespace Drupal\simplesitemap\Plugin\LinkGenerator;

use Drupal\simplesitemap\Annotation\LinkGenerator;
use Drupal\simplesitemap\LinkGeneratorBase;
use Drupal\Core\Url;

/**
 * TaxonomyVocabulary class.
 *
 * @LinkGenerator(
 *   id = "taxonomy_vocabulary"
 * )
 */
class TaxonomyVocabulary extends LinkGeneratorBase {

  /**
   * {@inheritdoc}
   */
  function get_entity_bundle_links($bundle, $languages) {
    $results = db_query("SELECT tid FROM {taxonomy_term_field_data} WHERE vid = :vid", array(':vid' => $bundle))
      ->fetchAllAssoc('tid');

    $urls = array();
    foreach ($results as $id => $changed) {
      foreach($languages as $language) {
        $urls[$id][$language->getId()] = Url::fromRoute("entity.taxonomy_term.canonical", array('taxonomy_term' => $id), array(
          'language' => $language,
          'absolute' => TRUE
        ))->toString();
      }
    }
    return $urls;
  }
}
