<?php

/**
 * @file
 * Contains \Drupal\simple_sitemap\Form\SimplesitemapCustomLinksForm.
 */

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_sitemap\Form;

/**
 * SimplesitemapCustomLinksFrom
 */
class SimplesitemapCustomLinksForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simple_sitemap_custom_links_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simple_sitemap.settings_custom'];
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
      '#title' => t('Custom links'),
      '#type' => 'fieldset',
      '#markup' => '<p>' . t('Add custom internal drupal paths to the XML sitemap.') . '</p>',
    ];

    $form['simple_sitemap_custom']['custom_links'] = [
      '#type' => 'textarea',
      '#title' => t('Relative Drupal paths'),
      '#default_value' => $setting_string,
      '#description' => t("Please specify drupal internal (relative) paths, one per line. Do not forget to prepend the paths with a '/'. You can optionally add a priority (0.0 - 1.0) by appending it to the path after a space. The home page with the highest priority would be <em>/ 1.0</em>, the contact page with the default priority would be <em>/contact 0.5</em>."),
    ];

    $f = new Form();
    $f->displaySitemapRegenerationSetting($form['simple_sitemap_custom']);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $custom_link_config = $this->getCustomLinkConfig($form_state->getValue('custom_links'));

    foreach($custom_link_config as $link_config) {

      if (!\Drupal::service('path.validator')->isValid($link_config['path'])) {
        $form_state->setErrorByName('', t("The path <em>@path</em> does not exist.", ['@path' => $link_config['path']]));
      }
      if ($link_config['path'][0] != '/') {
        $form_state->setErrorByName('', t("The path <em>@path</em> needs to start with a '/'.", ['@path' => $link_config['path']]));
      }
      if (isset($link_config['priority'])) {
        if (!Form::isValidPriority($link_config['priority'])) {
          $form_state->setErrorByName('', t("The priority setting <em>@priority</em> for path <em>@path</em> is incorrect. Set the priority from 0.0 to 1.0.", ['@priority' => $link_config['priority'], '@path' => $link_config['path']]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $generator = \Drupal::service('simple_sitemap.generator');
    $custom_link_config = $this->getCustomLinkConfig($form_state->getValue('custom_links'));
    $generator->removeCustomLinks();
    foreach ($custom_link_config as $link_config) {
      $generator->addCustomLink($link_config['path'], $link_config);
    }
    parent::submitForm($form, $form_state);

    // Regenerate sitemaps according to user setting.
    if ($form_state->getValue('simple_sitemap_regenerate_now')) {
      $generator->generateSitemap();
    }
  }

  private function getCustomLinkConfig($custom_links_string) {
    // Unify newline characters and explode into array.
    $custom_links_string_lines = explode("\n", str_replace("\r\n", "\n", $custom_links_string));
    // Remove whitespace from array values.
    $custom_links_string_lines = array_filter(array_map('trim', $custom_links_string_lines));
    $custom_link_config = [];
    foreach($custom_links_string_lines as $i => &$line) {
      $link_settings = explode(' ', $line, 2);
      $custom_link_config[$i]['path'] = $link_settings[0];
      if (isset($link_settings[1]) && $link_settings[1] != '') {
        $custom_link_config[$i]['priority'] = $link_settings[1];
      }
    }
    return $custom_link_config;
  }
}
