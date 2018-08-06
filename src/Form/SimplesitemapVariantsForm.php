<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_sitemap\Simplesitemap;

/**
 * Class SimplesitemapVariantsForm
 * @package Drupal\simple_sitemap\Form
 */
class SimplesitemapVariantsForm extends SimplesitemapFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_sitemap_variants_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['simple_sitemap_variants'] = [
      '#title' => $this->t('Sitemap variants'),
      '#type' => 'fieldset',
      '#markup' => '<p>' . $this->t('Define sitemap variants. A sitemap variant is a sitemap instance of a certain type (specific sitemap generator and URL generators) accessible under a certain URL.<br/>Each variant can have its own entity bundle settings (to be defined on bundle edit pages).') . '</p>',
      '#prefix' => $this->getDonationText(),
    ];

    $type_descriptions = [];
    $sitemap_types = $this->generator->getSitemapTypeDefinitions();
    foreach ($sitemap_types as $sitemap_type => $definition) {
      $type_descriptions[] = $sitemap_type . (!empty($definition['description']) ? (': ' . $definition['description']) : '');
    }
    $form['simple_sitemap_variants']['variants'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Variants'),
      '#default_value' => $this->variantsToString($this->generator->getSitemapVariants(NULL, TRUE)),
      '#description' => $this->t("Please specify sitemap variants, one per line.<br/>A variant definition consists of the variant name (used as the variant's path), the sitemap type it belongs to (optional) and the variant label (optional).<br/>These three values have to be separated by the | pipe | symbol.<br/><br/><strong>Examples:</strong><br/><em>default | default_hreflang | Default</em> -> variant of the <em>default_hreflang</em> sitemap type and <em>Default</em> as label; accessible under <em>/default/sitemap.xml</em><br/><em>test</em> -> variant of the <em>@default_sitemap_type</em> sitemap type and <em>test</em> as label; accessible under <em>/test/sitemap.xml</em><br/><br/><strong>Available sitemap types:</strong><br/>@sitemap_types", ['@default_sitemap_type' => Simplesitemap::DEFAULT_SITEMAP_TYPE, '@sitemap_types' => implode('<br/>', $type_descriptions)]),
    ];

    $this->formHelper->displayRegenerateNow($form['simple_sitemap_custom']);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @todo show multiple errors at once
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $line = 0;
    $sitemap_types = $this->generator->getSitemapTypeDefinitions();
    foreach ($this->stringToVariants($form_state->getValue('variants')) as $variant_name => $variant_definition) {
      $placeholders = [
        '@line' => ++$line,
        '@name' => $variant_name,
        '@type' => isset($variant_definition['type']) ? $variant_definition['type'] : '',
        '@label' => isset($variant_definition['label']) ? $variant_definition['label'] : '',
      ];

      if (trim($variant_name) === '') {
        $form_state->setErrorByName('', $this->t("<strong>Line @line</strong>: The variant name cannot be empty.", $placeholders));
      }

      if (!preg_match('/^[\w-_]+$/', $variant_name)) {
        $form_state->setErrorByName('', $this->t("<strong>Line @line</strong>: The variant name <em>@name</em> can only include alphanumeric characters, dashes and underscores.", $placeholders));
      }

      if (!isset($sitemap_types[$variant_definition['type']])) {
        $form_state->setErrorByName('', $this->t("<strong>Line @line</strong>: The variant <em>@name</em> is of a sitemap type <em>@type</em> that does not exist.", $placeholders));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @todo improve code
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($this->generator->getSitemapVariants() as $variant_name => $variant_definition) {
      $this->generator->removeSitemapVariant($variant_name);
    }
    $variants = $this->stringToVariants($form_state->getValue('variants'));
    foreach ($variants as $variant_name => $variant_definition) {
      $this->generator->addSitemapVariant($variant_name, $variant_definition);
    }
    parent::submitForm($form, $form_state);

    // Regenerate sitemaps according to user setting.
    if ($form_state->getValue('simple_sitemap_regenerate_now')) {
      $this->generator->generateSitemap();
    }
  }

  /**
   * @param $variant_string
   * @return array
   */
  protected function stringToVariants($variant_string) {

    // Unify newline characters and explode into array.
    $variants_string_lines = explode("\n", str_replace("\r\n", "\n", $variant_string));

    // Remove empty values and whitespaces from array.
    $variants_string_lines = array_filter(array_map('trim', $variants_string_lines));

    $variants = [];
    foreach ($variants_string_lines as $i => &$line) {
      $variant_settings = explode('|', $line);
      $name = strtolower(trim($variant_settings[0]));
      $variants[$name]['type'] = !empty($variant_settings[1]) ? trim($variant_settings[1]) : Simplesitemap::DEFAULT_SITEMAP_TYPE;
      $variants[$name]['label'] = !empty($variant_settings[2]) ? trim($variant_settings[2]) : $name;
    }
    return $variants;
  }

  /**
   * @param array $variants
   * @return string
   */
  protected function variantsToString(array $variants) {
    $variants_string = '';
    foreach ($variants as $variant_name => $variant_definition) {
      $variants_string .= $variant_name
        . ' | ' . $variant_definition['type']
        . ' | ' . $variant_definition['label']
        . "\r\n";
    }
    return $variants_string;
  }
}
