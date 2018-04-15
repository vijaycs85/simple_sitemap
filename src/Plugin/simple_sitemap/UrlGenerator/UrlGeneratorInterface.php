<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

/**
 * Interface UrlGeneratorInterface
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 */
interface UrlGeneratorInterface {

  public function generate(array $data_sets);

  public function getDataSets();
}
