<?php

/**
 * @file
 * Contains \Drupal\xmlsitemap\Form\SimplesitemapCustomLinksForm.
 */

namespace Drupal\simplesitemap\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simplesitemap\Simplesitemap;

/**
 * SimplesitemapCustomLinksFrom
 */
class SimplesitemapCustomLinksForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'simplesitemap_custom_links_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simplesitemap.settings_custom'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $sitemap = new Simplesitemap;
    $setting_string = '';
    foreach ($sitemap->get_custom_links() as $custom_link) {

      // todo: remove this statement after removing the index key from the configuration.
      if (isset($custom_link['index']) && $custom_link['index'] == 0)
        continue;

      $setting_string .= isset($custom_link['priority']) ? $custom_link['path'] . ' ' . $custom_link['priority'] : $custom_link['path'];
      $setting_string .= "\r\n";
    }

    $form['simplesitemap_custom'] = array(
      '#title' => t('Custom links'),
    );

    $form['simplesitemap_custom']['custom_links'] = array(
      '#type' => 'textarea',
      '#title' => '<span class="element-invisibule">' . t('Relative Drupal paths') . '</span>',
      '#default_value' => $setting_string,
      '#prefix' => t('Add custom internal drupal paths to the XML sitemap and specify their priorities.'),
      '#description' => t("Please specify drupal internal (relative) paths, one per line. Do not forget to prepend the paths with an '/' You can add a priority (0.0 - 1.0) by appending it to the path after a space. The home page with the highest priority would be <em>/ 1</em>, the contact page with a medium priority would be <em>/contact 0.5</em>."),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $custom_links_string = str_replace("\r\n", "\n", $form_state->getValue('custom_links'));
    $custom_links = array_filter(explode("\n", $custom_links_string), 'trim');

    foreach($custom_links as $link_setting) {
      $settings = explode(' ', $link_setting, 2);

      if (!\Drupal::service('path.validator')->isValid($settings[0])) {
        $form_state->setErrorByName('', t("The path <em>$settings[0]</em> does not exist."));
      }
      if ($settings[0][0] != '/') {
        $form_state->setErrorByName('', t("The path <em>$settings[0]</em> needs to start with an '/'."));
      }
      if (isset($settings[1])) {
        if (!is_numeric($settings[1]) || $settings[1] < 0 || $settings[1] > 1) {
          $form_state->setErrorByName('', t("Priority setting on line <em>$link_setting</em> is incorrect. Set priority from 0.0 to 1.0."));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $sitemap = new Simplesitemap;
    $custom_links_string = str_replace("\r\n", "\n", $form_state->getValue('custom_links'));
    $custom_links_string_lines = array_filter(explode("\n", $custom_links_string), 'trim');
    $custom_link_config = array();
    foreach($custom_links_string_lines as $line) {
      $line_settings = explode(' ', $line, 2);
      $custom_link_config[]['path'] = $line_settings[0];
      if (isset($line_settings[1])) {
        end($custom_link_config);
        $key = key($custom_link_config);
        $custom_link_config[$key]['priority'] = number_format((float)$line_settings[1], 1, '.', '');
      }
    }
    $sitemap->save_custom_links($custom_link_config);
    $sitemap->generate_all_sitemaps();
    parent::submitForm($form, $form_state);
  }
}
