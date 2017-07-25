<?php

namespace Drupal\menugroup\Plugin\GroupContentEnabler;

use Drupal\group\Entity\GroupType;
use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Class MenugroupDeriver extending DeriverBase.
 */
class MenugroupDeriver extends DeriverBase {

  /**
   * A{@inheritdoc}.
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach (GroupType::loadMultiple() as $name => $group_type) {
      $label = $group_type->label();

      $this->derivatives[$name] = [
        'entity_bundle' => $name,
        'label' => t('Menugroup') . " ($label)",
        'description' => t('Adds Menu to groups both publicly and privately.', ['%type' => $label]),
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
