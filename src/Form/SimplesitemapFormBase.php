<?php

namespace Drupal\simple_sitemap\Form;

use Drupal\Core\Form\ConfigFormBase;

/**
 * SimplesitemapFormBase
 */
abstract class SimplesitemapFormBase extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simple_sitemap.settings'];
  }

  protected function getDonationLink() {
    return "<div class='description'>" . $this->t("If you would like to say thanks and support the development of this module, a <a target='_blank' href='@url'>donation</a> is always appreciated.", ['@url' => 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=5AFYRSBLGSC3W']) . "</div>";
  }
}
