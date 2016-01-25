<?php

/**
 * @file
 * Provides Drupal\simplesitemap\LinkGenerator.
 */

namespace Drupal\simplesitemap;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for simplesitemap plugins.
 */

interface LinkGeneratorInterface extends PluginInspectionInterface {

  /**
   *
   *
   * @return array
   */
  public function get_entity_links($entity_type, $bundles, $languages);
}
