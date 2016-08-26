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


  const BATCH_INIT_MESSAGE = 'Initializing batch...';
  const BATCH_ERROR_MESSAGE = 'An error has occurred. This may result in an incomplete XML sitemap.';
  const BATCH_PROGRESS_MESSAGE = 'Processing @current out of @total link types.';

  public function __construct() {
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

  /**
   * Batch callback function which generates urls to entity paths.
   *
   * @param array $entity_info
   * @param array $batch_info
   * @param array &$context
   */
  public static function generateBundleUrls($entity_info, $batch_info, &$context) {
    \Drupal::service('simple_sitemap.bundle_url_generator')->generateBundleUrls($entity_info, $batch_info, $context);
  }

  /**
   * Batch function which generates urls to custom paths.
   *
   * @param array $custom_paths
   * @param array $batch_info
   * @param array &$context
   */
  public static function generateCustomUrls($custom_paths, $batch_info, &$context) {
    \Drupal::service('simple_sitemap.custom_url_generator')->generateCustomUrls($custom_paths, $batch_info, $context);
  }
}
