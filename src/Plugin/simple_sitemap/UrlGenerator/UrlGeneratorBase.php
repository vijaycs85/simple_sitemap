<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\simple_sitemap\EntityHelper;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\simple_sitemap\SitemapGenerator;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class UrlGeneratorBase
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 */
abstract class UrlGeneratorBase extends PluginBase implements PluginInspectionInterface, ContainerFactoryPluginInterface, UrlGeneratorInterface {

  use StringTranslationTrait;

  const ANONYMOUS_USER_ID = 0;
  const PROCESSING_PATH_MESSAGE = 'Processing path #@current out of @max: @path';

  /**
   * @var \Drupal\simple_sitemap\Simplesitemap
   */
  protected $generator;

  /**
   * @var \Drupal\simple_sitemap\SitemapGenerator
   */
  protected $sitemapGenerator;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * @var \Drupal\Core\Language\LanguageInterface[]
   */
  protected $languages;

  /**
   * @var string
   */
  protected $defaultLanguageId;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\simple_sitemap\Logger
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $anonUser;

  /**
   * @var array
   */
  protected $context;

  /**
   * @var array
   */
  protected $batchSettings;

  /**
   * @var \Drupal\simple_sitemap\EntityHelper
   */
  protected $entityHelper;

  /**
   * UrlGeneratorBase constructor.
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   * @param \Drupal\simple_sitemap\SitemapGenerator $sitemap_generator
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\simple_sitemap\Logger $logger
   * @param \Drupal\simple_sitemap\EntityHelper $entityHelper
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Simplesitemap $generator,
    SitemapGenerator $sitemap_generator,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    Logger $logger,
    EntityHelper $entityHelper
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->generator = $generator;
    $this->sitemapGenerator = $sitemap_generator;
    $this->languageManager = $language_manager;
    $this->languages = $language_manager->getLanguages();
    $this->defaultLanguageId = $language_manager->getDefaultLanguage()->getId();
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->entityHelper = $entityHelper;
    $this->anonUser = $this->entityTypeManager->getStorage('user')
      ->load(self::ANONYMOUS_USER_ID);
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.generator'),
      $container->get('simple_sitemap.sitemap_generator'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('simple_sitemap.logger'),
      $container->get('simple_sitemap.entity_helper')
    );
  }

  /**
   * @param $context
   * @return $this
   */
  public function setContext(&$context) {
    $this->context = &$context;
    return $this;
  }

  /**
   * @param array $batch_settings
   * @return $this
   */
  public function setBatchSettings(array $batch_settings) {
    $this->batchSettings = $batch_settings;
    return $this;
  }

  /**
   * @return bool
   */
  protected function isBatch() {
    return $this->batchSettings['from'] != 'nobatch';
  }

  /**
   * @param string $path
   * @return bool
   */
  protected function pathProcessed($path) {
    $path_pool = isset($this->context['results']['processed_paths'])
      ? $this->context['results']['processed_paths']
      : [];
    if (in_array($path, $path_pool)) {
      return TRUE;
    }
    $this->context['results']['processed_paths'][] = $path;
    return FALSE;
  }

  /**
   * @param array $path_data
   */
  protected function addUrl(array $path_data) {
    if ($path_data['url'] instanceof Url) {
      $url_object = $path_data['url'];
      unset($path_data['url']);
      $this->addUrlVariants($path_data, $url_object);
    }
    else {
      $this->context['results']['generate'][] = $path_data;
    }
  }

  /**
   * @param Url $url_object
   * @param array $path_data
   */
  protected function addUrlVariants(array $path_data, Url $url_object) {
    $alternate_urls = [];
    $entity = $this->entityHelper->getEntityFromUrlObject($url_object);
    $translation_languages = $entity instanceof ContentEntityBase && $this->batchSettings['skip_untranslated']
      ? $entity->getTranslationLanguages()
      : $this->languages;

    // Entity is not translated.
    if ($entity instanceof ContentEntityBase && isset($translation_languages['und'])) {
      if ($url_object->access($this->anonUser)) {
        $url_object->setOption('language', $this->languages[$this->defaultLanguageId]);
        $alternate_urls[$this->defaultLanguageId] = $this->replaceBaseUrlWithCustom($url_object->toString());
      }
    }
    else {
      // Including only translated variants of content entity.
      if ($entity instanceof ContentEntityBase && $this->batchSettings['skip_untranslated']) {
        foreach ($translation_languages as $language) {
          if (!isset($this->batchSettings['excluded_languages'][$language->getId()]) || $language->isDefault()) {
            $translation = $entity->getTranslation($language->getId());
            if ($translation->access('view', $this->anonUser)) {
              $url_object->setOption('language', $language);
              $alternate_urls[$language->getId()] = $this->replaceBaseUrlWithCustom($url_object->toString());
            }
          }
        }
      }

      // Not a content entity or including all untranslated variants.
      elseif ($url_object->access($this->anonUser)) {
        foreach ($translation_languages as $language) {
          if (!isset($this->batchSettings['excluded_languages'][$language->getId()]) || $language->isDefault()) {
            $url_object->setOption('language', $language);
            $alternate_urls[$language->getId()] = $this->replaceBaseUrlWithCustom($url_object->toString());
          }
        }
      }
    }

    foreach ($alternate_urls as $langcode => $url) {
      $this->context['results']['generate'][] = $path_data + [
        'langcode' => $langcode, 'url' => $url, 'alternate_urls' => $alternate_urls];
    }
  }

