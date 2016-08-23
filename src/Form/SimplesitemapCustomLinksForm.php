<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_sitemap\Form;

/**
 * SimplesitemapCustomLinksFrom
 */
class SimplesitemapCustomLinksForm extends SimplesitemapFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_sitemap_custom_links_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $generator = \Drupal::service('simple_sitemap.generator');
    $setting_string = '';
    foreach ($generator->getConfig('custom') as $custom_link) {
      $setting_string .= isset($custom_link['priority'])
        ? $custom_link['path'] . ' ' . Form::formatPriority($custom_link['priority'])
        : $custom_link['path'];
      $setting_string .= "\r\n";
    }

    $form['simple_sitemap_custom'] = [
      '#title' => $this->t('Custom links'),
      '#type' => 'fieldset',
      '#markup' => '<p>' . $this->t('Add custom internal drupal paths to the XML sitemap.') . '</p>',
      '#prefix' => $this->getDonationLink(),
    ];

    $form['simple_sitemap_custom']['custom_links'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Relative Drupal paths'),
      '#default_value' => $setting_string,
      '#description' => $this->t("Please specify drupal internal (relative) paths, one per line. Do not forget to prepend the paths with a '/'. You can optionally add a priority (0.0 - 1.0) by appending it to the path after a space. The home page with the highest priority would be <em>/ 1.0</em>, the contact page with the default priority would be <em>/contact 0.5</em>."),
    ];

    \Drupal::service('simple_sitemap.form')->displayRegenerateNow($form['simple_sitemap_custom']);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach($this->getCustomLinks($form_state->getValue('custom_links')) as $i => $link_config) {
      $placeholders = ['@line' => ++$i, '@path' => $link_config['path'], '@priority' => isset($link_config['priority']) ? $link_config['priority'] : ''];

      // Checking if internal path exists.
      if (!\Drupal::service('path.validator')->isValid($link_config['path'])
       || strpos($link_config['path'], '//') !== FALSE) { // Path validator does not see a double slash as an error. Catching this to prevent breaking path generation.
        $form_state->setErrorByName('', $this->t("<strong>Line @line</strong>: The path <em>@path</em> does not exist.", $placeholders));
      }

      // Making sure the paths start with a slash.
      if ($link_config['path'][0] != '/') {
        $form_state->setErrorByName('', $this->t("<strong>Line @line</strong>: The path <em>@path</em> needs to start with a '/'.", $placeholders));
      }

      // Making sure the priority is formatted correctly.
      if (isset($link_config['priority']) && !Form::isValidPriority($link_config['priority'])) {
        $form_state->setErrorByName('', $this->t("<strong>Line @line</strong>: The priority setting <em>@priority</em> for path <em>@path</em> is incorrect. Set the priority from 0.0 to 1.0.", $placeholders));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $custom_links = $this->getCustomLinks($form_state->getValue('custom_links'));
    $generator = \Drupal::service('simple_sitemap.generator')->removeCustomLinks();
    foreach ($custom_links as $link_config) {
      $generator->addCustomLink($link_config['path'], $link_config);
    }
    parent::submitForm($form, $form_state);

    // Regenerate sitemaps according to user setting.
    if ($form_state->getValue('simple_sitemap_regenerate_now')) {
      $generator->generateSitemap();
    }
  }

  private function getCustomLinks($custom_links_string) {
    // Unify newline characters and explode into array.
    $custom_links_string_lines = explode("\n", str_replace("\r\n", "\n", $custom_links_string));
    // Remove whitespace from array values.
    $custom_links_string_lines = array_filter(array_map('trim', $custom_links_string_lines));
    $custom_links = [];
    foreach($custom_links_string_lines as $i => &$line) {
      $link_settings = explode(' ', $line, 2);
      $custom_links[$i]['path'] = $link_settings[0];
      if (isset($link_settings[1]) && $link_settings[1] != '') {
        $custom_links[$i]['priority'] = $link_settings[1];
      }
    }
    return $custom_links;
  }
}
