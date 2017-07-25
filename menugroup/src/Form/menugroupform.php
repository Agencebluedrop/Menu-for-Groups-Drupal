<?php

namespace Drupal\menugroup\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\system\Entity\Menu;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Path\CurrentPathStack;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a singe text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class Menugroupform extends FormBase {

  /**
   * Build the simple form.
   *
   * A build form method constructs an array that defines how markup and
   * other form elements are included in an HTML form.
   *
   * @param array $form
   *   Default form array structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Object containing current form state.
   *
   * @return array
   *   The render array defining the elements of the form.
   */

  protected $path;

  /**
   * Creates a new MenuController object.
   *
   * @param \Drupal\Core\Menu\MenuParentFormSelectorInterface $menu_parent_form
   *   The menu parent form service.
   */
  public function __construct(CurrentPathStack $pathStack) {

    $this->path = $pathStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path.current')
    );
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['title_menu'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title Menu'),
      '#description' => $this->t('Title must be at least 5 characters in length.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Menu name'),
      '#maxlength' => 64,
      '#description' => $this->t('A unique name to construct the URL for the menu. It must only contain lowercase letters, numbers and hyphens.'),
      '#machine_name' => [
        'exists' => [$this, 'menuNameExists'],
        'source' => ['title_menu'],
        'replace_pattern' => '[^a-z0-9-]+',
        'replace' => '-',
      ],
      '#required' => TRUE,
      '#disabled' => 0,
      '#default_value' => '',

    ];

    // Group submit handlers in an actions element with a key of "actions" so
    // that it gets styled correctly, and so that other modules may add actions
    // to the form. This is not required, but is convention.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Add a submit button that handles the submission of the form.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['#theme'] = 'menu_form';

