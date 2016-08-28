<?php

namespace Drupal\simple_sitemap\Batch;

use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class BatchUrlGenerator
 * @package Drupal\simple_sitemap\Batch
 */
class BatchUrlGenerator {

  use StringTranslationTrait;

  const ANONYMOUS_USER_ID = 0;
  const PATH_DOES_NOT_EXIST_OR_NO_ACCESS_MESSAGE = "The path @path has been omitted from the XML sitemap as it either does not exist, or it is not accessible to anonymous users.";
  const PROCESSING_PATH_MESSAGE = 'Processing path #@current out of @max: @path';
  const REGENERATION_FINISHED_MESSAGE= "The <a href='@url' target='_blank'>XML sitemap</a> has been regenerated for all languages.";

  protected $sitemapGenerator;
  protected $languages;
  protected $entityTypeManager;
  protected $pathValidator;
  protected $entityQuery;
  protected $logger;
  protected $anonUser;

  protected $context;
  protected $batchInfo;

  /**
   * BatchUrlGenerator constructor.
   * @param $sitemap_generator
   * @param $language_manager
   * @param $entity_type_manager
   * @param $path_validator
   * @param $entity_query
   * @param $logger
   */
  public function __construct(
    $sitemap_generator,
    $language_manager,
    $entity_type_manager,
    $path_validator,
    $entity_query,
    $logger
  ) {
    $this->sitemapGenerator = $sitemap_generator; //todo using only one method, maybe make method static instead?
    $this->languages = $language_manager->getLanguages();
    $this->entityTypeManager = $entity_type_manager;
    $this->pathValidator = $path_validator;
    $this->entityQuery = $entity_query;
    $this->logger = $logger;
    $this->anonUser = $this->entityTypeManager->getStorage('user')->load(self::ANONYMOUS_USER_ID);
  }

  /**
   * The Drupal batch API can only call procedural functions or static methods.
   * To circumvent exclusively procedural code, on every batch iteration this
   * static method is called by the batch API and returns a freshly created
   * Drupal service object of this class. All following calls can be made on
   * the returned service the OOP way. This is is obviously trading performance
   * for cleanness. The service is created within its own class to improve
   * testability.
   *
   * @return object
   *   Symfony service object of this class
   */
  public static function service() {
    return \Drupal::service('simple_sitemap.batch_url_generator');
  }

  /**
   * @param $context
   * @return $this
   */
  public function setContext(&$context) {
    $this->context = &$context;
    return $this;
  }

  /**
   * @param $batch_info
   * @return $this
   */
  public function setBatchInfo($batch_info) {
    $this->batchInfo = $batch_info;
    return $this;
  }

  /**
   * @return bool
   */
  protected function isBatch() {
    return $this->batchInfo['from'] != 'nobatch';
  }

  /**
   * @param $path
   * @return bool
   */
  protected function pathProcessed($path) {
    $path_pool = isset($this->context['results']['processed_paths']) ? $this->context['results']['processed_paths'] : [];
    if (in_array($path, $path_pool)) {
      return TRUE;
    }
    $this->context['results']['processed_paths'][] = $path;
    return FALSE;
  }

  /**
   * @param $max
   */
  protected function initializeBatch($max) {
    if ($this->needsInitialization()) {
      $this->context['results']['generate'] = !empty($this->context['results']['generate']) ? $this->context['results']['generate'] : [];
      if ($this->isBatch()) {
        $this->context['sandbox']['progress'] = 0;
        $this->context['sandbox']['current_id'] = 0;
        $this->context['sandbox']['max'] = $max;
        $this->context['results']['processed_paths'] = !empty($this->context['results']['processed_paths'])
          ? $this->context['results']['processed_paths'] : [];
      }
    }
  }

  /**
   * @return bool
   */
  protected function needsInitialization() {
    return empty($this->context['sandbox']);
  }

  /**
   * @param $id
   */
  protected function setCurrentId($id) {
    if ($this->isBatch()) {
      $this->context['sandbox']['progress']++;
      $this->context['sandbox']['current_id'] = $id;
    }
  }

