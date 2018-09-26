<?php

namespace Drupal\simple_sitemap\Plugin\LanguageNegotiation;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Symfony\Component\HttpFoundation\Request;

/**
 * {@inheritdoc}
 */
class SimplesitemapLanguageNegotiationUrl extends LanguageNegotiationUrl {

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    $config = $this->config->get('language.negotiation')->get('url');
    if ($config['source'] === LanguageNegotiationUrl::CONFIG_PATH_PREFIX) {
      $args = explode('/', $path);
      if (count($args) === 4 && $args[1] === 'sitemaps' && $args[3] === 'sitemap.xml') {
        return $path;
      }
    }

    return parent::processOutbound($path, $options, $request, $bubbleable_metadata);
  }
}
