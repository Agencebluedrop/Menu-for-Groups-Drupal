<?php

namespace Drupal\menugroup\Plugin\GroupContentEnabler;

use Drupal\group\Plugin\GroupContentEnablerBase;

/**
 * Provides a content enabler for menugroup.
 *
 * @GroupContentEnabler(
 *   id = "menugroup",
 *   label = @Translation("Menugroup"),
 *   description = @Translation("Adds menu to groups."),
 *   entity_type_id = "group",
 *   pretty_path_key = "group",
 *   deriver = "Drupal\menugroup\Plugin\GroupContentEnabler\MenugroupDeriver",
 *   links = {
 *     "collection" = "/group/{group}/menu",
 *   }
 * )
 */
class Menugroup extends GroupContentEnablerBase {

  /**
   * {@inheritdoc}
   */
  public function getPermissions() {
    // Override default permission titles and descriptions.
    $permissions["Administer group menu"] = [
      'title' => 'Administer group menu',
      'description' => 'Allows you to create and edit menu that immediately belong to this group.',
    ];

    return $permissions;
  }

}
