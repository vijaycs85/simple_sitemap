<?php

namespace Drupal\simple_sitemap;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
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

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

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
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapType\SitemapTypeManager $sitemap_type_manager
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\UrlGeneratorManager $url_generator_manager
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager $sitemap_generator_manager
   * @param \Drupal\simple_sitemap\SimplesitemapSettings $settings
   */
  public function __construct(
    ConfigFactory $config_factory,
    Connection $database,
    SitemapTypeManager $sitemap_type_manager,
    UrlGeneratorManager $url_generator_manager,
    SitemapGeneratorManager $sitemap_generator_manager,
    SimplesitemapSettings $settings
  ) {
    $this->configFactory = $config_factory;
    $this->db = $database;
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
    }
    else {
      $variants = $this->configFactory->get("simple_sitemap.variants.$sitemap_type")->get('variants');
      $variants = is_array($variants) ? $variants : [];
      $variants = $attach_type_info ? $this->attachSitemapTypeToVariants($variants, $sitemap_type) : $variants;
    }
    array_multisort(array_column($variants, "weight"), SORT_ASC, $variants);
    return $variants;
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
   */
  public function addSitemapVariant($name, $definition = []) {
    $all_variants = $this->getSitemapVariants();
    if (isset($all_variants[$name])) {
      $old_variant = $all_variants[$name];
      if (!empty($definition['type']) && $old_variant['type'] !== $definition['type']) {
        $this->removeSitemapVariants($name);
        unset($old_variant);
      }
      else {
        unset($old_variant['type']);
      }
    }

    if (!isset($old_variant) && empty($definition['label'])) {
      $definition['label'] = (string) $name;
    }

    if (!isset($old_variant) && empty($definition['type'])) {
      $definition['type'] = self::DEFAULT_SITEMAP_TYPE;
    }

    if (isset($definition['weight'])) {
      $definition['weight'] = (int) $definition['weight'];
    }
    elseif (!isset($old_variant)) {
      $definition['weight'] = 0;
    }

    if (isset($old_variant)) {
      $definition = $definition + $old_variant;
    }

    $variants = array_merge($this->getSitemapVariants($definition['type'], FALSE), [$name => ['label' => $definition['label'], 'weight' => $definition['weight']]]);
    $this->configFactory->getEditable('simple_sitemap.variants.' . $definition['type'])
      ->set('variants', $variants)
      ->save();

    return $this;
  }

  public function removeSitemapVariants($variant_names = NULL) {
    if (NULL === $variant_names || !empty((array) $variant_names)) {
      SitemapGeneratorBase::removeSitemapVariants($variant_names); //todo should call the remove() method of every plugin instead?

      if (NULL === $variant_names) {
        // Remove all variants and their bundle settings.
        foreach(['variants', 'bundle_settings', 'custom_links'] as $config_name_part) {
          foreach ($this->configFactory->listAll("simple_sitemap.$config_name_part.") as $config_name) {
            $this->configFactory->getEditable($config_name)->delete();
          }
        }
      }
      else {
        // Remove bundle settings for specific variants.
        foreach ((array) $variant_names as $variant_name) {
          foreach ($this->configFactory->listAll("simple_sitemap.bundle_settings.$variant_name.") as $config_name) {
            $this->configFactory->getEditable($config_name)->delete();
          }
        }

        // Remove custom links for specific variants.
        foreach ((array) $variant_names as $variant_name) {
          foreach ($this->configFactory->listAll("simple_sitemap.custom_links.$variant_name") as $config_name) {
            $this->configFactory->getEditable($config_name)->delete();
          }
        }

        // Remove specific variants from configuration.
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
      }

      // Remove bundle setting overrides for entities.
      $query = $this->db->delete('simple_sitemap_entity_overrides');
      if (NULL !== $variant_names) {
        $query->condition('type', (array) $variant_names, 'IN');
      }
      $query->execute();


      // Remove default variant setting.
      if (NULL === $variant_names
        || in_array($this->settings->getSetting('default_variant', ''), (array) $variant_names)) {
        $this->settings->saveSetting('default_variant', '');
      }
    }

    return $this;
  }
}
