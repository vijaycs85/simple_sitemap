<?php
/**
 * @file
 * Contains \Drupal\simple_sitemap\Batch.
 */

namespace Drupal\simple_sitemap;

use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;


class Batch {
  private $batch;
  private $batch_info;

  const PLUGIN_ERROR_MESSAGE = "The simple_sitemap @plugin plugin has been omitted, as it does not return the required numeric array of path data sets. Each data sets must contain the required path element (relative path string or Drupal\\Core\\Url object) and optionally other elements, like lastmod.";
  const PATH_DOES_NOT_EXIST = "The path @faulty_path has been omitted from the XML sitemap, as it does not exist.";
  const PATH_DOES_NOT_EXIST_OR_NO_ACCESS = "The path @faulty_path has been omitted from the XML sitemap as it either does not exist, or it is not accessible to anonymous users.";
  const ANONYMOUS_USER_ID = 0;


  function __construct($from = 'form') {
    $this->batch = array(
      'title' => t('Generating XML sitemap'),
      'init_message' => t('Initializing batch...'),
      'error_message' => t('An error occurred'),
      'progress_message' => t('Processing @current out of @total link types.'),
      'operations' => array(),
      'finished' => __CLASS__ . '::finish_batch',
    );
    $config = \Drupal::config('simple_sitemap.settings')->get('settings');
    $this->batch_info = array(
      'from' => $from,
      'batch_process_limit' => $config['batch_process_limit'],
      'max_links' => $config['max_links'],
      'remove_duplicates' => $config['remove_duplicates'],
      'anonymous_user_account' => User::load(self::ANONYMOUS_USER_ID),
    );
  }

  public function start() {
    batch_set($this->batch);
    switch ($this->batch_info['from']) {
      case 'form':
        break;
      case 'drush':
        $this->batch =& batch_get();
        $this->batch['progressive'] = FALSE;
        drush_backend_batch_process();
        break;
      case 'cron':
        $this->batch =& batch_get();
        $this->batch['progressive'] = FALSE;
        batch_process();
        break;
    }
  }

  public function add_operations($type, $operations) {
    switch ($type) {
      case 'entity_types':
        foreach ($operations as $operation) {
          $this->batch['operations'][] = array(
            __CLASS__ . '::generate_bundle_urls',
            array($operation['query'], $operation['info'], $this->batch_info)
          );
        };
        break;
      case 'custom_paths':
        $this->batch['operations'][] = array(
          __CLASS__ . '::generate_custom_urls',
          array($operations, $this->batch_info)
        );
        break;
    }
  }

  public static function finish_batch($success, $results, $operations) {
    if ($success) {
      if (!empty($results) || is_null(db_query('SELECT MAX(id) FROM {simple_sitemap}')->fetchField())) {
        SitemapGenerator::generate_sitemap($results);
      }
      drupal_set_message(t("The <a href='@url' target='_blank'>XML sitemap</a> has been regenerated for all languages.",
        array('@url' => $GLOBALS['base_url'] . '/sitemap.xml')));
    }
    else {
    }
  }

  /**
   * Wrapper function for Drupal\Core\Url::fromRoute.
   * Returns url data for every language.
   *
   * @param $route_name
   * @param $route_parameters
   * @param $options
   * @see https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Url.php/function/Url%3A%3AfromRoute/8
   *
   * @return array
   *  Returns an array containing the internal path, url objects for every language,
   *  url options and access information.
   */
  public static function generate_bundle_urls($query, $info, $batch_info, &$context) {
    $languages = \Drupal::languageManager()->getLanguages();
    $default_language_id = Simplesitemap::get_default_lang_id();

    // Getting id field name from plugin info, if not defined assuming the name of the first field in the query to be the entity id field name.
    $fields = $query->getFields();
    if (isset($info['field_info']['entity_id']) && isset($fields[$info['field_info']['entity_id']])) {
      $id_field = $info['field_info']['entity_id'];
    }
    else {
      reset($fields);
      $id_field = key($fields);
    }

    // Getting the name of the route name field if any.
    if (!empty($info['field_info']['route_name'])) {
      $route_name_field = $info['field_info']['route_name'];
    }

    // Initializing batch.
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_id'] = 0;
      $context['sandbox']['max'] = $query->countQuery()
        ->execute()
        ->fetchField();
    }

    // Creating a query limited to n=batch_process_limit entries.
    $result = $query->condition($id_field, $context['sandbox']['current_id'], '>')
      ->orderBy($id_field)
      ->range(0, $batch_info['batch_process_limit'])
      ->execute()
      ->fetchAll();

