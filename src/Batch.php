<?php

namespace Drupal\simple_sitemap;

use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class Batch {
  use StringTranslationTrait;
  private $batch;
  private $batchInfo;

  const PATH_DOES_NOT_EXIST_OR_NO_ACCESS = "The path @path has been omitted from the XML sitemap as it either does not exist, or it is not accessible to anonymous users.";
  const BATCH_INIT_MESSAGE = 'Initializing batch...';
  const BATCH_ERROR_MESSAGE = 'An error has occurred. This may result in an incomplete XML sitemap.';
  const BATCH_PROGRESS_MESSAGE = 'Processing @current out of @total link types.';
  const ANONYMOUS_USER_ID = 0;

  function __construct() {
    $this->batch = [
      'title' => $this->t('Generating XML sitemap'),
      'init_message' => $this->t(self::BATCH_INIT_MESSAGE),
      'error_message' => $this->t(self::BATCH_ERROR_MESSAGE),
      'progress_message' => $this->t(self::BATCH_PROGRESS_MESSAGE),
      'operations' => [],
      'finished' => [__CLASS__, 'finishGeneration'], // __CLASS__ . '::finishGeneration' not working possibly due to a drush error.
    ];
  }

  public function setBatchInfo($batch_info) {
    $this->batchInfo = $batch_info;
  }

  /**
   * Starts the batch process depending on where it was requested from.
   */
  public function start() {
    switch ($this->batchInfo['from']) {

      case 'form':
        batch_set($this->batch);
        break;

      case 'drush':
        batch_set($this->batch);
        $this->batch =& batch_get();
        $this->batch['progressive'] = FALSE;
        drush_log(t(self::BATCH_INIT_MESSAGE), 'status');
        drush_backend_batch_process();
        break;

      case 'backend':
        batch_set($this->batch);
        $this->batch =& batch_get();
        $this->batch['progressive'] = FALSE;
        batch_process(); //todo: Does not take advantage of batch API and eventually runs out of memory on very large sites.
        break;

      case 'nobatch':
        $context = [];
        foreach($this->batch['operations'] as $i => $operation) {
          $operation[1][] = &$context;
          call_user_func_array($operation[0], $operation[1]);
        }
        self::finishGeneration(TRUE, $context['results'], []);
        break;
    }
  }

  /**
   * Adds an operation to the batch.
   *
   * @param string $processing_method
   * @param array $data
   */
  public function addOperation($processing_method, $data) {
    $this->batch['operations'][] = [
      __CLASS__ . '::' . $processing_method, [$data, $this->batchInfo]
    ];
  }

  /**
   * Callback function called by the batch API when all operations are finished.
   *
   * @see https://api.drupal.org/api/drupal/core!includes!form.inc/group/batch/8
   */
  public static function finishGeneration($success, $results, $operations) {
    if ($success) {
      $remove_sitemap = empty($results['chunk_count']);
      if (!empty($results['generate']) || $remove_sitemap) {
        \Drupal::service('simple_sitemap.sitemap_generator')->generateSitemap($results['generate'], $remove_sitemap);
      }
      Cache::invalidateTags(['simple_sitemap']);
      drupal_set_message(t("The <a href='@url' target='_blank'>XML sitemap</a> has been regenerated for all languages.",
        ['@url' => $GLOBALS['base_url'] . '/sitemap.xml']));
    }
    else {
      //todo: register error
    }
  }

  private static function isBatch($batch_info) {
    return $batch_info['from'] != 'nobatch';
  }

  private static function needsInitialization($context) {
    return empty($context['sandbox']);
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
  public static function generateBundleUrls($entity_info, $batch_info, &$context) {
    $query = \Drupal::entityQuery($entity_info['entity_type_name']);
    if (!empty($entity_info['keys']['id']))
      $query->sort($entity_info['keys']['id'], 'ASC');
    if (!empty($entity_info['keys']['bundle']))
      $query->condition($entity_info['keys']['bundle'], $entity_info['bundle_name']);
    if (!empty($entity_info['keys']['status']))
      $query->condition($entity_info['keys']['status'], 1);

    // Initialize batch if not done yet.
    if (self::needsInitialization($context)) {
      $count_query = clone $query;
      self::initializeBatch($batch_info, $count_query->count()->execute(), $context);
    }

    // Creating a query limited to n=batch_process_limit entries.
    if (self::isBatch($batch_info)) {
      $query->range($context['sandbox']['progress'], $batch_info['batch_process_limit']);
    }

    $results = $query->execute();
    if (!empty($results)) {
      $languages = \Drupal::languageManager()->getLanguages();
      $anon_user = User::load(self::ANONYMOUS_USER_ID);
      $entities = \Drupal::entityTypeManager()->getStorage($entity_info['entity_type_name'])->loadMultiple($results);

      foreach ($entities as $entity_id => $entity) {
        if (self::isBatch($batch_info)) {
          self::setCurrentId($entity_id, $context);
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
        if (!$url_object->access($anon_user))
          continue;

        // Do not include paths that have been already indexed.
        $path = $url_object->getInternalPath();
        if ($batch_info['remove_duplicates'] && self::pathProcessed($path, $context))
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
        foreach ($languages as $language) {
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
    if (self::isBatch($batch_info)) {
      self::setProgressInfo($context);
    }
    self::processSegment($context, $batch_info);
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
  public static function generateCustomUrls($custom_paths, $batch_info, &$context) {

    $languages = \Drupal::languageManager()->getLanguages();
    $anon_user = User::load(self::ANONYMOUS_USER_ID);

    // Initialize batch if not done yet.
    if (self::needsInitialization($context)) {
      self::initializeBatch($batch_info, count($custom_paths), $context);
    }

    foreach($custom_paths as $i => $custom_path) {
      if (self::isBatch($batch_info)) {
        self::setCurrentId($i, $context);
      }

      if (!\Drupal::service('path.validator')->isValid($custom_path['path'])) { //todo: Change to different function, as this also checks if current user has access. The user however varies depending if process was started from the web interface or via cron/drush.
        self::registerError(self::PATH_DOES_NOT_EXIST_OR_NO_ACCESS, ['@path' => $custom_path['path']], 'warning');
        continue;
      }
      $url_object = Url::fromUserInput($custom_path['path'], ['absolute' => TRUE]);

      if (!$url_object->access($anon_user))
        continue;

      $path = $url_object->getInternalPath();
      if ($batch_info['remove_duplicates'] && self::pathProcessed($path, $context))
        continue;

      // Load entity object if this is an entity route.
      $route_parameters = $url_object->getRouteParameters();
      $entity = !empty($route_parameters)
        ? \Drupal::entityTypeManager()->getStorage(key($route_parameters))->load($route_parameters[key($route_parameters)])
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
      foreach ($languages as $language) {
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
    if (self::isBatch($batch_info)) {
      self::setProgressInfo($context);
    }
    self::processSegment($context, $batch_info);
  }

  private static function pathProcessed($path, &$context) {
    $path_pool = isset($context['results']['processed_paths']) ? $context['results']['processed_paths'] : [];
    if (in_array($path, $path_pool)) {
      return TRUE;
    }
    $context['results']['processed_paths'][] = $path;
    return FALSE;
  }

  private static function initializeBatch($batch_info, $max, &$context) {
    $context['results']['generate'] = !empty($context['results']['generate']) ? $context['results']['generate'] : [];
    if (self::isBatch($batch_info)) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_id'] = 0;
      $context['sandbox']['max'] = $max;
      $context['results']['processed_paths'] = !empty($context['results']['processed_paths'])
        ? $context['results']['processed_paths'] : [];
    }
  }

  private static function setCurrentId($id, &$context) {
    $context['sandbox']['progress']++;
    $context['sandbox']['current_id'] = $id;
  }

  private static function setProgressInfo(&$context) {
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

  private static function processSegment(&$context, $batch_info) {
    if (!empty($batch_info['max_links']) && count($context['results']['generate']) >= $batch_info['max_links']) {
      $chunks = array_chunk($context['results']['generate'], $batch_info['max_links']);
      foreach ($chunks as $i => $chunk_links) {
        if (count($chunk_links) == $batch_info['max_links']) {
          $remove_sitemap = empty($context['results']['chunk_count']);
          \Drupal::service('simple_sitemap.sitemap_generator')->generateSitemap($chunk_links, $remove_sitemap);
          $context['results']['chunk_count'] = !isset($context['results']['chunk_count'])
            ? 1 : $context['results']['chunk_count'] + 1;
          $context['results']['generate'] = array_slice($context['results']['generate'], count($chunk_links));
        }
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
  private static function registerError($message, $substitutions = [], $type = 'error') {
    $message = strtr(t($message), $substitutions);
    \Drupal::logger('simple_sitemap')->notice($message);
    drupal_set_message($message, $type);
  }
}
