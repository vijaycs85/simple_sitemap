<?php
/**
 * @file
 * Contains \Drupal\simple_sitemap\Form.
 */

namespace Drupal\simple_sitemap;

/**
 * Form class.
 */
class Form {

  const PRIORITY_DEFAULT = 0.5;
  const PRIORITY_HIGHEST = 10;
  const PRIORITY_DIVIDER = 10;

  public $entityCategory;
  public $entityTypeId;
  public $bundleName;
  public $instanceId;

  private $formState;
  private $formId;

  /**
   * Form constructor.
   */
  function __construct(&$form, $form_state, $form_id) {
    $this->formId = $form_id;
    $this->formState = $form_state;
    $this->entityCategory = NULL;

    if (!$this->getEntityDataFromFormId()) {
      $this->getEntityDataFromFormEntity();
    }
  }

  /**
   * This is a temporary solution to be able to set sitemap settings
   * for all 'user' entities on the form 'user_admin_settings', as there is no
   * general setting page where non-bundle entity types can be set up.
   *
   * @todo: Remove this and create a general setting page for all entity types.
   */
  private function getEntityDataFromFormId() {
    switch ($this->formId) {

      case 'user_admin_settings':
        $this->entityCategory = 'bundle';
        $this->entityTypeId = 'user';
        $this->bundleName = 'user';
        $this->instanceId = NULL;
        return TRUE;

      default:
        return FALSE;
    }
  }

  /**
   * Checks if this particular form is a bundle form, or a bundle instance form
   * and gathers sitemap settings from the database.
   *
   * @return bool
   *  TRUE if this is a bundle or bundle instance form, FALSE otherwise.
   */
  private function getEntityDataFromFormEntity() {
    $form_entity = $this->getFormEntity();
    if ($form_entity !== FALSE) {
      $form_entity_type = $form_entity->getEntityType();
      $entity_type_id = $form_entity->getEntityTypeId(); //todo: Change to $form_entity_type->id()?
      $sitemap_entity_types = Simplesitemap::getSitemapEntityTypes();
      $bundle_entity_type = $form_entity_type->getBundleEntityType();
      $entity_bundle = $form_entity->bundle();
      if (isset($sitemap_entity_types[$entity_type_id])) {
        $this->entityCategory = 'instance';
      }
      else {
        foreach ($sitemap_entity_types as $sitemap_entity) {
          if ($sitemap_entity->getBundleEntityType() == $entity_type_id) {
            $this->entityCategory = 'bundle';
            break;
          }
        }
      }

      // Menu fixes.
      if (is_null($this->entityCategory) && $entity_type_id == 'menu') {
        $this->entityCategory = 'bundle';
      }
      if ($entity_type_id == 'menu_link_content') {
        $bundle_entity_type = 'menu';
      }

      switch ($this->entityCategory) {
        case 'bundle':
          $this->entityTypeId = $form_entity->getEntityTypeId();
          $this->bundleName = $form_entity->id();
          $this->instanceId = NULL;
          break;

        case 'instance':
          $this->entityTypeId = !empty($bundle_entity_type) ? $bundle_entity_type : $entity_bundle;
          $this->bundleName = $entity_bundle == 'menu_link_content' && method_exists($form_entity, 'getMenuName') ? $form_entity->getMenuName() : $entity_bundle; // menu fix
          $this->instanceId = $form_entity->id();
          break;

        default:
          return FALSE;
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Gets the object entity of the form if available.
   *
   * @return object $entity or FALSE if non-existent or if form operation is
   *  'delete'.
   */
  private function getFormEntity() {
    $form_object = $this->formState->getFormObject();
    if (!is_null($form_object)
      && method_exists($form_object, 'getEntity')
      && $form_object->getOperation() !== 'delete') {
      return $form_object->getEntity();
    }
    return FALSE;
  }

  /**
   * Gets new entity Id after entity creation.
   * To be used in an entity form submit.
   *
   * @return int entity ID.
   */
  public static function getNewEntityId($form_state) {
    return $form_state->getFormObject()->getEntity()->id();
  }

  /**
   * Checks if simple_sitemap values have been changed after submitting the form.
   * To be used in an entity form submit.
   *
   * @return bool
   *  TRUE if simple_sitemap form values have been altered by the user.
   */
  public static function valuesChanged($form, $form_state) {
    $values = $form_state->getValues();
    foreach (array('simple_sitemap_index_content', 'simple_sitemap_priority', 'simple_sitemap_regenerate_now') as $field_name) {
      if (isset($values['simple_sitemap'][$field_name]) && $values['simple_sitemap'][$field_name] != $form['simple_sitemap'][$field_name]['#default_value']
        || isset($values[$field_name]) && $values[$field_name] != $form['simple_sitemap'][$field_name]['#default_value']) { // Fix for values appearing in a sub array on a commerce product entity.
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Gets the values needed to display the priority dropdown setting.
   *
   * @return array $options
   */
  public static function getPrioritySelectValues() {
    $options = array();
    foreach(range(0, self::PRIORITY_HIGHEST) as $value) {
      $value = $value / self::PRIORITY_DIVIDER;
      $options[(string)$value] = (string)$value;
    }
    return $options;
  }
}
