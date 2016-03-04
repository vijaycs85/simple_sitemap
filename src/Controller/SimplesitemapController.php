<?php
/**
 * @file
 * Contains \Drupal\simple_sitemap\Controller\SimplesitemapController.
 */

namespace Drupal\simple_sitemap\Controller;

//use Drupal\Core\Cache\CacheableResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\simple_sitemap\Simplesitemap;

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
  public function get_sitemap($sitemap_id = NULL) {
    $sitemap = new Simplesitemap;
    $output = $sitemap->get_sitemap($sitemap_id);
    $output = !$output ? '' : $output;

    // Display sitemap with correct xml header.
//    return new CacheableResponse($output, Response::HTTP_OK, array('content-type' => 'application/xml'));
    return new Response($output, Response::HTTP_OK, array('content-type' => 'application/xml'));
  }
}
