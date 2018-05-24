<?php

namespace Drupal\simple_sitemap;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorBase;

/**
 * Class Batch
 * @package Drupal\simple_sitemap\Batch
 *
 * The services of this class are not injected, as this class looses its state
 * on every method call because of how the batch API works.
 */
class Batch {

  use StringTranslationTrait;

  /**
   * @var array
   */
  protected $batch;

  /**
   * @var array
   */
  protected $batchMeta;

  const BATCH_TITLE = 'Generating XML sitemap';
  const BATCH_INIT_MESSAGE = 'Initializing batch...';
  const BATCH_ERROR_MESSAGE = 'An error has occurred. This may result in an incomplete XML sitemap.';
  const BATCH_PROGRESS_MESSAGE = 'Running @current out of @total operations.';
  const REGENERATION_FINISHED_MESSAGE = 'The XML sitemaps have been regenerated.';
  const REGENERATION_FINISHED_ERROR_MESSAGE = 'The sitemap generation finished with an error.';

  /**
   * Batch constructor.
   */
  public function __construct() {
    $this->batch = [
      'title' => $this->t(self::BATCH_TITLE),
      'init_message' => $this->t(self::BATCH_INIT_MESSAGE),
      'error_message' => $this->t(self::BATCH_ERROR_MESSAGE),
      'progress_message' => $this->t(self::BATCH_PROGRESS_MESSAGE),
      'operations' => [],
      'finished' => [__CLASS__, 'finishGeneration'],
    ];
  }

  /**
   * @param array $batch_meta
   */
  public function setBatchMeta(array $batch_meta) {
    $this->batchMeta = $batch_meta;
  }

  /**
   * Adds an operation to the batch.
   *
   * @param string $operation_name
   * @param array $arguments
   */
  public function addOperation($operation_name, $arguments = []) {
    $this->batch['operations'][] = [
      __CLASS__ . '::' . $operation_name, [$arguments, $this->batchMeta]
    ];
  }

  /**
   * Batch callback function which generates URLs.
   *
   * @param array $arguments
   * @param array $batch_meta
   * @param $context
   *
   * @see https://api.drupal.org/api/drupal/core!includes!form.inc/group/batch/8
   */
  public static function generateSitemap(array $arguments, array $batch_meta, &$context) {

    /** @var UrlGeneratorBase $url_generator*/
    $url_generator = \Drupal::service('plugin.manager.simple_sitemap.url_generator')
      ->createInstance($arguments['url_generator']);

    $url_generator
      ->setContext($context)
      ->setSettings($arguments['settings'])
      ->setBatchMeta($batch_meta)
      ->setSitemapVariant($arguments['variant'])
      ->setSitemapGeneratorId($arguments['sitemap_generator'])
      ->generate($arguments['data_set']);
  }

  /**
   * Batch callback function which generates URLs.
   *
   * @param array $arguments
   * @param array $batch_meta
   * @param $context
   *
   * @see https://api.drupal.org/api/drupal/core!includes!form.inc/group/batch/8
   */
  public static function generateIndex(array $arguments, array $batch_meta, &$context) {

    /** @var SitemapGeneratorBase $sitemap_generator*/
    $sitemap_generator = \Drupal::service('plugin.manager.simple_sitemap.sitemap_generator')
      ->createInstance($arguments['sitemap_generator']);

    $sitemap_generator
      ->setSettings($arguments['settings'])
      ->setSitemapVariant($arguments['variant'])
      ->generateIndex();
  }

  /**
   * Batch callback function which generates URLs.
   *
   * @param array $arguments
   * @param array $batch_meta
   * @param $context
   *
   * @see https://api.drupal.org/api/drupal/core!includes!form.inc/group/batch/8
   */
  public static function removeSitemap(array $arguments, array $batch_meta, &$context) {

    /** @var SitemapGeneratorBase $sitemap_generator*/
    $sitemap_generator = \Drupal::service('plugin.manager.simple_sitemap.sitemap_generator')
      ->createInstance($arguments['sitemap_generator']);

    $sitemap_generator
      ->setSitemapVariant($arguments['variant'])
      ->remove()
      ->invalidateCache();
  }

  /**
   * Callback function called by the batch API when all operations are finished.
   *
   * @param $success
   * @param $results
   * @param $operations
   *
   * @return bool
   *
   * @see https://api.drupal.org/api/drupal/core!includes!form.inc/group/batch/8
   *
   * @todo Display success/failure message in Drush > 9.
   */
  public static function finishGeneration($success, $results, $operations) {
    if ($success) {
      \Drupal::service('simple_sitemap.logger')
        ->m(self::REGENERATION_FINISHED_MESSAGE)
//        ['@url' => $this->sitemapGenerator->getCustomBaseUrl() . '/sitemap.xml']) //todo: Use actual base URL for message.
        ->display('status')
        ->log('info');
    }
    else {
      \Drupal::service('simple_sitemap.logger')
        ->m(self::REGENERATION_FINISHED_ERROR_MESSAGE)
        ->display('error', 'administer sitemap settings')
        ->log('error');
    }

    return $success;
  }

  /**
   * Starts the batch process depending on where it was requested from.
   *
   * @return bool
   */
  public function start() {

    // Update last operation info for each operation.
    $this->addAdditionalMetaInfo();

    switch ($this->batchMeta['from']) {

      case 'form':
        // Start batch process.
        batch_set($this->batch);
        return TRUE;

      case 'drush':
        // Start drush batch process.
        batch_set($this->batch);

        // See https://www.drupal.org/node/638712
        $this->batch =& batch_get();
        $this->batch['progressive'] = FALSE;

        drush_log($this->t(self::BATCH_INIT_MESSAGE), 'status');
        drush_backend_batch_process();
        return TRUE;

      case 'backend':
        // Start backend batch process.
        batch_set($this->batch);

        // See https://www.drupal.org/node/638712
        $this->batch =& batch_get();
        $this->batch['progressive'] = FALSE;

        // todo: Does not take advantage of batch API and eventually runs out of memory on very large sites. Use queue API instead?
        batch_process();
        return TRUE;

      case 'nobatch':
        // Call each batch operation the way the Drupal batch API would do, but
        // within one process (so in fact not using batch API here, just
        // mimicking it to avoid code duplication).
        $context = [];
        foreach ($this->batch['operations'] as $i => $operation) {
          $operation[1][] = &$context;
          call_user_func_array($operation[0], $operation[1]);
        }
        return $this->finishGeneration(TRUE, !empty($context['results']) ? $context['results'] : [], []);
    }
    return FALSE;
  }

  protected function addAdditionalMetaInfo() {
    $last_operation_no = 0;
    foreach ($this->batch['operations'] as $i => $operation) {
      if ($operation[0] === __CLASS__ . '::generateSitemap') {
        $this->batch['operations'][$i][1][1]['current_generate_sitemap_operation_no'] = $i;
        $last_operation_no = $i;
      }
    }
    foreach ($this->batch['operations'] as $i => $operation) {
      if ($operation[0] === __CLASS__ . '::generateSitemap') {
        $this->batch['operations'][$i][1][1]['last_generate_sitemap_operation_no'] = $last_operation_no;
      }
    }
  }
}