    return $form;
  }

  /**
   * Getter method for Form ID.
   *
   * The form ID is used in implementations of hook_form_alter() to allow other
   * modules to alter the render array built by this form controller.  it must
   * be unique site wide. It normally starts with the providing module's name.
   *
   * @return string
   *   The unique ID of the form defined by this class.
   */
  public function getFormId() {
    return 'Menugroupform';
  }

  /**
   * Function menuNameExists to check for machine_name.
   */
  public function menuNameExists($value) {
    // Check first to see if a menu with this ID exists.
    $row = \Drupal::database()
      ->query("select count(*) as c from config where name=:value", [":value" => 'system.menu.' . $value])
      ->fetchObject()->c;
    if ($row) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Implements form validation.
   *
   * The validateForm method is the default method called to validate input on
   * a form.
   *
   * @param array $form
   *   The render array of the currently built form.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   Object describing the current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $val = $form_state->getValue('id');

    if ($this->menuNameExists($val)) {
      // Set an error for the form element with a key of "title".
      $form_state->setErrorByName('id', $this->t('machine name must be unique'));
    }
  }

  /**
   * Function Save.
   *
   * @save function.
   */
  public function save(array $form, FormStateInterface $form_state) {
    $menu = $this->entity;
    $status = $menu->save();
    $edit_link = $this->entity->link($this->t('Edit'));
    if ($status == SAVED_UPDATED) {
      drupal_set_message($this->t('Menu %label has been updated.', ['%label' => $menu->label()]));
      $this->logger('menu')->notice('Menu %label has been updated.', ['%label' => $menu->label(), 'link' => $edit_link]);
    }
    else {
      drupal_set_message($this->t('Menu %label has been added.', ['%label' => $menu->label()]));
      $this->logger('menu')->notice('Menu %label has been added.', ['%label' => $menu->label(), 'link' => $edit_link]);
    }
    $form_state->setRedirectUrl($this->entity->urlInfo('edit-form'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $val = $form_state->getValues();
    $menu = Menu::create([
      'id' => $val['id'],
      'label' => $val['title_menu'],
      'description' => '',
    ]);
    $menu->save();
    $current_path = $this->path->getPath();
    $path_args = explode('/', $current_path);
    \Drupal::database()->query("INSERT INTO group_group_menu_menugroup (group_id, menu_id) VALUES(" . $path_args[2] . ",'" . $val['id'] . "');");
  }

  /**
   * Recursive helper function for buildOverviewForm().
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The tree retrieved by \Drupal\Core\Menu\MenuLinkTreeInterface::load().
   * @param int $delta
   *   The default number of menu items used in the menu weight selector is 50.
   *
   * @return array
   *   The overview tree form.
   */
  public function buildOverviewTreeForm(array $tree, $delta) {
    $form = &$this->overviewTreeForm;
    $tree_access_cacheability = new CacheableMetadata();
    foreach ($tree as $element) {
      $tree_access_cacheability = $tree_access_cacheability->merge(CacheableMetadata::createFromObject($element->access));

      // Only render accessible links.
      if (!$element->access->isAllowed()) {
        continue;
      }

      /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
      $link = $element->link;
      if ($link) {
        $id = 'menu_plugin_id:' . $link->getPluginId();
        $form[$id]['#item'] = $element;
        $form[$id]['#attributes'] = $link->isEnabled() ? ['class' => ['menu-enabled']] : ['class' => ['menu-disabled']];
        $form[$id]['title'] = Link::fromTextAndUrl($link->getTitle(), $link->getUrlObject())->toRenderable();
        if (!$link->isEnabled()) {
          $form[$id]['title']['#suffix'] = ' (' . $this->t('disabled') . ')';
        }
        // @todo Remove this in https://www.drupal.org/node/2568785.
        elseif ($id === 'menu_plugin_id:user.logout') {
          $form[$id]['title']['#suffix'] = ' (' . $this->t('<q>Log in</q> for anonymous users') . ')';
        }
        // @todo Remove this in https://www.drupal.org/node/2568785.
        elseif (($url = $link->getUrlObject()) && $url->isRouted() && $url->getRouteName() == 'user.page') {
          $form[$id]['title']['#suffix'] = ' (' . $this->t('logged in users only') . ')';
        }

        $form[$id]['enabled'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable @title menu link', ['@title' => $link->getTitle()]),
          '#title_display' => 'invisible',
          '#default_value' => $link->isEnabled(),
        ];
        $form[$id]['weight'] = [
          '#type' => 'weight',
          '#delta' => $delta,
          '#default_value' => $link->getWeight(),
          '#title' => $this->t('Weight for @title', ['@title' => $link->getTitle()]),
          '#title_display' => 'invisible',
        ];
        $form[$id]['id'] = [
          '#type' => 'hidden',
          '#value' => $link->getPluginId(),
        ];
        $form[$id]['parent'] = [
          '#type' => 'hidden',
          '#default_value' => $link->getParent(),
        ];
        // Build a list of operations.
        $operations = [];
        $operations['edit'] = [
          'title' => $this->t('Edit'),
        ];
        // Allow for a custom edit link per plugin.
        $edit_route = $link->getEditRoute();
        if ($edit_route) {
          $operations['edit']['url'] = $edit_route;
          // Bring the user back to the menu overview.
          $operations['edit']['query'] = $this->getDestinationArray();
        }
        else {
          // Fall back to the standard edit link.
          $operations['edit'] += [
            'url' => Url::fromRoute('menu_ui.link_edit', ['menu_link_plugin' => $link->getPluginId()]),
          ];
        }
        // Links can either be reset or deleted, not both.
        if ($link->isResettable()) {
          $operations['reset'] = [
            'title' => $this->t('Reset'),
            'url' => Url::fromRoute('menu_ui.link_reset', ['menu_link_plugin' => $link->getPluginId()]),
          ];
        }
        elseif ($delete_link = $link->getDeleteRoute()) {
          $operations['delete']['url'] = $delete_link;
          $operations['delete']['query'] = $this->getDestinationArray();
          $operations['delete']['title'] = $this->t('Delete');
        }
        if ($link->isTranslatable()) {
          $operations['translate'] = [
            'title' => $this->t('Translate'),
            'url' => $link->getTranslateRoute(),
          ];
        }
        $form[$id]['operations'] = [
          '#type' => 'operations',
          '#links' => $operations,
        ];
      }

      if ($element->subtree) {
        $this->buildOverviewTreeForm($element->subtree, $delta);
      }
    }

    $tree_access_cacheability
      ->merge(CacheableMetadata::createFromRenderArray($form))
      ->applyTo($form);

    return $form;
  }

}
