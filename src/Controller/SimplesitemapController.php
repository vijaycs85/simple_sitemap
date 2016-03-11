<?php
/**
 * @file
 * Contains \Drupal\simple_sitemap\Controller\SimplesitemapController.
 */

namespace Drupal\simple_sitemap\Controller;

use Drupal\Core\Cache\CacheableResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * SimplesitemapController.
 */
class SimplesitemapController {

  /**
   * Returns the whole sitemap, a requested sitemap chunk, or the sitemap index file.
   *
   * @param int $sitemap_id
   *  Optional ID of the sitemap chunk. If none provided, the first chunk or
   *  the sitemap index is fetched.
   *
   * @return object Response
   *  Returns an XML response.
   */
  public function getSitemap($sitemap_id = NULL) {
    $sitemap = \Drupal::service('simple_sitemap.generator');
    $output = $sitemap->getSitemap($sitemap_id);
    $output = !$output ? '' : $output;

    // Display sitemap with correct xml header.
    $response = new CacheableResponse($output, Response::HTTP_OK, array('content-type' => 'application/xml'));
    $meta_data = $response->getCacheableMetadata();
    $meta_data->addCacheTags(['simple_sitemap']);
    return $response;
  }
}
