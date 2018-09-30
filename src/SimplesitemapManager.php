<?php

namespace Drupal\simple_sitemap;

use Drupal\Core\Config\ConfigFactory;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapType\SitemapTypeBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapType\SitemapTypeManager;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorManager;

/**
 * Class SimplesitemapManager
 * @package Drupal\simple_sitemap
 */
class SimplesitemapManager {

  const DEFAULT_SITEMAP_TYPE = 'default_hreflang';
  const DEFAULT_SITEMAP_GENERATOR = 'default';
  const DEFAULT_SITEMAP_VARIANT = 'default';

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapType\SitemapTypeManager
   */
  protected $sitemapTypeManager;

  /**
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorManager
   */
  protected $urlGeneratorManager;

  /**
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager
   */
  protected $sitemapGeneratorManager;

  /**
   * @var \Drupal\simple_sitemap\SimplesitemapSettings
   */
  protected $settings;

  /**
   * @var SitemapTypeBase[] $sitemapTypes
   */
  protected $sitemapTypes = [];

  /**
   * @var UrlGeneratorBase[] $urlGenerators
   */
  protected $urlGenerators = [];

  /**
   * @var SitemapGeneratorBase[] $sitemapGenerators
   */
  protected $sitemapGenerators = [];

  /**
   * SimplesitemapManager constructor.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapType\SitemapTypeManager $sitemap_type_manager
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorManager $url_generator_manager
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager $sitemap_generator_manager
   * @param \Drupal\simple_sitemap\SimplesitemapSettings $settings
   */
  public function __construct(
    ConfigFactory $config_factory,
    SitemapTypeManager $sitemap_type_manager,
    UrlGeneratorManager $url_generator_manager,
    SitemapGeneratorManager $sitemap_generator_manager,
    SimplesitemapSettings $settings
  ) {
    $this->configFactory = $config_factory;
    $this->sitemapTypeManager = $sitemap_type_manager;
    $this->urlGeneratorManager = $url_generator_manager;
    $this->sitemapGeneratorManager = $sitemap_generator_manager;
    $this->settings = $settings;
  }

  /**
   * @param $sitemap_generator_id
   * @return \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorBase
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getSitemapGenerator($sitemap_generator_id) {
    if (!isset($this->sitemapGenerators[$sitemap_generator_id])) {
      $this->sitemapGenerators[$sitemap_generator_id]
        = $this->sitemapGeneratorManager->createInstance($sitemap_generator_id);
    }

    return $this->sitemapGenerators[$sitemap_generator_id];
  }

  /**
   * @param $url_generator_id
   * @return \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorBase
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getUrlGenerator($url_generator_id) {
    if (!isset($this->urlGenerators[$url_generator_id])) {
      $this->urlGenerators[$url_generator_id]
        = $this->urlGeneratorManager->createInstance($url_generator_id);
    }

    return $this->urlGenerators[$url_generator_id];
  }

  /**
   * @return array
   */
  public function getSitemapTypes() {
    if (empty($this->sitemapTypes)) {
      $this->sitemapTypes = $this->sitemapTypeManager->getDefinitions();
    }

    return $this->sitemapTypes;
  }

  /**
   * @param null $sitemap_type
   * @return array
   *
   * @todo document
   * @todo translate label
   */
  public function getSitemapVariants($sitemap_type = NULL, $attach_type_info = TRUE) {
    if (NULL === $sitemap_type) {
      $variants = [];
      foreach ($this->configFactory->listAll('simple_sitemap.variants.') as $config_name) {
        $config_name_parts = explode('.', $config_name);
        $saved_variants = $this->configFactory->get($config_name)->get('variants');
        $saved_variants = $attach_type_info ? $this->attachSitemapTypeToVariants($saved_variants, $config_name_parts[2]) : $saved_variants;
        $variants = array_merge($variants, (is_array($saved_variants) ? $saved_variants : []));
      }
      array_multisort(array_column($variants, "weight"), SORT_ASC, $variants);
      return $variants;
    }
    else {
      $variants = $this->configFactory->get("simple_sitemap.variants.$sitemap_type")->get('variants');
      $variants = is_array($variants) ? $variants : [];
      $variants = $attach_type_info ? $this->attachSitemapTypeToVariants($variants, $sitemap_type) : $variants;
      array_multisort(array_column($variants, "weight"), SORT_ASC, $variants);
      return $variants;
    }
  }

  protected function attachSitemapTypeToVariants(array $variants, $type) {
    return array_map(function($variant) use ($type) { return $variant + ['type' => $type]; }, $variants);
  }

  protected function detachSitemapTypeFromVariants(array $variants) {
    return array_map(function($variant) { unset($variant['type']); return $variant; }, $variants);
  }

  /**
   * @param $name
   * @param $definition
   * @return $this
   *
   * @todo document
   * @todo exceptions
   */
  public function addSitemapVariant($name, $definition = []) {
    if (empty($definition['label'])) {
      $definition['label'] = $name;
    }

    if (empty($definition['type'])) {
      $definition['type'] = self::DEFAULT_SITEMAP_TYPE;
    }
    else {
      $types = $this->getSitemapTypes();
      if (!isset($types[$definition['type']])) {
        // todo: exception
      }
    }

    $definition['weight'] = isset($definition['weight']) ? (int) $definition['weight'] : 0;

    $all_variants = $this->getSitemapVariants();
    if (isset($all_variants[$name])) {
      //todo: exception
    }
    else {
      $variants = array_merge($this->getSitemapVariants($definition['type'], FALSE), [$name => ['label' => $definition['label'], 'weight' => $definition['weight']]]);
      $this->configFactory->getEditable('simple_sitemap.variants.' . $definition['type'])
        ->set('variants', $variants)
        ->save();
    }

    return $this;
  }

  public function removeSitemapVariants($variant_names = NULL) {
    SitemapGeneratorBase::removeSitemapVariants($variant_names); //todo should call the remove() method of every plugin instead?

    if (NULL === $variant_names) {
      foreach ($this->configFactory->listAll('simple_sitemap.variants.') as $config_name) {
        $this->configFactory->getEditable($config_name)->delete();
      }
      $this->settings->saveSetting('default_variant', '');
    }
    else {
      $remove_variants = [];
      $variants = $this->getSitemapVariants();
      foreach ((array) $variant_names as $variant_name) {
        if (isset($variants[$variant_name])) {
          $remove_variants[$variants[$variant_name]['type']][$variant_name] = $variant_name;
        }
      }

      foreach ($remove_variants as $type => $variants_per_type) {
        $this->configFactory->getEditable("simple_sitemap.variants.$type")
          ->set('variants', array_diff_key($this->getSitemapVariants($type, FALSE), $variants_per_type))
          ->save();
      }
      if (in_array($this->settings->getSetting('default_variant', ''), (array) $variant_names)) {
        $this->settings->saveSetting('default_variant', '');
      }
    }

    return $this;
  }
}
