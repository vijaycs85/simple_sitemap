<?php

namespace Drupal\simple_sitemap\Plugin\Field;

use Drupal\Core\Field\FieldItemList;

abstract class SimplesitemapItemBase extends FieldItemList {

  protected $settingName;

  protected $defaultValue;

  function computedListProperty() {
    $entity = $this->getEntity();
    $generator = \Drupal::service('simple_sitemap.generator');

    if ($generator->entityTypeIsEnabled($entity->getEntityTypeId())) {
      if (!empty($entity->id())) {
        $settings = $generator->getEntityInstanceSettings(
          $entity->getEntityTypeId(),
          $entity->id()
        );
      }
      elseif (!empty($entity->getEntityTypeId())) {
        $settings = $generator->getBundleSettings(
          $entity->getEntityTypeId(),
          $entity->bundle()
        );
      }

      $this->list[0] = $this->createItem(
        0,
        isset($settings[$this->settingName]) ? $settings[$this->settingName] : $this->defaultValue
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function get($index) {
    $this->computedListProperty();
    return isset($this->list[$index]) ? $this->list[$index] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    $this->computedListProperty();
    return parent::getIterator();
  }
}
