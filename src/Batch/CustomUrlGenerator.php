<?php

namespace Drupal\simple_sitemap\Batch;

use Drupal\Core\Url;

/**
 * Class BatchUrlGenerator
 * @package Drupal\simple_sitemap\Batch
 */
class CustomUrlGenerator extends UrlGeneratorBase implements UrlGeneratorInterface {

  const PATH_DOES_NOT_EXIST_OR_NO_ACCESS_MESSAGE = "The custom path @path has been omitted from the XML sitemap as it either does not exist, or it is not accessible to anonymous users. You can review custom paths <a href='@custom_paths_url'>here</a>.";

  /**
   * Batch function which generates urls to custom paths.
   *
   * @param mixed $custom_paths
   */
  public function generate($custom_paths) {

    foreach ($this->getBatchIterationCustomPaths($custom_paths) as $i => $custom_path) {

      $this->setCurrentId($i);

      // todo: Change to different function, as this also checks if current user has access. The user however varies depending if process was started from the web interface or via cron/drush. Use getUrlIfValidWithoutAccessCheck()?
      if (!$this->pathValidator->isValid($custom_path['path'])) {
//        if (!(bool) $this->pathValidator->getUrlIfValidWithoutAccessCheck($custom_path['path'])) {
        $this->logger->m(self::PATH_DOES_NOT_EXIST_OR_NO_ACCESS_MESSAGE,
          ['@path' => $custom_path['path'], '@custom_paths_url' => $GLOBALS['base_url'] . '/admin/config/search/simplesitemap/custom'])
          ->display('warning', 'administer sitemap settings')
          ->log('warning');
        continue;
      }
      $url_object = Url::fromUserInput($custom_path['path'], ['absolute' => TRUE]);

      $path = $url_object->getInternalPath();
      if ($this->batchInfo['remove_duplicates'] && $this->pathProcessed($path)) {
        continue;
      }

      $entity = $this->getEntityFromUrlObject($url_object);

      $path_data = [
        'path' => $path,
        'lastmod' => method_exists($entity, 'getChangedTime')
          ? date_iso8601($entity->getChangedTime()) : NULL,
        'priority' => isset($custom_path['priority']) ? $custom_path['priority'] : NULL,
      ];
      if (NULL !== $entity) {
        $path_data['entity_info'] = [
          'entity_type' => $entity->getEntityTypeId(),
          'id' => $entity->id()
        ];
      }
      $this->addUrlVariants($url_object, $path_data, $entity);
    }
    $this->processSegment();
  }

  /**
   * @param array $custom_paths
   * @return array
   */
  protected function getBatchIterationCustomPaths(array $custom_paths) {

    if ($this->needsInitialization()) {
      $this->initializeBatch(count($custom_paths));
    }

    if ($this->isBatch()) {
      $custom_paths = array_slice($custom_paths, $this->context['sandbox']['progress'], $this->batchInfo['batch_process_limit']);
    }

    return $custom_paths;
  }

  /**
   * @param $url_object
   * @return object|null
   */
  protected function getEntityFromUrlObject(Url $url_object) {
    $route_parameters = $url_object->getRouteParameters();
    return !empty($route_parameters) && $this->entityTypeManager
      ->getDefinition($entity_type_id = key($route_parameters), FALSE)
      ? $this->entityTypeManager->getStorage($entity_type_id)
        ->load($route_parameters[$entity_type_id])
      : NULL;
  }
}
