<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * SimplesitemapSettingsFrom
 */
class SimplesitemapSettingsForm extends SimplesitemapFormBase {

  private $form_settings = [
    'max_links',
    'cron_generate',
    'remove_duplicates',
    'skip_untranslated',
    'batch_process_limit'
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_sitemap_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $generator = \Drupal::service('simple_sitemap.generator');

    $form['simple_sitemap_settings']['#prefix'] = $this->getDonationLink();

    $form['simple_sitemap_settings']['regenerate'] = [
      '#title' => $this->t('Regenerate sitemap'),
      '#type' => 'fieldset',
      '#markup' => '<p>' . $this->t('This will regenerate the XML sitemap for all languages.') . '</p>',
    ];

    $form['simple_sitemap_settings']['regenerate']['regenerate_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Regenerate sitemap'),
      '#submit' => ['::generateSitemap'],
      '#validate' => [], // Skip form-level validator.
    ];

    $form['simple_sitemap_settings']['settings'] = [
      '#title' => $this->t('Settings'),
      '#type' => 'fieldset',
    ];

    $form['simple_sitemap_settings']['settings']['cron_generate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Regenerate the sitemap on every cron run'),
      '#description' => $this->t('Uncheck this if you intend to only regenerate the sitemap manually or via drush.'),
      '#default_value' => $generator->getSetting('cron_generate', TRUE),
    ];

    $form['simple_sitemap_settings']['advanced'] = [
      '#title' => $this->t('Advanced settings'),
      '#type' => 'fieldset',
    ];

    $form['simple_sitemap_settings']['advanced']['remove_duplicates'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude duplicate links'),
      '#description' => $this->t('Uncheck this to significantly speed up the sitemap generation process on a huge site (more than 20 000 indexed entities).'),
      '#default_value' => $generator->getSetting('remove_duplicates', TRUE),
    ];

    $form['simple_sitemap_settings']['advanced']['skip_untranslated'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip non-existent translations'),
      '#description' => $this->t('If checked, only links to the translated content will be included, otherwise the sitemap will include links to all content translation variants, even when the content has not been translated yet.'),
      '#default_value' => $generator->getSetting('skip_untranslated', FALSE),
    ];

    $form['simple_sitemap_settings']['advanced']['max_links'] = [
      '#title' => $this->t('Maximum links in a sitemap'),
      '#description' => $this->t("The maximum number of links one sitemap can hold. If more links are generated than set here, a sitemap index will be created and the links split into several sub-sitemaps.<br/>50 000 links is the maximum Google will parse per sitemap, however it is advisable to set this to a lower number. If left blank, all links will be shown on a single sitemap."),
      '#type' => 'textfield',
      '#maxlength' => 5,
      '#size' => 5,
      '#default_value' => $generator->getSetting('max_links', 2000),
    ];

    $form['simple_sitemap_settings']['advanced']['batch_process_limit'] = [
      '#title' => $this->t('Refresh batch every n links'),
      '#description' => $this->t("During sitemap generation, the batch process will issue a page refresh after n links processed to prevent PHP timeouts and memory exhaustion. Increasing this number will reduce the number of times Drupal has to bootstrap (thus speeding up the generation process), but will require more memory and less strict PHP timeout settings."),
      '#type' => 'textfield',
      '#maxlength' => 5,
      '#size' => 5,
      '#default_value' => $generator->getSetting('batch_process_limit', 1500),
      '#required' => TRUE,
    ];

    \Drupal::service('simple_sitemap.form')->displayRegenerateNow($form['simple_sitemap_settings']);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $max_links = $form_state->getValue('max_links');
    if ($max_links != '') {
      if (!is_numeric($max_links) || $max_links < 1 || $max_links != round($max_links)) {
        $form_state->setErrorByName('max_links', $this->t("The value of the <em>Maximum links in a sitemap</em> field must be empty, or a positive integer greater than 0."));
      }
    }

  $batch_process_limit = $form_state->getValue('batch_process_limit');
    if (!is_numeric($batch_process_limit) || $batch_process_limit < 1 || $batch_process_limit != round($batch_process_limit)) {
      $form_state->setErrorByName('batch_process_limit', $this->t("The value of the <em>Refresh batch every n links</em> field must be a positive integer greater than 0."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $generator = \Drupal::service('simple_sitemap.generator');
    foreach($this->form_settings as $setting_name) {
      $generator->saveSetting($setting_name, $form_state->getValue($setting_name));
    }
    parent::submitForm($form, $form_state);

    // Regenerate sitemaps according to user setting.
    if ($form_state->getValue('simple_sitemap_regenerate_now')) {
      $generator->generateSitemap();
    }
  }

  public function generateSitemap(array &$form, FormStateInterface $form_state) {
    \Drupal::service('simple_sitemap.generator')->generateSitemap();
  }
}