  protected function processSegment() {
    if ($this->isBatch()) {
      $this->setProgressInfo();
    }
    if (!empty($this->batchInfo['max_links']) && count($this->context['results']['generate']) >= $this->batchInfo['max_links']) {
      $chunks = array_chunk($this->context['results']['generate'], $this->batchInfo['max_links']);
      foreach ($chunks as $i => $chunk_links) {
        if (count($chunk_links) == $this->batchInfo['max_links']) {
          $remove_sitemap = empty($this->context['results']['chunk_count']);
          $this->sitemapGenerator->generateSitemap($chunk_links, $remove_sitemap);
          $this->context['results']['chunk_count'] = !isset($this->context['results']['chunk_count'])
            ? 1 : $this->context['results']['chunk_count'] + 1;
          $this->context['results']['generate'] = array_slice($this->context['results']['generate'], count($chunk_links));
        }
      }
    }
  }

  protected function setProgressInfo() {
    if ($this->context['sandbox']['progress'] != $this->context['sandbox']['max']) {
      // Providing progress info to the batch API.
      $this->context['finished'] = $this->context['sandbox']['progress'] / $this->context['sandbox']['max'];
      // Adding processing message after finishing every batch segment.
      end($this->context['results']['generate']);
      $last_key = key($this->context['results']['generate']);
      if (!empty($this->context['results']['generate'][$last_key]['path'])) {
        $this->context['message'] = $this->t(self::PROCESSING_PATH_MESSAGE, [
          '@current' => $this->context['sandbox']['progress'],
          '@max' => $this->context['sandbox']['max'],
          '@path' => HTML::escape($this->context['results']['generate'][$last_key]['path']),
        ]);
      }
    }
  }

  /**
   * Batch callback function which generates urls to entity paths.
   *
   * @param array $entity_info
   */
  public function generateBundleUrls($entity_info) {

    $query = $this->entityQuery->get($entity_info['entity_type_name']);
    if (!empty($entity_info['keys']['id']))
      $query->sort($entity_info['keys']['id'], 'ASC');
    if (!empty($entity_info['keys']['bundle']))
      $query->condition($entity_info['keys']['bundle'], $entity_info['bundle_name']);
    if (!empty($entity_info['keys']['status']))
      $query->condition($entity_info['keys']['status'], 1);

    $count_query = clone $query;
    $this->initializeBatch($count_query->count()->execute());

    // Creating a query limited to n=batch_process_limit entries.
    if ($this->isBatch()) {
      $query->range($this->context['sandbox']['progress'], $this->batchInfo['batch_process_limit']);
    }

    $results = $query->execute();
    if (!empty($results)) {
      $entities = $this->entityTypeManager->getStorage($entity_info['entity_type_name'])->loadMultiple($results);

      foreach ($entities as $entity_id => $entity) {
        $this->setCurrentId($entity_id);

        // Overriding entity settings if it has been overridden on entity edit page...
        if (isset($this->batchInfo['entity_types'][$entity_info['entity_type_name']][$entity_info['bundle_name']]['entities'][$entity_id]['index'])) {

          // Skipping entity if it has been excluded on entity edit page.
          if (!$this->batchInfo['entity_types'][$entity_info['entity_type_name']][$entity_info['bundle_name']]['entities'][$entity_id]['index']) {
            continue;
          }
          // Otherwise overriding priority settings for this entity.
          $priority = $this->batchInfo['entity_types'][$entity_info['entity_type_name']][$entity_info['bundle_name']]['entities'][$entity_id]['priority'];
        }

        switch ($entity_info['entity_type_name']) {
          case 'menu_link_content': // Loading url object for menu links.
            if (!$entity->isEnabled())
              continue;
            $url_object = $entity->getUrlObject();
            break;
          default: // Loading url object for other entities.
            $url_object = $entity->toUrl(); //todo: file entity type does not have a canonical url and breaks generation, hopefully fixed in https://www.drupal.org/node/2402533
        }

        // Do not include external paths.
        if (!$url_object->isRouted())
          continue;

        // Do not include paths inaccessible to anonymous users.
        if (!$url_object->access($this->anonUser))
          continue;

        // Do not include paths that have been already indexed.
        $path = $url_object->getInternalPath();
        if ($this->batchInfo['remove_duplicates'] && $this->pathProcessed($path))
          continue;

        $url_object->setOption('absolute', TRUE);

        $path_data = [
          'path' => $path,
          'entity_info' => ['entity_type' => $entity_info['entity_type_name'], 'id' => $entity_id],
          'lastmod' => method_exists($entity, 'getChangedTime') ? date_iso8601($entity->getChangedTime()) : NULL,
          'priority' => isset($priority) ? $priority : (isset($entity_info['bundle_settings']['priority']) ? $entity_info['bundle_settings']['priority'] : NULL),
        ];
        $priority = NULL;

        $alternate_urls = [];
        foreach ($this->languages as $language) {
          $langcode = $language->getId();
          if (!$this->batchInfo['skip_untranslated'] || $language->isDefault() || $entity->hasTranslation($langcode)) {
            $url_object->setOption('language', $language);
            $alternate_urls[$langcode] = $url_object->toString();
          }
        }
        foreach($alternate_urls as $langcode => $url) {
          $this->context['results']['generate'][] = $path_data + ['langcode' => $langcode, 'url' => $url, 'alternate_urls' => $alternate_urls];
        }
      }
    }
    $this->processSegment();
  }

