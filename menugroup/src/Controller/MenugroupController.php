<?php

namespace Drupal\menugroup\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Access\GroupAccessResult;

/**
 * Returns responses for Menu routes.
 */
class MenugroupController extends ControllerBase {

  /**
   * The menu parent form service.
   *
   * @var \Drupal\Core\Menu\MenuParentFormSelectorInterface
   */
  protected $menuParentSelector;
  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;
  /**
   * Creates a new MenuController object.
   *
   * @param \Drupal\Core\Menu\MenuParentFormSelectorInterface $menu_parent_form
   *   The menu parent form service.
   */

  public function __construct(MenuParentFormSelectorInterface $menu_parent_form) {

    $this->menuParentSelector = $menu_parent_form;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('menu.parent_form_selector'));
  }

  /**
   * Gets all the available menus and menu items as a JavaScript array.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request of the page.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The available menu and menu items.
   */
  public function getParentOptions(Request $request) {
    $available_menus = [];
    if ($menus = $request->request->get('menus')) {
      foreach ($menus as $menu) {
        $available_menus[$menu] = $menu;
      }
    }
    // @todo Update this to use the optional $cacheability parameter, so that
    //   a cacheable JSON response can be sent.
    $options = $this->menuParentSelector->getParentSelectOptions('', $available_menus);

    return new JsonResponse($options);
  }

  /**
   * Route title callback.
   *
   * @param \Drupal\system\MenuInterface $menu
   *   The menu entity.
   *
   * @return array
   *   The menu label as a render array.
   */
  public function menuTitle(MenuInterface $menu) {
    return ['#markup' => $menu->label(), '#allowed_tags' => Xss::getHtmlTagList()];
  }

  /**
   * Provides the form for adding a menu.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to join.
   *
   * @return array
   *   A group join form.
   */
  public function addmenu(GroupInterface $group) {
    $current_path = \Drupal::service('path.current')->getPath();
    $path_args = explode('/', $current_path);
    
    $group = entity_load('group', $path_args[2]);
    $account = \Drupal::currentUser();
    $perm = 'Administer group menu';
    $result_object = GroupAccessResult::allowedIfHasGroupPermission($group, $account, $perm, 'AND');
    $tab = explode('\\', get_class($result_object));
    if ($tab[3] != 'AccessResultAllowed') {
      throw new AccessDeniedHttpException();
    }

    $row = \Drupal::database()
      ->query("SELECT count(*) as c, menu_id FROM group_group_menu_menugroup WHERE group_id = '" . $path_args[2] . "' GROUP BY menu_id")
      ->fetchObject();
    if (!$row->c) {
      $form = \Drupal::formBuilder()->getForm('Drupal\menugroup\Form\Menugroupform');
      return $form;
    }
    else {
      $redirect = new RedirectResponse("/admin/structure/menu/manage/" . $row->menu_id);
      $redirect->send();
      $build = [
        '#type' => 'markup',
        '#markup' => "hello",
      ];
      return $build;
    }
  }

}
