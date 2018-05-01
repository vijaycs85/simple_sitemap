<?php

/**
 * @file
 * Provide views data for simple_sitemap.module.
 */

/**
 * Implements hook_views_data().
 */
function simple_sitemap_views_data() {

  /** @var \Drupal\Core\Entity\ContentEntityTypeInterface[] $entity_types */
  $entity_types = \Drupal::service('simple_sitemap.entity_helper')->getSupportedEntityTypes();

  $data = [];
  foreach ($entity_types as $entity_type) {
    $table = $entity_type->getDataTable() ?: $entity_type->getBaseTable();
    $data[$table]['simple_sitemap_index'] = [
      'title' => t('Indexed in sitemap'),
      'field' => [
        'id' => 'field',
        'default_formatter' => 'boolean',
        'field_name' => 'simple_sitemap_index',
      ]
    ];
    $data[$table]['simple_sitemap_priority'] = [
      'title' => t('Priority in sitemap'),
      'field' => [
        'id' => 'field',
        'default_formatter' => 'number_decimal',
        'field_name' => 'simple_sitemap_priority',
      ]
    ];
    $data[$table]['simple_sitemap_changefreq'] = [
      'title' => t('Change frequency in sitemap'),
      'field' => [
        'id' => 'field',
        'default_formatter' => 'string',
        'field_name' => 'simple_sitemap_changefreq',
      ]
    ];
    $data[$table]['simple_sitemap_include_images'] = [
      'title' => t('Include images in sitemap'),
      'field' => [
        'id' => 'field',
        'default_formatter' => 'boolean',
        'field_name' => 'simple_sitemap_include_images',
      ]
    ];
  }

  return $data;
}
