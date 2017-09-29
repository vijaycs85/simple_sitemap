<?php

namespace Drupal\simple_sitemap\Batch\Generator;

/**
 * Interface UrlGeneratorInterface
 * @package Drupal\simple_sitemap\Batch\Generator
 */
interface UrlGeneratorInterface {

  /**
   * @param mixed $data
   */
  public function generate($data);

}
