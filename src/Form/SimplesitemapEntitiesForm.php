<?php

/**
 * @file
 * Contains \Drupal\simple_sitemap\Form\SimplesitemapEntitiesForm.
 */

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_sitemap\Simplesitemap;

/**
 * SimplesitemapSettingsFrom
 */
class SimplesitemapEntitiesForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_sitemap_entities_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simple_sitemap.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $sitemap = \Drupal::service('simple_sitemap.generator');

    $form['simple_sitemap_entities']['entities'] = array(
      '#title' => t('Supported entities'),
      '#type' => 'fieldset',
      '#markup' => '<p>' . t('XML sitemap settings will be added only to entity forms of entities enabled here. Disabling an entity on this page will irreversibly delete its sitemap settings.') . '</p>',
    );

    $options = [];
    $entity_types = Simplesitemap::getSitemapEntityTypes();
    foreach ($entity_types as $entity_type_id => $entity_type) {
      $options[$entity_type_id] = $entity_type->getLabel() ? : $entity_type_id;
    }

    $form['simple_sitemap_entities']['entities']['entities'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Enable sitemap settings'),
      '#description' => t(''),
      '#options' => $options,
      '#default_value' => array_keys($sitemap->getConfig('entity_types')),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $sitemap = \Drupal::service('simple_sitemap.generator');
    $entity_types = $sitemap->getConfig('entity_types');

    foreach($form_state->getValue('entities') as $entity_type_name => $enable) {
      if (!$enable) {
        unset($entity_types[$entity_type_name]);
      }
      elseif (empty($entity_types[$entity_type_name])) {
        $entity_types[$entity_type_name] = [];
      }
    }

    $sitemap->saveConfig('entity_types', $entity_types);
    parent::submitForm($form, $form_state);
  }
}
