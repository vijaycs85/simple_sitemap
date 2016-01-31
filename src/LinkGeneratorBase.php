<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\LinkGeneratorBase.
 */

namespace Drupal\simplesitemap;

use Drupal\Component\Plugin\PluginBase;

abstract class LinkGeneratorBase extends PluginBase implements LinkGeneratorInterface {

  private $entity_paths = array();
  private $current_entity_type;
  const PLUGIN_ERROR_MESSAGE = "The simplesitemap @plugin plugin has been omitted, as it does not return the required numeric array of path data sets. Each data sets must contain the required path element and optionally other elements, like lastmod.";

  /**
   * {@inheritdoc}
   */
  public function get_entity_paths($entity_type, $bundles) {
    $this->current_entity_type = $entity_type;
    $i = 0;
    foreach($bundles as $bundle => $bundle_settings) {
      if (!$bundle_settings['index'])
        continue;
      $paths = $this->get_entity_bundle_paths($bundle);

      if (!is_array($paths)) { // Some error catching.
        $this->register_error(self::PLUGIN_ERROR_MESSAGE);
        return $this->entity_paths;
      }

      foreach($paths as $id => $path) {
        // Some error catching.
        if (!isset($path['path']) || !is_string($path['path'])) { // Error catching; careful, path can be empty.
          $this->register_error(self::PLUGIN_ERROR_MESSAGE);
          return $this->entity_paths;
        }

        $this->entity_paths[$i]['path'] = $path['path'];
        $this->entity_paths[$i]['priority'] = !empty($path['priority']) ? $path['priority'] : $bundle_settings['priority'];
        $this->entity_paths[$i]['lastmod'] = !empty($path['lastmod']) && (int)$path['lastmod'] == $path['lastmod'] ? date_iso8601($path['lastmod']) : NULL;
        $i++;
      }
    }
    return $this->entity_paths;
  }

  private function register_error($message) {
    $message = str_replace('@plugin', $this->current_entity_type, t($message));
    \Drupal::logger('simplesitemap')->notice($message);
    drupal_set_message($message, 'error');
  }

  /**
   * Returns an array of all urls and their data of a bundle.
   *
   * @param string $bundle
   *  Machine name of the bundle, eg. 'page'.
   *
   * @return array $paths
   *  A numeric array of Drupal internal path data sets containing the path and
   *  lastmod info:
   *  array(
   *    path => 'drupal/internal/path/to/content', // required
   *    lastmod => '1234567890' // content changed unix date, optional
   *  )
   *
   * @abstract
   */
  abstract function get_entity_bundle_paths($bundle);
}