  /**
   * Batch function which generates urls to custom paths.
   *
   * @param array $custom_paths
   */
  public function generateCustomUrls($custom_paths) {

    $this->initializeBatch(count($custom_paths));

    foreach($custom_paths as $i => $custom_path) {
      $this->setCurrentId($i);

      if (!$this->pathValidator->isValid($custom_path['path'])) { //todo: Change to different function, as this also checks if current user has access. The user however varies depending if process was started from the web interface or via cron/drush. Use getUrlIfValidWithoutAccessCheck()?
        $this->logger->registerError([self::PATH_DOES_NOT_EXIST_OR_NO_ACCESS_MESSAGE, ['@path' => $custom_path['path']]], 'warning');
        continue;
      }
      $url_object = Url::fromUserInput($custom_path['path'], ['absolute' => TRUE]);

      if (!$url_object->access($this->anonUser))
        continue;

      $path = $url_object->getInternalPath();
      if ($this->batchInfo['remove_duplicates'] && $this->pathProcessed($path))
        continue;

      // Load entity object if this is an entity route.
      $route_parameters = $url_object->getRouteParameters();
      $entity = !empty($route_parameters)
        ? $this->entityTypeManager->getStorage(key($route_parameters))->load($route_parameters[key($route_parameters)])
        : NULL;

      $path_data = [
        'path' => $path,
        'lastmod' => method_exists($entity, 'getChangedTime') ? date_iso8601($entity->getChangedTime()) : NULL,
        'priority' => isset($custom_path['priority']) ? $custom_path['priority'] : NULL,
      ];
      if (!is_null($entity)) {
        $path_data['entity_info'] = ['entity_type' => $entity->getEntityTypeId(), 'id' => $entity->id()];
      }
      $alternate_urls = [];
      foreach ($this->languages as $language) {
        $langcode = $language->getId();
        if (!$this->batchInfo['skip_untranslated'] || is_null($entity) || $entity->hasTranslation($langcode) || $language->isDefault()) {
          $url_object->setOption('language', $language);
          $alternate_urls[$langcode] = $url_object->toString();
        }
      }
      foreach($alternate_urls as $langcode => $url) {
        $this->context['results']['generate'][] = $path_data + ['langcode' => $langcode, 'url' => $url, 'alternate_urls' => $alternate_urls];
      }
    }
    $this->processSegment();
  }

  /**
   * Callback function called by the batch API when all operations are finished.
   *
   * @see https://api.drupal.org/api/drupal/core!includes!form.inc/group/batch/8
   */
  public function finishGeneration($success, $results, $operations) {
    if ($success) {
      $remove_sitemap = empty($results['chunk_count']);
      if (!empty($results['generate']) || $remove_sitemap) {
        $this->sitemapGenerator->generateSitemap($results['generate'], $remove_sitemap);
      }
      Cache::invalidateTags(['simple_sitemap']);
      drupal_set_message($this->t(self::REGENERATION_FINISHED_MESSAGE,
        ['@url' => $GLOBALS['base_url'] . '/sitemap.xml']));
    }
    else {
      //todo: register error
    }
  }
}
