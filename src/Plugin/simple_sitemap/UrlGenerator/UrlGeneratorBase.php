<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\simple_sitemap\Plugin\simple_sitemap\SimplesitemapPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Url;
use Drupal\simple_sitemap\EntityHelper;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorBase;

/**
 * Class UrlGeneratorBase
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 */
abstract class UrlGeneratorBase extends SimplesitemapPluginBase implements UrlGeneratorInterface {

  const ANONYMOUS_USER_ID = 0;
  const PROCESSING_PATH_MESSAGE = 'Processing path #@current out of @max: @path';

  /**
   * @var \Drupal\simple_sitemap\Simplesitemap
   */
  protected $generator;

  /**
   * @var \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager
   */
  protected $sitemapGeneratorManager;

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
  protected $settings;

  /**
   * @var array
   */
  protected $batchMeta;

  /**
   * @var \Drupal\simple_sitemap\EntityHelper
   */
  protected $entityHelper;

  /**
   * @var string
   */
  protected $sitemapGeneratorId;

  /**
   * @var string
   */
  protected $sitemapVariant;

  /**
   * UrlGeneratorBase constructor.
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   * @param \Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorManager $sitemap_generator_manager
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
    SitemapGeneratorManager $sitemap_generator_manager,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    Logger $logger,
    EntityHelper $entityHelper
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->generator = $generator;
    $this->sitemapGeneratorManager = $sitemap_generator_manager;
    $this->languageManager = $language_manager;
    $this->languages = $language_manager->getLanguages();
    $this->defaultLanguageId = $language_manager->getDefaultLanguage()->getId();
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->entityHelper = $entityHelper;
    $this->anonUser = $this->entityTypeManager->getStorage('user')
      ->load(self::ANONYMOUS_USER_ID);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.generator'),
      $container->get('plugin.manager.simple_sitemap.sitemap_generator'),
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
   * @param array $settings
   * @return $this
   */
  public function setSettings(array $settings) {
    $this->settings = $settings;
    return $this;
  }

  /**
   * @param array $batch_meta
   * @return $this
   */
  public function setBatchMeta(array $batch_meta) {
    $this->batchMeta = $batch_meta;
    return $this;
  }

  /**
   * @param string $sitemap_generator_id
   * @return $this
   */
  public function setSitemapGeneratorId($sitemap_generator_id) {
    $this->sitemapGeneratorId = $sitemap_generator_id;
    return $this;
  }

  /**
   * @param string $sitemap_variant
   * @return $this
   */
  public function setSitemapVariant($sitemap_variant) {
    $this->sitemapVariant = $sitemap_variant;
    return $this;
  }

  /**
   * @return array
   */
  protected function getProcessedElements() {
    return !empty($this->context['results']['processed_paths'][$this->sitemapVariant])
      ? $this->context['results']['processed_paths'][$this->sitemapVariant]
      : [];
  }

  protected function addProcessedElement($element) {
    $this->context['results']['processed_paths'][$this->sitemapVariant][] = $element;

    // Clean up duplicate data of processed sitemap types to save memory.
    if (count($this->context['results']['processed_paths']) > 1) {
      reset($this->context['results']['processed_paths']);
      unset($this->context['results']['processed_paths'][key($this->context['results']['processed_paths'])]);
    }
  }

  protected function getBatchResultQueue() {
    return !empty($this->context['results']['generate'])
      ? $this->context['results']['generate']
      : [];
  }

  protected function addBatchResultToQueue($result) {
    $this->context['results']['generate'][$this->sitemapVariant]['sitemap_generator_id'] = $this->sitemapGeneratorId;
    $this->context['results']['generate'][$this->sitemapVariant]['queued_links'][] = $result;
  }

  protected function sliceFromBatchResultQueue($sitemap_variant, $number_results) {
    $this->context['results']['generate'][$sitemap_variant]['queued_links'] = array_slice(
      $this->context['results']['generate'][$sitemap_variant]['queued_links'], $number_results
    );
    if (empty($this->context['results']['generate'][$sitemap_variant]['queued_links'])) {
      unset($this->context['results']['generate'][$sitemap_variant]);
    }
  }

  /**
   * @return bool
   */
  protected function isDrupalBatch() {
    return $this->batchMeta['from'] !== 'nobatch';
  }

