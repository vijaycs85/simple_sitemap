<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

/**
 * Interface SitemapGeneratorInterface
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator
 */
interface SitemapGeneratorInterface {

  public function generate(array $links);

  public function generateIndex();

  public function remove();

}
