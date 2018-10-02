<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\simple_sitemap\SimplesitemapManager;

/**
 * Class SimplesitemapSettingsForm
 * @package Drupal\simple_sitemap\Form
 */
class SimplesitemapSettingsForm extends SimplesitemapFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_sitemap_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['simple_sitemap_settings']['#prefix'] = $this->getDonationText();

    $form['simple_sitemap_settings']['regenerate'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Generate sitemaps'),
      '#markup' => '<p>' . $this->t('Sitemaps can be regenerated on demand here.') . '</p>',
    ];

    $form['simple_sitemap_settings']['regenerate']['regenerate_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate from queue'),
      '#submit' => ['::generateSitemap'],
      '#validate' => [],
    ];

//    $form['simple_sitemap_settings']['regenerate']['regenerate_backend_submit'] = [
//      '#type' => 'submit',
//      '#value' => $this->t('Generate from queue (background)'),
//      '#submit' => ['::generateSitemapBackend'],
//      '#validate' => [],
//    ];

    $form['simple_sitemap_settings']['regenerate']['rebuild_queue_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Rebuild queue'),
      '#submit' => ['::rebuildQueue'],
      '#validate' => [],
    ];

    $queue_worker = $this->generator->getQueueWorker();
    $total_count = $queue_worker->getInitialElementCount();
    if (!empty($total_count)) {
      $indexed_count = $queue_worker->getProcessedElementCount();
      $percent = round(100 * $indexed_count / $total_count);

      // With all results processed, there still may be some stashed results to be indexed.
      $percent = $percent === 100 && $queue_worker->generationInProgress() ? 99 : $percent;

      $index_progress = [
        '#theme' => 'progress_bar',
        '#percent' => $percent,
        '#message' => t('@indexed out of @total items have been processed.', ['@indexed' => $indexed_count, '@total' => $total_count]),
      ];
      $form['simple_sitemap_settings']['progress'] = [
        '#markup' => render($index_progress),
        '#prefix' => '<div class="simple-sitemap-progress clearfix">',
        '#suffix' => '</div>',
      ];
    }

    $form['simple_sitemap_settings']['settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Settings'),
    ];

    $form['simple_sitemap_settings']['settings']['cron_generate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Regenerate the sitemaps during cron runs'),
      '#description' => $this->t('Uncheck this if you intend to only regenerate the sitemaps manually or via drush.'),
      '#default_value' => $this->generator->getSetting('cron_generate', TRUE),
    ];

    $form['simple_sitemap_settings']['settings']['cron_generate_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Sitemap generation interval'),
      '#description' => $this->t('The sitemap will be generated according to this interval.'),
      '#default_value' => $this->generator->getSetting('cron_generate_interval', 0),
      '#options' => [
        0 => $this->t('On every cron run'),
        1 => $this->t('Once an hour'),
        3 => $this->t('Once every @hours hours', ['@hours' => 3]),
        6 => $this->t('Once every @hours hours', ['@hours' => 6]),
        12 => $this->t('Once every @hours hours', ['@hours' => 12]),
        24 => $this->t('Once a day'),
        48 => $this->t('Once every @days days', ['@days' => 48/24]),
        72 => $this->t('Once every @days days', ['@days' => 72/24]),
        96 => $this->t('Once every @days days', ['@days' => 96/24]),
        120 => $this->t('Once every @days days', ['@days' => 120/24]),
        144 => $this->t('Once every @days days', ['@days' => 144/24]),
        168 => $this->t('Once a week'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="cron_generate"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['simple_sitemap_settings']['settings']['languages'] = [
      '#type' => 'details',
      '#title' => $this->t('Language settings'),
      '#open' => FALSE,
    ];

    $language_options = [];
    foreach ($this->languageManager->getLanguages() as $language) {
      if (!$language->isDefault()) {
        $language_options[$language->getId()] = $language->getName();
      }
    }

    $form['simple_sitemap_settings']['settings']['languages']['skip_untranslated'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip non-existent translations'),
      '#description' => $this->t('If checked, entity links are generated exclusively for languages the entity has been translated to as long as the language is not excluded below.<br/>Otherwise entity links are generated for every language installed on the site apart from languages excluded below.<br/>Bear in mind that non-entity paths like homepage will always be generated for every non-excluded language.'),
      '#default_value' => $this->generator->getSetting('skip_untranslated', FALSE),
    ];

    $form['simple_sitemap_settings']['settings']['languages']['excluded_languages'] = [
      '#title' => $this->t('Exclude languages'),
      '#type' => 'checkboxes',
      '#options' => $language_options,
      '#description' => !empty($language_options)
        ? $this->t('There will be no links generated for languages checked here.')
        : $this->t('There are no languages other than the default language <a href="@url">available</a>.', ['@url' => $GLOBALS['base_url'] . '/admin/config/regional/language']),
      '#default_value' => $this->generator->getSetting('excluded_languages', []),
    ];

    $form['simple_sitemap_settings']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => TRUE,
    ];

    $variants = [];
    foreach ($this->generator->getSitemapManager()->getSitemapVariants(NULL, FALSE) as $name => $info) {
      $variants[$name] = $this->t($info['label']);
    }
    $default_variant = $this->generator->getSetting('default_variant', SimplesitemapManager::DEFAULT_SITEMAP_VARIANT);

    $form['simple_sitemap_settings']['advanced']['default_variant'] = [
      '#type' => 'select',
      '#title' => $this->t('Default sitemap variant'),
      '#description' => $this->t('This sitemap variant will be available under <em>/sitemap.xml</em> in addition to its default path <em>/variant-name/sitemap.xml</em>.<br/>Variants can be configured <a href="@url">here</a>.', ['@url' => $GLOBALS['base_url'] . '/admin/config/search/simplesitemap/variants']),
      '#default_value' => isset($variants[$default_variant]) ? $default_variant : '',
      '#options' => ['' => $this->t('- None -')] + $variants,
      ];

    $form['simple_sitemap_settings']['advanced']['base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default base URL'),
      '#default_value' => $this->generator->getSetting('base_url', ''),
      '#size' => 30,
      '#description' => $this->t('On some hosting providers it is impossible to pass parameters to cron to tell Drupal which URL to bootstrap with. In this case the base URL of sitemap links can be overridden here.<br/>Example: <em>@url</em>', ['@url' => $GLOBALS['base_url']]),
    ];

    $form['simple_sitemap_settings']['advanced']['remove_duplicates'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude duplicate links'),
      '#description' => $this->t('Prevent per-sitemap variant duplicate links.<br/>Uncheck this to significantly speed up the sitemap generation process on a huge site (more than 20 000 indexed entities).'),
      '#default_value' => $this->generator->getSetting('remove_duplicates', TRUE),
    ];

    $form['simple_sitemap_settings']['advanced']['max_links'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum links in a sitemap'),
      '#min' => 1,
      '#description' => $this->t('The maximum number of links one sitemap can hold. If more links are generated than set here, a sitemap index will be created and the links split into several sub-sitemaps.<br/>50 000 links is the maximum Google will parse per sitemap, but an equally important consideration is generation performance: Splitting sitemaps into chunks <em>greatly</em> increases it.<br/>If left blank, all links will be shown on a single sitemap.'),
      '#default_value' => $this->generator->getSetting('max_links'),
    ];

    $form['simple_sitemap_settings']['advanced']['generate_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Sitemap generation max duration'),
      '#min' => 1,
      '#description' => $this->t('The maximum duration in seconds the generation task can run during a single cron run or during one batch process iteration.<br/>The higher the number, the quicker the generation process, but higher the risk of PHP timeout errors.'),
      '#default_value' => $this->generator->getSetting('generate_duration', 10000) / 1000,
      '#required' => TRUE,
    ];

    $this->formHelper->displayRegenerateNow($form['simple_sitemap_settings']);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $base_url = $form_state->getValue('base_url');
    $form_state->setValue('base_url', rtrim($base_url, '/'));
    if ($base_url !== '' && !UrlHelper::isValid($base_url, TRUE)) {
      $form_state->setErrorByName('base_url', t('The base URL is invalid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach (['max_links',
               'cron_generate',
               'cron_generate_interval',
               'remove_duplicates',
               'skip_untranslated',
               'base_url',
               'default_variant'] as $setting_name) {
      $this->generator->saveSetting($setting_name, $form_state->getValue($setting_name));
    }
    $this->generator->saveSetting('excluded_languages', array_filter($form_state->getValue('excluded_languages')));
    $this->generator->saveSetting('generate_duration', $form_state->getValue('generate_duration') * 1000);

    parent::submitForm($form, $form_state);

    // Regenerate sitemaps according to user setting.
    if ($form_state->getValue('simple_sitemap_regenerate_now')) {
      $this->generator->rebuildQueue()->generateSitemap();
    }
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function generateSitemap(array &$form, FormStateInterface $form_state) {
    $this->generator->generateSitemap();
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function generateSitemapBackend (array &$form, FormStateInterface $form_state) {
    $this->generator->generateSitemap('backend');
  }


  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function rebuildQueue(array &$form, FormStateInterface $form_state) {
    $this->generator->rebuildQueue();
  }

}
