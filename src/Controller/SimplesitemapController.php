<?php

namespace Drupal\simple_sitemap\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Cache\CacheableResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;

/**
 * Class SimplesitemapController
 * @package Drupal\simple_sitemap\Controller
 */
class SimplesitemapController extends ControllerBase {

  /**
   * @var \Drupal\simple_sitemap\Simplesitemap
   */
  protected $generator;

  /**
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $cacheKillSwitch;

  /**
   * SimplesitemapController constructor.
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $cache_kill_switch
   */
  public function __construct(Simplesitemap $generator, KillSwitch $cache_kill_switch) {
    $this->generator = $generator;
    $this->cacheKillSwitch = $cache_kill_switch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simple_sitemap.generator'),
      $container->get('page_cache_kill_switch')
    );
  }

  /**
   * Returns the whole sitemap variant, its requested chunk,
   * or its sitemap index file.
   * Caches the response in case of expected output, prevents caching otherwise.
   *
   * @param string $variant
   *  Optional name of sitemap variant.
   *  @see \hook_simple_sitemap_variants_alter()
   *  @see Simplesitemap::getSitemapVariants()
   *
   * @param int $delta
   *  Optional delta of the sitemap chunk. If none provided, the first chunk or
   *  the sitemap index is fetched.
   *
   * @throws NotFoundHttpException
   *
   * @return object
   *  Returns an XML response.
   */
  public function getSitemap($variant = Simplesitemap::DEFAULT_SITEMAP_VARIANT, $delta = NULL) {
    $output = $this->generator->getSitemap($variant, $delta);
    if (!$output) {
      $this->cacheKillSwitch->trigger();
      throw new NotFoundHttpException();
    }

    $response = new CacheableResponse($output, Response::HTTP_OK, [
      'content-type' => 'application/xml',
      'X-Robots-Tag' => 'noindex', // Tell search engines not to index the sitemap itself.
    ]);

    // Cache output.
    $response->getCacheableMetadata()->addCacheTags(["simple_sitemap:$variant"]);

    return $response;
  }
}
