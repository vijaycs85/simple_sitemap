<?php

/**
 * @file
 * Contains \Drupal\xmlsitemap\Form\SimplesitemapSettingsForm.
 */

namespace Drupal\simplesitemap\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * SimplesitemapSettingsFrom
 */
class SimplesitemapSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simplesitemap_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simplesitemap.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['simplesitemap_custom'] = array(
      '#type' => 'fieldset',
      '#title' => t('No settings'),
      '#description' => t('There are no settings here yet. Visit the other tab.'),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }
}