  /**
   * @return bool
   */
  protected function needsInitialization() {
    return empty($this->context['sandbox']);
  }

  /**
   * @param $max
   */
  protected function initializeBatch($max) {
    $this->context['results']['generate'] = !empty($this->context['results']['generate']) ? $this->context['results']['generate'] : [];
    if ($this->isBatch()) {
      $this->context['sandbox']['progress'] = 0;
      $this->context['sandbox']['current_id'] = 0;
      $this->context['sandbox']['max'] = $max;
      $this->context['results']['processed_paths'] = !empty($this->context['results']['processed_paths'])
        ? $this->context['results']['processed_paths']
        : [];
    }
  }

  /**
   * @param $id
   */
  protected function setCurrentId($id) {
    if ($this->isBatch()) {
      $this->context['sandbox']['progress']++;
      $this->context['sandbox']['current_id'] = $id;
    }
  }

  /**
   *
   */
  protected function processSegment() {
    if ($this->isBatch()) {
      $this->setProgressInfo();
    }
    if (!empty($this->batchSettings['max_links']) && count($this->context['results']['generate']) >= $this->batchSettings['max_links']) {
      $chunks = array_chunk($this->context['results']['generate'], $this->batchSettings['max_links']);
      foreach ($chunks as $i => $chunk_links) {
        if (count($chunk_links) == $this->batchSettings['max_links']) {
          $remove_sitemap = empty($this->context['results']['chunk_count']);
          $this->sitemapGenerator->generateSitemap($chunk_links, $remove_sitemap);
          $this->context['results']['chunk_count'] = !isset($this->context['results']['chunk_count'])
            ? 1
            : $this->context['results']['chunk_count'] + 1;
          $this->context['results']['generate'] = array_slice($this->context['results']['generate'], count($chunk_links));
        }
      }
    }
  }

  /**
   *
   */
  protected function setProgressInfo() {
    if ($this->context['sandbox']['progress'] != $this->context['sandbox']['max']) {
      // Providing progress info to the batch API.
      $this->context['finished'] = $this->context['sandbox']['progress'] / $this->context['sandbox']['max'];
      // Adding processing message after finishing every batch segment.
      end($this->context['results']['generate']);
      $last_key = key($this->context['results']['generate']);
      if (!empty($this->context['results']['generate'][$last_key]['path'])) {
        $this->context['message'] = $this->t(self::PROCESSING_PATH_MESSAGE, [
          '@current' => $this->context['sandbox']['progress'],
          '@max' => $this->context['sandbox']['max'],
          '@path' => HTML::escape($this->context['results']['generate'][$last_key]['path']),
        ]);
      }
    }
  }

  /**
   * @param string $url
   * @return string
   */
  protected function replaceBaseUrlWithCustom($url) {
    return !empty($this->batchSettings['base_url'])
      ? str_replace($GLOBALS['base_url'], $this->batchSettings['base_url'], $url)
      : $url;
  }

  /**
   * @param array $elements
   * @return array
   */
  protected function getBatchIterationElements(array $elements) {
    if ($this->needsInitialization()) {
      $this->initializeBatch(count($elements));
    }

    return $this->isBatch()
      ? array_slice($elements, $this->context['sandbox']['progress'], $this->batchSettings['batch_process_limit'])
      : $elements;
  }

  /**
   * @return mixed
   */
  abstract protected function getData();

  /**
   * @param $path_data
   * @return array
   */
  abstract protected function getPathData($path_data);

  /**
   * Called by batch.
   */
  public function generate() {
    foreach ($this->getBatchIterationElements($this->getData()) as $id => $data) {
      $this->setCurrentId($id);
      $path_data = $this->getPathData($data);
      if (!$path_data) {
        continue;
      }
      $this->addUrl($path_data);
    }
    $this->processSegment();
  }

  /**
   * @param $entity_type_name
   * @param $entity_id
   * @return array
   */
  protected function getImages($entity_type_name, $entity_id) {
    $images = [];
    foreach ($this->entityHelper->getEntityImageUrls($entity_type_name, $entity_id) as $Url) {
      $images[]['path'] = $this->replaceBaseUrlWithCustom($Url);
    }
    return $images;
  }
}
