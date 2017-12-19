<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

/**
 * Class EntityMenuLinkContentUrlGenerator
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 *
 * @UrlGenerator(
 *   id = "entity_menu_link_content",
 *   title = @Translation("Menu link URL generator"),
 *   description = @Translation("Generates menu link URLs by overriding the 'entity' URL generator."),
 *   enabled = TRUE,
 *   weight = 5,
 *   settings = {
 *     "instantiate_for_each_data_set" = true,
 *   },
 * )
 */
class EntityMenuLinkContentUrlGenerator extends EntityUrlGenerator {

  /**
   * @inheritdoc
   */
  public function getDataSets() {
    $data_sets = [];
    $bundle_settings = $this->generator->getBundleSettings();
    if (!empty($bundle_settings['menu_link_content'])) {

      $keys = $this->entityTypeManager->getDefinition('menu_link_content')->getKeys();
      $keys['bundle'] = 'menu_name'; // Menu fix.

      foreach ($bundle_settings['menu_link_content'] as $bundle_name => $settings) {
        if ($settings['index']) {
          $data_sets[] = [
            'bundle_settings' => $settings,
            'bundle_name' => $bundle_name,
            'entity_type_name' => 'menu_link_content',
            'keys' => $keys,
          ];
        }
      }
    }

    return $data_sets;
  }

  /**
   * @inheritdoc
   */
  protected function processDataSet($entity) {

    $entity_id = $entity->id();
    $entity_type_name = $entity->getEntityTypeId();

    $entity_settings = $this->generator->getEntityInstanceSettings($entity_type_name, $entity_id);

    if (empty($entity_settings['index'])) {
      return FALSE;
    }

    if (!$entity->isEnabled()) {
      return FALSE;
    }

    $url_object = $entity->getUrlObject();

    // Do not include external paths.
    if (!$url_object->isRouted()) {
      return FALSE;
    }

    $path = $url_object->getInternalPath();

    // Do not include paths that have been already indexed.
    if ($this->batchSettings['remove_duplicates'] && $this->pathProcessed($path)) {
      return FALSE;
    }

    $url_object->setOption('absolute', TRUE);

    return [
      'url' => $url_object,
      'lastmod' => NULL,
      'priority' => isset($entity_settings['priority']) ? $entity_settings['priority'] : NULL,
      'changefreq' => !empty($entity_settings['changefreq']) ? $entity_settings['changefreq'] : NULL,
      'images' => !empty($entity_settings['include_images']) //todo check if this is working for menu links
        ? $this->getImages($entity_type_name, $entity_id)
        : [],

      // Additional info useful in hooks.
      'meta' => [
        'path' => $path,
        'entity_info' => [
          'entity_type' => $entity_type_name,
          'id' => $entity_id,
        ],
      ]
    ];
  }
}
