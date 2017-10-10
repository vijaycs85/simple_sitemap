<?php

namespace Drupal\simple_sitemap\Commands;

use Drupal\simple_sitemap\Simplesitemap;
use Drush\Commands\DrushCommands;

/**
 * Class SimpleSitemapCommands
 * @package Drupal\simple_sitemap\Commands
 */
class SimpleSitemapCommands extends DrushCommands {

  /**
   * @var \Drupal\simple_sitemap\Simplesitemap
   */
  protected $generator;

  /**
   * SimpleSitemapCommands constructor.
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   */
  public function __construct(Simplesitemap $generator) {
    $this->generator = $generator;
  }

  /**
   * Regenerate the XML sitemap according to the module settings.
   *
   * @command simple-sitemap:generate
   * @aliases ss:generate, ssg
   */
  public function generate() {
    $this->generator->generateSitemap('drush');
  }

}
