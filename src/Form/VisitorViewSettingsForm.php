<?php

namespace Drupal\visitor_view\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure settings for the Visitor View module.
 */
class VisitorViewSettingsForm extends ConfigFormBase {

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The local task manager.
   *
   * @var \Drupal\Core\Menu\LocalTaskManagerInterface
   */
  protected LocalTaskManagerInterface $localTaskManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->moduleExtensionList = $container->get('extension.list.module');
    $instance->localTaskManager = $container->get('plugin.manager.menu.local_task');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'visitor_view_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['visitor_view.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('visitor_view.settings');

    $current_label = $form_state->getValue('button_label') ?? $config->get('button_label') ?? 'Preview site';
    $current_icon = $form_state->getValue('button_icon') ?? $config->get('button_icon') ?? 'arrow-square-out';

    $form['preview_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Live Preview'),
      '#prefix' => '<div id="visitor-view-live-preview">',
      '#suffix' => '</div>',
    ];

    $form['preview_wrapper']['preview_button'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'style' => 'display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; background-color: #222330; color: #ffffff; border-radius: 4px; font-size: 14px; font-weight: bold; width: fit-content;',
      ],
      'icon' => [
        '#type' => 'icon',
        '#pack_id' => 'visitor_view',
        '#icon_id' => $current_icon,
        '#settings' => ['size' => 18],
      ],
      'label' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $current_label,
      ],
    ];

    $form['display_location'] = [
      '#type' => 'radios',
      '#title' => $this->t('Button Location'),
      '#description' => $this->t('Choose where the Visitor View link should appear. This applies to both the Classic Toolbar and the Navigation module.'),
      '#options' => [
        'top_bar' => $this->t('Top Bar'),
        'local_tasks' => $this->t('Local Tasks'),
      ],
      '#default_value' => $config->get('display_location') ?: 'top_bar',
    ];

    $form['button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button Label'),
      '#description' => $this->t('The text displayed next to the icon.'),
      '#default_value' => $config->get('button_label') ?: 'Preview site',
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updatePreview',
        'wrapper' => 'visitor-view-live-preview',
        'event' => 'change',
      ],
    ];

    $form['button_icon'] = [
      '#type' => 'select',
      '#title' => $this->t('Button Icon'),
      '#description' => $this->t('Select the icon to display.'),
      '#options' => $this->getIconOptions(),
      '#default_value' => $config->get('button_icon') ?: 'arrow-square-out',
      '#ajax' => [
        'callback' => '::updatePreview',
        'wrapper' => 'visitor-view-live-preview',
        'event' => 'change',
      ],
    ];

    $saved_classes = $config->get('classes_to_remove') ?? [];

    if (!is_array($saved_classes)) {
      $saved_classes = [];
    }

    $form['classes_to_remove'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body Classes to Remove'),
      '#description' => $this->t('Enter any additional body classes that should be removed when Visitor View is active. Put each class on a new line. Core toolbar classes (like `admin-toolbar`) are removed automatically.'),
      '#default_value' => implode("\n", $saved_classes),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback to update the live preview.
   */
  public function updatePreview(array &$form, FormStateInterface $form_state): array {
    return $form['preview_wrapper'];
  }

  /**
   * Helper to scan the icons directory and build select options.
   *
   * @return array
   *   An array of icon options formatted for a select field.
   */
  protected function getIconOptions(): array {
    $options = [];
    $module_path = $this->moduleExtensionList->getPath('visitor_view');
    $icons_path = $module_path . '/icons';

    if (is_dir($icons_path)) {
      $files = glob($icons_path . '/*.svg');
      foreach ($files as $file) {
        $filename = basename($file, '.svg');
        $label = ucwords(str_replace(['-', '_'], ' ', $filename));
        $options[$filename] = $label;
      }
    }
    asort($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $classes_string = $form_state->getValue('classes_to_remove');
    $classes_array = array_values(array_filter(preg_split('/\s+/', $classes_string)));

    $this->config('visitor_view.settings')
      ->set('display_location', $form_state->getValue('display_location'))
      ->set('button_label', $form_state->getValue('button_label'))
      ->set('button_icon', $form_state->getValue('button_icon'))
      ->set('classes_to_remove', $classes_array)
      ->save();

    $this->localTaskManager->clearCachedDefinitions();

    Cache::invalidateTags(['local_task', 'rendered']);

    parent::submitForm($form, $form_state);
  }

}
