<?php
/**
 * @file
 * Contains \Drupal\simple_sitemap\Plugin\LinkGenerator\CommerceProductType.
 *
 * Plugin for commerce product entity link generation.
 */

namespace Drupal\simple_sitemap\Plugin\LinkGenerator;

use Drupal\simple_sitemap\Annotation\LinkGenerator;
use Drupal\simple_sitemap\LinkGeneratorBase;

/**
 * CommerceProductType class.
 *
 * @LinkGenerator(
 *   id = "commerce_product_type"
 * )
 */
class CommerceProductType extends LinkGeneratorBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return array(
      'field_info' => array(
        'entity_id' => 'product_id',
        'lastmod' => 'changed',
      ),
      'path_info' => array(
        'route_name' => 'entity.commerce_product.canonical',
        'entity_type' => 'commerce_product',
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery($bundle) {
    return $this->database->select('commerce_product_field_data', 'p')
      ->fields('p', array('product_id', 'changed'))
      ->condition('type', $bundle)
      ->condition('status', 1);
  }
}
