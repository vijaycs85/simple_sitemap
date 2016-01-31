<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\LinkGeneratorBase.
 */

namespace Drupal\simplesitemap;

use Drupal\Component\Plugin\PluginBase;

abstract class LinkGeneratorBase extends PluginBase implements LinkGeneratorInterface {

  private $entity_paths = array();

  /**
   * {@inheritdoc}
   */
  public function get_entity_paths($entity_type, $bundles) {
    $i = 0;
    foreach($bundles as $bundle => $bundle_settings) {
      if (!$bundle_settings['index'])
        continue;
      foreach ($this->get_entity_bundle_paths($bundle) as $id => $path) {
        $this->entity_paths[$i]['path'] = $path['path'];
        $this->entity_paths[$i]['priority'] = !empty($path['priority']) ? $path['priority'] : $bundle_settings['priority'];
        $this->entity_paths[$i]['lastmod'] = !empty($path['lastmod']) ? date_iso8601($path['lastmod']) : NULL;
        $i++;
      }
    }
    return $this->entity_paths;
  }

  /**
   * Returns an array of all urls to this bundle.
   *
   * @param string $bundle
   *  Machine name of the bundle, eg. 'page'.
   *
   * @return array $paths
   *  A numeric array of Drupal internal paths like node/1/edit or user/1
   *
   * @abstract
   */
  abstract function get_entity_bundle_paths($bundle);
}
