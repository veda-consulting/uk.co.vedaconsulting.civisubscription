<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2016                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 * This class introduces component to the system and provides all the
 * information about it. It needs to extend CRM_Core_Component_Info
 * abstract class.
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2016
 */
class CRM_Subscription_Info extends CRM_Core_Component_Info {

  /**
   * @inheritDoc
   */
  protected $keyword = 'subscription';

  /**
   * Provides base information about the component.
   * Needs to be implemented in component's information
   * class.
   *
   * @return array
   *   collection of required component settings
   */
  public function getInfo() {
    return array(
      'name' => 'CiviSubscription',
      'translatedName' => ts('CiviSubscription'),
      'title' => ts('CiviCRM Subscription Engine'),
      'search' => 1,
      'showActivitiesInCore' => 1,
    );
  }


  /**
   * @inheritDoc
   * Provides permissions that are used by component.
   * Needs to be implemented in component's information
   * class.
   *
   * NOTE: if using conditionally permission return,
   * implementation of $getAllUnconditionally is required.
   *
   * @param bool $getAllUnconditionally
   * @param bool $descriptions
   *   Whether to return permission descriptions
   *
   * @return array|null
   *   collection of permissions, null if none
   */
  public function getPermissions($getAllUnconditionally = FALSE, $descriptions = FALSE) {
    $permissions = array(
      'access CiviSubscription' => array(
        ts('access CiviSubscription'),
        ts('View subscription'),
      ),
      'edit subscriptions' => array(
        ts('edit subscriptions'),
        ts('Create and update subscriptions'),
      ),
      'delete in CiviSubscription' => array(
        ts('delete in CiviSubscription'),
        ts('Delete subscriptions'),
      ),
    );

    if (!$descriptions) {
      foreach ($permissions as $name => $attr) {
        $permissions[$name] = array_shift($attr);
      }
    }

    return $permissions;
  }

  /**
   * @inheritDoc
   * Provides information about user dashboard element
   * offered by this component.
   *
   * @return array|null
   *   collection of required dashboard settings,
   *                    null if no element offered
   */
  public function getUserDashboardElement() {
    return array(
      'name' => ts('Subscriptions'),
      'title' => ts('Your Subscription(s)'),
      // we need to check this permission since you can click on contribution page link for making payment
      'perm' => array('make online contributions'),
      'weight' => 15,
    );
  }

  /**
   * @inheritDoc
   * Provides information about user dashboard element
   * offered by this component.
   *
   * @return array|null
   *   collection of required dashboard settings,
   *                    null if no element offered
   */
  public function registerTab() {
    return array(
      'title' => ts('Subscriptions'),
      'url' => 'subscription',
      'weight' => 25,
    );
  }

  /**
   * @inheritDoc
   * Provides information about advanced search pane
   * offered by this component.
   *
   * @return array|null
   *   collection of required pane settings,
   *                    null if no element offered
   */
  public function registerAdvancedSearchPane() {
    return array(
      'title' => ts('Subscriptions'),
      'weight' => 25,
    );
  }

  /**
   * @inheritDoc
   * Provides potential activity types that this
   * component might want to register in activity history.
   * Needs to be implemented in component's information
   * class.
   *
   * @return array|null
   *   collection of activity types
   */
  public function getActivityTypes() {
    return NULL;
  }

  /**
   * add shortcut to Create New.
   * @param $shortCuts
   */
  public function creatNewShortcut(&$shortCuts) {
    if (CRM_Core_Permission::check('access CiviSubscription') &&
      CRM_Core_Permission::check('edit subscriptions')
    ) {
      $shortCuts = array_merge($shortCuts, array(
        array(
          'path' => 'civicrm/subscription/add',
          'query' => 'reset=1&action=add&context=standalone',
          'ref' => 'new-subscription',
          'title' => ts('Subscription'),
        ),
      ));
    }
  }

}