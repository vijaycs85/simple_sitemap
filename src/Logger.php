<?php

namespace Drupal\simple_sitemap;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class Logger
 * @package Drupal\simple_sitemap
 */
class Logger {

  use StringTranslationTrait;

  protected $logger;
  protected $currentUser;

  /**
   * Logger constructor.
   * @param $logger
   */
  public function __construct($logger, $current_user) {
    $this->logger = $logger;
    $this->currentUser = $current_user;
  }

  /**
   * Logs error and optionally displays it to the privileged user.
   *
   * @param $message
   *  Can be string or an array where the first value is the message string and
   *  the second value an array with arrays containing
   *  placeholder => replacement values for the message.
   * @param string $display
   *  Message type (status/warning/error), if set, message is displayed to
   *  privileged user in addition to being logged.
   */
  public function registerError($message, $display = NULL) {
    $substitutions = isset($message[1]) && is_array($message[1]) ? $message[1] : [];
    $message = is_array($message) ? $message[0] : $message;
    $this->logger->notice(strtr($message, $substitutions));
    if (!empty($display)
      && $this->currentUser->hasPermission('administer sitemap settings')) {
      drupal_set_message($this->t($message, $substitutions), $display);
    }
  }
}
