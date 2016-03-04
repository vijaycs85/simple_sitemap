<?php
/**
 * @file
 * Contains \Drupal\simplesitemap\Controller\SimplesitemapController.
 */

namespace Drupal\simplesitemap\Controller;

use Drupal\Core\Cache\CacheableResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\simplesitemap\Simplesitemap;

/**
 * SimplesitemapController.
 */
class SimplesitemapController {

  /**
   * Returns the whole sitemap, a requested sitemap chunk, or the sitemap index file.
   *
   * @param int $sitemap_id
   *  Id of the sitemap chunk.
   *
   * @return object Response
   *  Returns an XML response.
   */
  public function get_sitemap($sitemap_id = NULL) {
    $sitemap = new Simplesitemap;
    $output = $sitemap->get_sitemap($sitemap_id);

    // Display sitemap with correct xml header.
    $response = new CacheableResponse($output, Response::HTTP_OK, array('content-type' => 'application/xml'));
    $meta_data = $response->getCacheableMetadata();
    $meta_data->addCacheTags(['simplesitemap']);
    return $response;
  }
}
