<?php

namespace Drupal\simple_sitemap\Batch;

/**
 * Class ArbitraryUrlGenerator
 * @package Drupal\simple_sitemap\Batch
 */
class ArbitraryUrlGenerator extends UrlGeneratorBase implements UrlGeneratorInterface {

  /**
   * Batch function that adds arbitrary URLs to the sitemap.
   *
   * @param mixed $arbitrary_paths
   */
  public function generate($arbitrary_paths) {

    foreach ($this->getBatchIterationElements($arbitrary_paths) as $i => $path_data) {
      $this->setCurrentId($i);
      $this->context['results']['generate'][] = $path_data;
    }
    $this->processSegment();
  }
}