    foreach ($result as $row) {

      $context['sandbox']['current_id'] = $row->$id_field;
      $context['sandbox']['progress']++;

      // Getting the name of the route parameter field if any.
      if (!empty($info['field_info']['route_parameters'])) {
        $route_params_field = $info['field_info']['route_parameters'];
      }
      // Setting route parameters if they exist in the database (menu links).
      if (isset($route_params_field) && !empty($route_parameters = unserialize($row->$route_params_field))) {
        $route_parameters = array(key($route_parameters) => $route_parameters[key($route_parameters)]);
      }
      elseif (!empty($info['path_info']['entity_type'])) {
        $route_parameters = array($info['path_info']['entity_type'] => $row->$id_field);
      }
      else {
        $route_parameters = array();
      }

      // Getting the name of the options field if any.
      if (!empty($info['field_info']['options'])) {
        $options_field = $info['field_info']['options'];
      }

      // Setting options if they exist in the database (menu links)
      $options = isset($options_field) && !empty($options = unserialize($row->$options_field)) ? $options : array();
      $options['absolute'] = TRUE;

      // Setting route name if it exists in the database (menu links)
      if (isset($route_name_field)) {
        $route_name = $row->$route_name_field;
      }
      elseif (isset($info['path_info']['route_name'])) {
        $route_name = $info['path_info']['route_name'];
      }
      else {
        continue;
      }

      $url_object = Url::fromRoute($route_name, $route_parameters, $options);

      $access = self::access($url_object, $batch_info['anonymous_user_account']);
      if (!$access) {
        continue;
      }

      // Do not include path if it already exists.
      $path = $url_object->getInternalPath();
      if ($batch_info['remove_duplicates']) {
        foreach ($context['results'] as $result) {
          if ($result['path'] == $path) {
            continue 2;
          }
        }
      }

      $urls = array();
      foreach ($languages as $language) {
        if ($language->getId() === $default_language_id) {
          $urls[$default_language_id] = $url_object->toString();
        }
        else {
          $options['language'] = $language;
          $urls[$language->getId()] = Url::fromRoute($route_name, $route_parameters, $options)
            ->toString();
        }
      }
      $context['results'][] = array(
        'path' => $path,
        'urls' => $urls,
        'options' => $url_object->getOptions(),
        'lastmod' => !empty($info['field_info']['lastmod']) ? $row->{$info['field_info']['lastmod']} : NULL,
        'priority' => !empty($info['bundle_settings']['priority']) ? $info['bundle_settings']['priority'] : NULL,
      );
    }

    // Providing progress info.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
      // Adding processing message after finishing every part of the batch.
      if (!empty($context['results'][key($context['results'])]['path'])) {
        $last_path = HTML::escape($context['results'][key($context['results'])]['path']);
        $context['message'] = t("Processing path @current out of @max: @path", array(
          '@current' => $context['sandbox']['progress'],
          '@max' => $context['sandbox']['max'],
          '@path' => $last_path,
        ));
//        switch($batch_info['from']) { //todo: add shell output
//          case 'drush':
//            print $context['message'] . "\r\n";
//            break;
//          default:
//        }
      }
    }

    if (!empty($batch_info['max_links']) && count($context['results']) >= $batch_info['max_links']) {
      $chunks = array_chunk($context['results'], $batch_info['max_links']);
      foreach($chunks as $i => $chunk_links) {
        if (count($chunk_links) == $batch_info['max_links']) {
          SitemapGenerator::generate_sitemap($chunk_links);
          $context['results'] = array_slice($context['results'], count($chunk_links));
        }
      }
    }
  }

  /**
   * Wrapper function for Drupal\Core\Url::fromUserInput.
   * Returns url data for every language.
   *
   * @param $user_input
   * @param $options
   * @see https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Url.php/function/Url%3A%3AfromUserInput/8
   *
   * @return array or FALSE
   *  Returns an array containing the internal path, url objects for every language,
   *  url options and access information. Returns FALSE if path does not exist.
   */
  public static function generate_custom_urls($custom_paths, $batch_info, &$context) {

    $languages = \Drupal::languageManager()->getLanguages();
    $default_language_id = Simplesitemap::get_default_lang_id();

    $options['absolute'] = TRUE;

    foreach($custom_paths as $i => $custom_path) {

      $user_input = $custom_path['path'][0] === '/' ? $custom_path['path'] : '/' . $custom_path['path'];
      if (!\Drupal::service('path.validator')->isValid($custom_path['path'])) { //todo: Change to different function, as this also checks if current user has access. The user however varies depending if process was started from the web interface or via cron/drush.
        self::register_error(self::PATH_DOES_NOT_EXIST_OR_NO_ACCESS, array('@faulty_path' => $custom_path['path']), 'warning');
        continue;
      }
      $url_object = Url::fromUserInput($user_input, $options);

      $access = self::access($url_object, $batch_info['anonymous_user_account']);
      if (!$access) {
        continue;
      }

      //  Do not include path if it already exists. //todo: test
      $path = $url_object->getInternalPath();
      foreach ($context['results'] as $result) {
        if ($result['path'] == $path) {
          continue 2;
        }
      }

      $urls = array();
      foreach($languages as $language) {
        if ($language->getId() === $default_language_id) {
          $urls[$default_language_id] = $url_object->toString();
        }
        else {
          $options['language'] = $language;
          $urls[$language->getId()] = Url::fromUserInput($user_input, $options)->toString();
        }
      }
      $context['results'][] = array(
        'path' => $path,
        'urls' => $urls,
        'options' => $url_object->getOptions(),
        'priority' => !empty($custom_path['priority']) ? $custom_path['priority'] : NULL,
      );
    }
  }

  /**
   * Logs and displays an error.
   *
   * @param $message
   *  Untranslated message.
   * @param array $substitutions (optional)
   *  Substitutions (placeholder => substitution) which will replace placeholders
   *  with strings.
   * @param string $type (optional)
   *  Message type (status/warning/error).
   */
  private static function register_error($message, $substitutions = array(), $type = 'error') {
    $message = strtr(t($message), $substitutions);
    \Drupal::logger('simple_sitemap')->notice($message);
    drupal_set_message($message, $type);
  }

  /**
   * Checks if anonymous users have access to a given path.
   *
   * @param \Drupal\Core\Url object
   *
   * @return bool
   *  TRUE if anonymous users have access to path, FALSE if they do not.
   */
  protected static function access($url_object, $account) {
    return $url_object->access($account); //todo: Add error checking.
  }
}
