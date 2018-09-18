<?php

namespace Drupal\simple_sitemap\Queue;

use Drupal\Core\StringTranslation\StringTranslationTrait;

trait BatchTrait {

  use StringTranslationTrait;

  /**
   * @var array
   */
  protected $batch;

  protected static $batchErrorMessage = 'The generation failed to finish. It can be continued manually on the module\'s setting page, or via drush.';

  /**
   * @param string $from
   * @param null $variants
   * @return bool
   *
   * @todo Implement generating for certain variants only.
   */
  public function batchGenerateSitemap($from = 'form', $variants = NULL) {
    $this->batch = [
      'title' => $this->t('Generating XML sitemaps'),
      'init_message' => $this->t('Initializing...'),
      'error_message' => $this->t(self::$batchErrorMessage),
      'progress_message' => $this->t('Processing items from queue. Each sitemap variant is published as soon as its items have been processed.'),
      'operations' => [[ __CLASS__ . '::' . 'doBatchGenerateSitemap', []]],
      'finished' => [__CLASS__, 'finishGeneration'],
    ];

    switch ($from) {

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

        drush_log($this->batch['init_message'], 'status');
        drush_backend_batch_process();
        return TRUE;
    }
    return FALSE;
  }

  /**
   * @param $context
   *
   * @todo Make sure batch does not run at the same time as cron.
   * @todo Variants into generateSitemap().
   */
  public static function doBatchGenerateSitemap(&$context) {

    /** @var \Drupal\simple_sitemap\Queue\QueueWorker $queue_worker */
    $queue_worker = \Drupal::service('simple_sitemap.queue_worker');

    $queue_worker->generateSitemap();
    $processed_element_count = $queue_worker->getProcessedElementCount();
    $original_element_count = $queue_worker->getInitialElementCount();

    $context['message'] = t('@indexed out of @total total items have been indexed.', [
      '@indexed' => $processed_element_count, '@total' => $original_element_count]);
    $context['finished'] = $processed_element_count / $original_element_count;
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
        ->m('The XML sitemaps have been regenerated.')
//        ['@url' => $this->sitemapGenerator->getCustomBaseUrl() . '/sitemap.xml']) //todo: Use actual base URL for message.
        ->display('status')
        ->log('info');
    }
    else {
      \Drupal::service('simple_sitemap.logger')
        ->m(self::$batchErrorMessage)
        ->display('error', 'administer sitemap settings')
        ->log('error');
    }

    return $success;
  }
}