  /**
   * @return bool
   */
  protected function batchOperationNeedsInitialization() {
    return $this->isDrupalBatch() && empty($this->context['sandbox']);
  }

  /**
   * Initialize sandbox for the batch process.
   *
   * @param $max
   */
  protected function initializeBatchOperation($max) {
    $this->context['sandbox']['progress'] = 0;
    $this->context['sandbox']['current_id'] = NULL;
    $this->context['sandbox']['max'] = $max;
    $this->context['sandbox']['finished'] = 0;
  }

  /**
   * @param $id
   */
  protected function setCurrentId($id) {
    if ($this->isDrupalBatch()) {
      $this->context['sandbox']['progress']++;
      $this->context['sandbox']['current_id'] = $id;
    }
  }

  /**
   * @param string $path
   * @return bool
   */
  protected function pathProcessed($path) {
    if (in_array($path, $this->getProcessedElements())) {
      return TRUE;
    }
    $this->addProcessedElement($path);
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
      $this->addBatchResultToQueue($path_data);
    }
  }

  /**
   * @param Url $url_object
   * @param array $path_data
   */
  protected function addUrlVariants(array $path_data, Url $url_object) {

    if (!$url_object->isRouted()) {
      // Not a routed URL, including only default variant.
      $alternate_urls = $this->getAlternateUrlsForDefaultLanguage($url_object);
    }
    elseif ($this->settings['skip_untranslated']
      && ($entity = $this->entityHelper->getEntityFromUrlObject($url_object)) instanceof ContentEntityBase) {

      /** @var ContentEntityBase $entity */
      $translation_languages = $entity->getTranslationLanguages();
      if (isset($translation_languages[Language::LANGCODE_NOT_SPECIFIED])
        || isset($translation_languages[Language::LANGCODE_NOT_APPLICABLE])) {

        // Content entity's language is unknown, including only default variant.
        $alternate_urls = $this->getAlternateUrlsForDefaultLanguage($url_object);
      }
      else {
        // Including only translated variants of content entity.
        $alternate_urls = $this->getAlternateUrlsForTranslatedLanguages($entity, $url_object);
      }
    }
    else {
      // Not a content entity or including all untranslated variants.
      $alternate_urls = $this->getAlternateUrlsForAllLanguages($url_object);
    }

    foreach ($alternate_urls as $langcode => $url) {
      $this->addBatchResultToQueue(
        $path_data + [
          'langcode' => $langcode, 'url' => $url, 'alternate_urls' => $alternate_urls
        ]
      );
    }
  }

  protected function getAlternateUrlsForDefaultLanguage(Url $url_object) {
    $alternate_urls = [];
    if ($url_object->access($this->anonUser)) {
      $url_object->setOption('language', $this->languages[$this->defaultLanguageId]);
      $alternate_urls[$this->defaultLanguageId] = $this->replaceBaseUrlWithCustom($url_object->toString());
    }
    return $alternate_urls;
  }

  protected function getAlternateUrlsForTranslatedLanguages(ContentEntityBase $entity, Url $url_object) {
    $alternate_urls = [];
    foreach ($entity->getTranslationLanguages() as $language) {

      /** @var Language $language */
      if (!isset($this->settings['excluded_languages'][$language->getId()]) || $language->isDefault()) {
        $translation = $entity->getTranslation($language->getId());
        if ($translation->access('view', $this->anonUser)) {
          $url_object->setOption('language', $language);
          $alternate_urls[$language->getId()] = $this->replaceBaseUrlWithCustom($url_object->toString());
        }
      }
    }
    return $alternate_urls;
  }

  protected function getAlternateUrlsForAllLanguages(Url $url_object) {
    $alternate_urls = [];
    if ($url_object->access($this->anonUser)) {
      foreach ($this->languages as $language) {
        if (!isset($this->settings['excluded_languages'][$language->getId()]) || $language->isDefault()) {
          $url_object->setOption('language', $language);
          $alternate_urls[$language->getId()] = $this->replaceBaseUrlWithCustom($url_object->toString());
        }
      }
    }
    return $alternate_urls;
  }

  protected function processSegment() {
    $this->setBatchProgressInfo();

    // If this is not a batch operation, enter the generation process only if
    // all links from all operations have been queued. If it is a batch
    // operation, generate the required amount of links in this batch process
    // segment before the generation process.
    if ($this->isDrupalBatch() || $this->batchMeta['current_generate_sitemap_operation_no']
      == $this->batchMeta['last_generate_sitemap_operation_no']) {
      foreach ($this->getBatchResultQueue() as $sitemap_variant => $queue_data) {

        /** @var SitemapGeneratorBase $sitemap_generator */
        $sitemap_generator = $this->sitemapGeneratorManager
          ->createInstance($queue_data['sitemap_generator_id'])
          ->setSitemapVariant($sitemap_variant)
          ->setSettings(['excluded_languages' => $this->settings['excluded_languages']]);

        foreach (array_chunk($queue_data['queued_links'], $this->settings['max_links']) as $chunk_links) {

          // Generating chunk from all links of this sitemap type if this is not a batch operation.
          if (!$this->isDrupalBatch()

            // If this is batch, generating chunk in case the required amount of
            // links for this chunk have been queued.
            || count($chunk_links) == $this->settings['max_links']

            // If this is batch, also generating in case of fewer links than
            // defined per chunk, but only if these are the last links of this
            // sitemap type.
            || (count($chunk_links) < $this->settings['max_links'] && count($this->getBatchResultQueue()) > 1)

            // If this is batch, also generating in case this is the last batch
            // segment of the last batch operation.
            || (
              $this->batchMeta['current_generate_sitemap_operation_no']
              == $this->batchMeta['last_generate_sitemap_operation_no']
              && $this->context['finished'] >= 1
            )
          ) {

            // Generate sitemap chunk.
            $sitemap_generator->generate($chunk_links);

            // Remove links from result array that have been generated.
            $this->sliceFromBatchResultQueue($sitemap_variant, count($chunk_links));
          }
        }
      }
    }
  }

  protected function setBatchProgressInfo() {
    if ($this->isDrupalBatch() &&
      $this->context['sandbox']['progress'] != $this->context['sandbox']['max']) {

      // Provide progress info to the batch API.
      $this->context['finished'] = $this->context['sandbox']['progress'] / $this->context['sandbox']['max'];

      // Add processing message after finishing every batch segment.
      $this->setProcessingBatchMessage();
    }
  }

  protected function setProcessingBatchMessage() {
    $results = $this->getBatchResultQueue();
    end($results);
    $sitemap_type_results = $results[key($results)]['queued_links'];
    end($sitemap_type_results);
    if (!empty($path = $sitemap_type_results[key($sitemap_type_results)]['meta']['path'])) {
      $this->context['message'] = $this->t(self::PROCESSING_PATH_MESSAGE, [
        '@current' => $this->context['sandbox']['progress'],
        '@max' => $this->context['sandbox']['max'],
        '@path' => Html::escape($path),
      ]);
    }
  }

  /**
   * @param string $url
   * @return string
   */
  protected function replaceBaseUrlWithCustom($url) {
    return !empty($this->settings['base_url'])
      ? str_replace($GLOBALS['base_url'], $this->settings['base_url'], $url)
      : $url;
  }

  /**
   * @param mixed $elements
   * @return array
   */
  protected function getBatchIterationElements($elements) {
    if ($this->batchOperationNeedsInitialization()) {
      $this->initializeBatchOperation(count($elements));
    }

    return $this->isDrupalBatch()
      ? array_slice($elements, $this->context['sandbox']['progress'], $this->settings['batch_process_limit'])
      : $elements;
  }

  /**
   * @return mixed
   */
  abstract public function getDataSets();

  /**
   * @param $data_set
   * @return array
   */
  abstract protected function processDataSet($data_set);

  /**
   * Called by batch.
   *
   * @param mixed $data_sets
   */
  public function generate($data_sets) {
    foreach ($this->getBatchIterationElements($data_sets) as $id => $data_set) {
      $this->setCurrentId($id);
      $path_data = $this->processDataSet($data_set);
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
    foreach ($this->entityHelper->getEntityImageUrls($entity_type_name, $entity_id) as $url) {
      $images[]['path'] = $this->replaceBaseUrlWithCustom($url);
    }
    return $images;
  }
}
