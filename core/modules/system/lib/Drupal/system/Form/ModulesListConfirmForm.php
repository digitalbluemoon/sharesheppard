<?php

/**
 * @file
 * Contains \Drupal\system\Form\ModulesListConfirmForm.
 */

namespace Drupal\system\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Builds a confirmation form for enabling modules with dependencies.
 */
class ModulesListConfirmForm extends ConfirmFormBase implements ContainerInjectionInterface {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The expirable key value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $keyValueExpirable;

  /**
   * An associative list of modules to enable or disable.
   *
   * @var array
   */
  protected $modules = array();

  /**
   * Constructs a ModulesListConfirmForm object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $key_value_expirable
   *   The key value expirable factory.
   */
  public function __construct(ModuleHandlerInterface $module_handler, KeyValueStoreExpirableInterface $key_value_expirable) {
    $this->moduleHandler = $module_handler;
    $this->keyValueExpirable = $key_value_expirable;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('keyvalue.expirable')->get('module_list')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Some required modules must be enabled');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {
    return array(
      'route_name' => 'system.modules_list',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Continue');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Would you like to continue with the above?');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'system_modules_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $account = $this->currentUser()->id();
    $this->modules = $this->keyValueExpirable->get($account);

    // Redirect to the modules list page if the key value store is empty.
    if (!$this->modules) {
      return new RedirectResponse($this->urlGenerator()->generate('system.modules_list', array(), TRUE));
    }

    $items = array();
    // Display a list of required modules that have to be installed as well but
    // were not manually selected.
    foreach ($this->modules['dependencies'] as $module => $dependencies) {
      $items[] = format_plural(count($dependencies), 'You must enable the @required module to install @module.', 'You must enable the @required modules to install @module.', array(
        '@module' => $this->modules['enable'][$module],
        '@required' => implode(', ', $dependencies),
      ));
    }

    foreach ($this->modules['missing'] as $name => $dependents) {
      $items[] = format_plural(count($dependents), 'The @module module is missing, so the following module will be disabled: @depends.', 'The @module module is missing, so the following modules will be disabled: @depends.', array(
        '@module' => $name,
        '@depends' => implode(', ', $dependents),
      ));
    }

    $form['message'] = array(
      '#theme' => 'item_list',
      '#items' => $items,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    // Remove the key value store entry.
    $account = $this->currentUser()->id();
    $this->keyValueExpirable->delete($account);

    // Gets list of modules prior to install process.
    $before = $this->moduleHandler->getModuleList();

    // Installs, enables, and disables modules.
    if (!empty($this->modules['enable'])) {
      $this->moduleHandler->enable(array_keys($this->modules['enable']));
    }
    if (!empty($this->modules['disable'])) {
      $this->moduleHandler->disable(array_keys($this->modules['disable']));
    }

    // Gets module list after install process, flushes caches and displays a
    // message if there are changes.
    if ($before != $this->moduleHandler->getModuleList()) {
      drupal_flush_all_caches();
      drupal_set_message($this->t('The configuration options have been saved.'));
    }

    $form_state['redirect'] = 'admin/modules';
  }

}
