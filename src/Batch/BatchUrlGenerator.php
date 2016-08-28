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
  const PATH_DOES_NOT_EXIST_OR_NO_ACCESS = "The path @path has been omitted from the XML sitemap as it either does not exist, or it is not accessible to anonymous users.";

  protected $sitemapGenerator;
  protected $languages;
  protected $entityTypeManager;
  protected $pathValidator;
  protected $entityQuery;
  protected $anonUser;

  /**
   * BatchUrlGenerator constructor.
   *
   * @param $sitemap_generator
   * @param $language_manager
   * @param $entity_type_manager
   * @param $path_validator
   * @param $entity_query
   */
  public function __construct(
    $sitemap_generator,
    $language_manager,
    $entity_type_manager,
    $path_validator,
    $entity_query
  ) {
    $this->sitemapGenerator = $sitemap_generator; //todo using only one method, maybe make method static instead?
    $this->languages = $language_manager->getLanguages();
    $this->entityTypeManager = $entity_type_manager;
    $this->pathValidator = $path_validator;
    $this->entityQuery = $entity_query;
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
   * @param $batch_info
   * @return bool
   */
  protected function isBatch($batch_info) {
    return $batch_info['from'] != 'nobatch';
  }

  /**
   * @param $context
   * @return bool
   */
  protected function needsInitialization($context) {
    return empty($context['sandbox']);
  }

  /**
   * @param $path
   * @param $context
   * @return bool
   */
  protected function pathProcessed($path, &$context) {
    $path_pool = isset($context['results']['processed_paths']) ? $context['results']['processed_paths'] : [];
    if (in_array($path, $path_pool)) {
      return TRUE;
    }
    $context['results']['processed_paths'][] = $path;
    return FALSE;
  }

  /**
   * @param $batch_info
   * @param $max
   * @param $context
   */
  protected function initializeBatch($batch_info, $max, &$context) {
    $context['results']['generate'] = !empty($context['results']['generate']) ? $context['results']['generate'] : [];
    if ( $this->isBatch($batch_info)) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_id'] = 0;
      $context['sandbox']['max'] = $max;
      $context['results']['processed_paths'] = !empty($context['results']['processed_paths'])
        ? $context['results']['processed_paths'] : [];
    }
  }

  /**
   * @param $id
   * @param $context
   */
  protected function setCurrentId($id, &$context) {
    $context['sandbox']['progress']++;
    $context['sandbox']['current_id'] = $id;
  }

  /**
   * @param $context
   * @param $batch_info
   */
  protected function processSegment(&$context, $batch_info) {
    if ($this->isBatch($batch_info)) {
      $this->setProgressInfo($context);
    }
    if (!empty($batch_info['max_links']) && count($context['results']['generate']) >= $batch_info['max_links']) {
      $chunks = array_chunk($context['results']['generate'], $batch_info['max_links']);
      foreach ($chunks as $i => $chunk_links) {
        if (count($chunk_links) == $batch_info['max_links']) {
          $remove_sitemap = empty($context['results']['chunk_count']);
          $this->sitemapGenerator->generateSitemap($chunk_links, $remove_sitemap);
          $context['results']['chunk_count'] = !isset($context['results']['chunk_count'])
            ? 1 : $context['results']['chunk_count'] + 1;
          $context['results']['generate'] = array_slice($context['results']['generate'], count($chunk_links));
        }
      }
    }
  }

  /**
   * @param $context
   */
  protected function setProgressInfo(&$context) {
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      // Providing progress info to the batch API.
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
      // Adding processing message after finishing every batch segment.
      end($context['results']['generate']);
      $last_key = key($context['results']['generate']);
      if (!empty($context['results']['generate'][$last_key]['path'])) {
        $context['message'] = t("Processing path @current out of @max: @path", [
          '@current' => $context['sandbox']['progress'],
          '@max' => $context['sandbox']['max'],
          '@path' => HTML::escape($context['results']['generate'][$last_key]['path']),
        ]);
      }
    }
  }
  
  /**
   * Logs and displays an error.
   *
   * @param $message
   *  Untranslated message.
   * @param array $substitutions (optional)
   *  Substitutions (placeholder => substitution) which will replace placeholders
   *  with strings.
   * @param string $type (optional)
   *  Message type (status/warning/error).
   */
  protected function registerError($message, $substitutions = [], $type = 'error') {
    $message = strtr(t($message), $substitutions);
    \Drupal::logger('simple_sitemap')->notice($message); //todo DI
    drupal_set_message($message, $type);
  }

  /**
   * Batch callback function which generates urls to entity paths.
   *
   * @param array $entity_info
   * @param array $batch_info
   * @param array &$context
   *
   * @see https://api.drupal.org/api/drupal/core!includes!form.inc/group/batch/8
   */
  public function generateBundleUrls($entity_info, $batch_info, &$context) {

    $query = $this->entityQuery->get($entity_info['entity_type_name']);//todo
    if (!empty($entity_info['keys']['id']))
      $query->sort($entity_info['keys']['id'], 'ASC');
    if (!empty($entity_info['keys']['bundle']))
      $query->condition($entity_info['keys']['bundle'], $entity_info['bundle_name']);
    if (!empty($entity_info['keys']['status']))
      $query->condition($entity_info['keys']['status'], 1);

    // Initialize batch if not done yet.
    if ($this->needsInitialization($context)) {
      $count_query = clone $query;
      $this->initializeBatch($batch_info, $count_query->count()->execute(), $context);
    }

    // Creating a query limited to n=batch_process_limit entries.
    if ($this->isBatch($batch_info)) {
      $query->range($context['sandbox']['progress'], $batch_info['batch_process_limit']);
    }

    $results = $query->execute();
    if (!empty($results)) {
      $entities = $this->entityTypeManager->getStorage($entity_info['entity_type_name'])->loadMultiple($results);

      foreach ($entities as $entity_id => $entity) {
        if ($this->isBatch($batch_info)) {
          $this->setCurrentId($entity_id, $context);
        }

        // Overriding entity settings if it has been overridden on entity edit page...
        if (isset($batch_info['entity_types'][$entity_info['entity_type_name']][$entity_info['bundle_name']]['entities'][$entity_id]['index'])) {

          // Skipping entity if it has been excluded on entity edit page.
          if (!$batch_info['entity_types'][$entity_info['entity_type_name']][$entity_info['bundle_name']]['entities'][$entity_id]['index']) {
            continue;
          }
          // Otherwise overriding priority settings for this entity.
          $priority = $batch_info['entity_types'][$entity_info['entity_type_name']][$entity_info['bundle_name']]['entities'][$entity_id]['priority'];
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
        if ($batch_info['remove_duplicates'] && $this->pathProcessed($path, $context))
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
          if (!$batch_info['skip_untranslated'] || $language->isDefault() || $entity->hasTranslation($langcode)) {
            $url_object->setOption('language', $language);
            $alternate_urls[$langcode] = $url_object->toString();
          }
        }
        foreach($alternate_urls as $langcode => $url) {
          $context['results']['generate'][] = $path_data + ['langcode' => $langcode, 'url' => $url, 'alternate_urls' => $alternate_urls];
        }
      }
    }
    $this->processSegment($context, $batch_info);
  }

  /**
   * Batch function which generates urls to custom paths.
   *
   * @param array $custom_paths
   * @param array $batch_info
   * @param array &$context
   *
   * @see https://api.drupal.org/api/drupal/core!includes!form.inc/group/batch/8
   */
  public function generateCustomUrls($custom_paths, $batch_info, &$context) {

    // Initialize batch if not done yet.
    if ($this->needsInitialization($context)) {
      $this->initializeBatch($batch_info, count($custom_paths), $context);
    }

    foreach($custom_paths as $i => $custom_path) {
      if ($this->isBatch($batch_info)) {
        $this->setCurrentId($i, $context);
      }

      if (!$this->pathValidator->isValid($custom_path['path'])) { //todo: Change to different function, as this also checks if current user has access. The user however varies depending if process was started from the web interface or via cron/drush. Use getUrlIfValidWithoutAccessCheck()?
        $this->registerError(self::PATH_DOES_NOT_EXIST_OR_NO_ACCESS, ['@path' => $custom_path['path']], 'warning');
        continue;
      }
      $url_object = Url::fromUserInput($custom_path['path'], ['absolute' => TRUE]);

      if (!$url_object->access($this->anonUser))
        continue;

      $path = $url_object->getInternalPath();
      if ($batch_info['remove_duplicates'] && $this->pathProcessed($path, $context))
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
        if (!$batch_info['skip_untranslated'] || is_null($entity) || $entity->hasTranslation($langcode) || $language->isDefault()) {
          $url_object->setOption('language', $language);
          $alternate_urls[$langcode] = $url_object->toString();
        }
      }
      foreach($alternate_urls as $langcode => $url) {
        $context['results']['generate'][] = $path_data + ['langcode' => $langcode, 'url' => $url, 'alternate_urls' => $alternate_urls];
      }
    }
    $this->processSegment($context, $batch_info);
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
      drupal_set_message($this->t("The <a href='@url' target='_blank'>XML sitemap</a> has been regenerated for all languages.",
        ['@url' => $GLOBALS['base_url'] . '/sitemap.xml']));
    }
    else {
      //todo: register error
    }
  }
}
