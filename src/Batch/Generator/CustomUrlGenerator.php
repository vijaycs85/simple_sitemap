<?php

namespace Drupal\simple_sitemap\Batch\Generator;

use Drupal\Core\Url;
use Drupal\simple_sitemap\EntityHelper;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\simple_sitemap\SitemapGenerator;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidator;

/**
 * Class CustomUrlGenerator
 * @package Drupal\simple_sitemap\Batch\Generator
 */
class CustomUrlGenerator extends UrlGeneratorBase implements UrlGeneratorInterface {

  const PATH_DOES_NOT_EXIST_OR_NO_ACCESS_MESSAGE = "The custom path @path has been omitted from the XML sitemap as it either does not exist, or it is not accessible to anonymous users. You can review custom paths <a href='@custom_paths_url'>here</a>.";


  /**
   * @var \Drupal\Core\Path\PathValidator
   */
  protected $pathValidator;

  /**
   * @var bool
   */
  protected $includeImages;

  /**
   * CustomUrlGenerator constructor.
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   * @param \Drupal\simple_sitemap\SitemapGenerator $sitemap_generator
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\simple_sitemap\Logger $logger
   * @param \Drupal\simple_sitemap\EntityHelper $entityHelper
   * @param \Drupal\Core\Path\PathValidator $path_validator
   */
  public function __construct(
    Simplesitemap $generator,
    SitemapGenerator $sitemap_generator,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    Logger $logger,
    EntityHelper $entityHelper,
    PathValidator $path_validator
  ) {
    parent::__construct(
      $generator,
      $sitemap_generator,
      $language_manager,
      $entity_type_manager,
      $logger,
      $entityHelper
    );
    $this->pathValidator = $path_validator;

  }

  /**
   * @return array
   */
  protected function getData() {
    return $this->generator->getCustomLinks();
  }

  /**
   * Batch function which generates urls to custom paths.
   */
  public function generate() {

    $this->includeImages = $this->generator->getSetting('custom_links_include_images', FALSE);

    foreach ($this->getBatchIterationElements($this->getData()) as $i => $custom_path) {

      $this->setCurrentId($i);

      // todo: Change to different function, as this also checks if current user has access. The user however varies depending if process was started from the web interface or via cron/drush. Use getUrlIfValidWithoutAccessCheck()?
      if (!$this->pathValidator->isValid($custom_path['path'])) {
//        if (!(bool) $this->pathValidator->getUrlIfValidWithoutAccessCheck($custom_path['path'])) {
        $this->logger->m(self::PATH_DOES_NOT_EXIST_OR_NO_ACCESS_MESSAGE,
          ['@path' => $custom_path['path'], '@custom_paths_url' => $GLOBALS['base_url'] . '/admin/config/search/simplesitemap/custom'])
          ->display('warning', 'administer sitemap settings')
          ->log('warning');
        continue;
      }
      $url_object = Url::fromUserInput($custom_path['path'], ['absolute' => TRUE]);

      $path = $url_object->getInternalPath();
      if ($this->batchInfo['remove_duplicates'] && $this->pathProcessed($path)) {
        continue;
      }

      $entity = $this->entityHelper->getEntityFromUrlObject($url_object);

      $path_data = [
        'path' => $path,
        'lastmod' => method_exists($entity, 'getChangedTime')
          ? date_iso8601($entity->getChangedTime()) : NULL,
        'priority' => isset($custom_path['priority']) ? $custom_path['priority'] : NULL,
        'changefreq' => !empty($custom_path['changefreq']) ? $custom_path['changefreq'] : NULL,
        'images' => $this->includeImages && method_exists($entity, 'getEntityTypeId')
          ? $this->getImages($entity->getEntityTypeId(), $entity->id())
          : []
      ];
      if (NULL !== $entity) {
        $path_data['entity_info'] = [
          'entity_type' => $entity->getEntityTypeId(),
          'id' => $entity->id()
        ];
      }
      $this->addUrl($path_data, $url_object);
    }
    $this->processSegment();
  }
}
