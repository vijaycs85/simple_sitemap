<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SimplesitemapFormBase
 * @package Drupal\simple_sitemap\Form
 */
abstract class SimplesitemapFormBase extends ConfigFormBase {

  protected $generator;
  protected $formHelper;
  protected $pathValidator;

  /**
   * SimplesitemapFormBase constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $generator
   * @param $form_helper
   * @param $path_validator
   */
  public function __construct($generator, $form_helper, $path_validator) {
    $this->generator = $generator;
    $this->formHelper = $form_helper;
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simple_sitemap.generator'),
      $container->get('simple_sitemap.form_helper'),
      $container->get('path.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simple_sitemap.settings'];
  }

  protected function getDonationText() {
    return "<div class='description'>" . $this->t("If you would like to say thanks and support the development of this module, a <a target='_blank' href='@url'>donation</a> is always appreciated.", ['@url' => 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=5AFYRSBLGSC3W']) . "</div>";
  }
}
