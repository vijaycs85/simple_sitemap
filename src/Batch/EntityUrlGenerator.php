<?php

namespace Drupal\simple_sitemap\Batch;

/**
 * Class BatchUrlGenerator
 * @package Drupal\simple_sitemap\Batch
 */
class EntityUrlGenerator extends UrlGeneratorBase implements UrlGeneratorInterface {

  /**
   * Batch callback function which generates urls to entity paths.
   *
   * @param mixed $entity_info
   */
  public function generate($entity_info) {

    foreach ($this->getBatchIterationElements($entity_info) as $entity_id => $entity) {

      $this->setCurrentId($entity_id);

      $entity_settings = $this->generator->getEntityInstanceSettings($entity_info['entity_type_name'], $entity_id);

      if (empty($entity_settings['index'])) {
        continue;
      }

      switch ($entity_info['entity_type_name']) {
        // Loading url object for menu links.
        case 'menu_link_content':
          if (!$entity->isEnabled()) {
            continue 2;
          }
          $url_object = $entity->getUrlObject();
          break;

        // Loading url object for other entities.
        default:
          $url_object = $entity->toUrl();
      }

      // Do not include external paths.
      if (!$url_object->isRouted()) {
        continue;
      }

      $path = $url_object->getInternalPath();

      // Do not include paths that have been already indexed.
      if ($this->batchInfo['remove_duplicates'] && $this->pathProcessed($path)) {
        continue;
      }

      $url_object->setOption('absolute', TRUE);

      $path_data = [
        'path' => $path,
        'entity_info' => [
          'entity_type' => $entity_info['entity_type_name'],
          'id' => $entity_id
        ],
        'lastmod' => method_exists($entity, 'getChangedTime')
          ? date_iso8601($entity->getChangedTime()) : NULL,
        'priority' => isset($entity_settings['priority']) ? $entity_settings['priority'] : NULL,
        'changefreq' => !empty($entity_settings['changefreq']) ? $entity_settings['changefreq'] : NULL,
        'images' => $entity_settings['include_images']
          ? $this->entityHelper->getEntityImageUris($entity_info['entity_type_name'], $entity_id)
          : []
      ];

      $this->addUrlVariants($url_object, $path_data, $entity);
    }
    $this->processSegment();
  }

  /**
   * @param array $entity_info
   * @return \Drupal\Core\Entity\EntityInterface[]
   */
  protected function getBatchIterationElements(array $entity_info) {
    $query = $this->entityTypeManager->getStorage($entity_info['entity_type_name'])->getQuery();

    if (!empty($entity_info['keys']['id'])) {
      $query->sort($entity_info['keys']['id'], 'ASC');
    }
    if (!empty($entity_info['keys']['bundle'])) {
      $query->condition($entity_info['keys']['bundle'], $entity_info['bundle_name']);
    }
    if (!empty($entity_info['keys']['status'])) {
      $query->condition($entity_info['keys']['status'], 1);
    }

    if ($this->needsInitialization()) {
      $count_query = clone $query;
      $this->initializeBatch($count_query->count()->execute());
    }

    if ($this->isBatch()) {
      $query->range($this->context['sandbox']['progress'], $this->batchInfo['batch_process_limit']);
    }

    return $this->entityTypeManager
      ->getStorage($entity_info['entity_type_name'])
      ->loadMultiple($query->execute());
  }
}
