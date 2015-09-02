<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\Controller\SimplesitemapController.
 */

namespace Drupal\simplesitemap\Controller;

use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Url;

/**
 * SimplesitemapController.
 */
class SimplesitemapController {

  /**
   * Generates an example page.
   */
  public function generate_sitemap() {

    $config = \Drupal::config('simplesitemap.settings');
    $content_types = $config->get('content_types');

//    $config->set('content_types', array('blog'));
//    $config->save();

    $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
    <urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">";

    global $base_url;

    $home = $config->get('home');
    if ($home['index']) {
      $output .= "<url><loc>" . $base_url . "</loc><priority>" . $home['priority'] .  "</priority></url>";
    }

    $custom = $config->get('custom');
    foreach($custom as $page) {
      if ($page['index']) {
        $output .= "<url><loc>" . $base_url . '/' . $page['path'] . "</loc><priority>" . $page['priority'] . "</priority></url>";
      }
    }
    if (count($content_types) > 0) {

      //todo: D8 entityQuery doesn't seem to take multiple OR conditions, that's why that ugly db_select.
  /*    $query = \Drupal::entityQuery('node')
        ->condition('status', 1)
        ->condition('type', array_keys($content_types));
      $nids = $query->execute();*/
      $query = db_select('node_field_data', 'n')
        ->fields('n', array('nid', 'type'))
        ->condition('status', 1);
      $db_or = db_or();
      foreach($content_types as $machine_name => $options) {
        $db_or->condition('type', $machine_name);
      }
      $query->condition($db_or);
      $nids = $query->execute()->fetchAllAssoc('nid');

      foreach($nids as $nid => $node) {
        $link_url = Url::fromRoute('entity.node.canonical', array('node' => $nid), array('absolute' => TRUE));
        $output .= "<url><loc>" . $link_url->toString() . "</loc><priority>" . $content_types[$node->type]['priority'] .  "</priority></url>";
      }
    }

    $output .= "</urlset>";

    return new Response($output, Response::HTTP_OK, array('content-type' => 'application/xml'));
  }
}
