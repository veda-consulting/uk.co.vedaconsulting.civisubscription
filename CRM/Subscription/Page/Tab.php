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
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2016
 */
class CRM_Subscription_Page_Tab extends CRM_Core_Page {
  public $_permission = NULL;
  public $_contactId = NULL;

  /**
   * called when action is browse.
   */
  public function browse() {
    $controller = new CRM_Core_Controller_Simple('CRM_Subscription_Form_Search', ts('Subscriptions'), $this->_action);
    $controller->setEmbedded(TRUE);
    $controller->reset();
    $controller->set('cid', $this->_contactId);
    $controller->set('context', 'subscription');
    $controller->set('limit', '25');
    $controller->process();
    $controller->run();

    if ($this->_contactId) {
      $displayName = CRM_Contact_BAO_Contact::displayName($this->_contactId);
      $this->assign('displayName', $displayName);
      $this->ajaxResponse['tabCount'] = CRM_Subscription_BAO_Subscription::getContactSubscriptionCount($this->_contactId);
      // Refresh other tabs with related data
      $this->ajaxResponse['updateTabs'] = array(
        '#tab_contribute' => CRM_Contact_BAO_Contact::getCountComponent('contribution', $this->_contactId),
        '#tab_activity' => CRM_Contact_BAO_Contact::getCountComponent('activity', $this->_contactId),
      );
    }
  }

  /**
   * called when action is view.
   *
   * @return null
   */
  public function view() {
    $controller = new CRM_Core_Controller_Simple('CRM_Subscription_Form_SubscriptionView',
      'View Subscription',
      $this->_action
    );
    $controller->setEmbedded(TRUE);
    $controller->set('id', $this->_id);
    $controller->set('cid', $this->_contactId);

    return $controller->run();
  }

  /**
   * called when action is update or new.
   *
   * @return null
   */
  public function edit() {
    $controller = new CRM_Core_Controller_Simple('CRM_Subscription_Form_Subscription',
      'Create Subscription',
      $this->_action
    );
    $controller->setEmbedded(TRUE);
    $controller->set('id', $this->_id);
    $controller->set('cid', $this->_contactId);

    return $controller->run();
  }

  public function preProcess() {
    $context = CRM_Utils_Request::retrieve('context', 'String', $this);
    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE, 'browse');
    $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this);

    if ($context == 'standalone') {
      $this->_action = CRM_Core_Action::ADD;
    }
    else {
      $this->_contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $this, TRUE);
      $this->assign('contactId', $this->_contactId);

      // check logged in url permission
      CRM_Contact_Page_View::checkUserPermission($this);
    }

    $this->assign('action', $this->_action);

    if ($this->_permission == CRM_Core_Permission::EDIT && !CRM_Core_Permission::check('edit subscriptions')) {
      // demote to view since user does not have edit subscription rights
      $this->_permission = CRM_Core_Permission::VIEW;
      $this->assign('permission', 'view');
    }
  }

  /**
   * the main function that is called when the page loads, it decides the which action has to be taken for the page.
   *
   * @return null
   */
  public function run() {
    $this->preProcess();

    // check if we can process credit card registration
    $this->assign('newCredit', CRM_Core_Config::isEnabledBackOfficeCreditCardPayments());

    self::setContext($this);

    if ($this->_action & CRM_Core_Action::VIEW) {
      $this->view();
    }
    elseif ($this->_action & (CRM_Core_Action::UPDATE | CRM_Core_Action::ADD | CRM_Core_Action::DELETE)) {
      $this->edit();
    }
    elseif ($this->_action & CRM_Core_Action::DETACH) {
      CRM_Subscription_BAO_Subscription::cancel($this->_id);
      $session = CRM_Core_Session::singleton();
      $session->setStatus(ts('Subscription has been Cancelled and all scheduled (not completed) payments have been cancelled.<br />'));
      CRM_Utils_System::redirect($session->popUserContext());
    }
    else {
      $this->browse();
    }

    return parent::run();
  }

  /**
   * Get context.
   *
   * @param $form
   */
  public static function setContext(&$form) {
    $context = CRM_Utils_Request::retrieve('context', 'String', $form, FALSE, 'search');

    $qfKey = CRM_Utils_Request::retrieve('key', 'String', $form);
    // validate the qfKey
    if (!CRM_Utils_Rule::qfKey($qfKey)) {
      $qfKey = NULL;
    }

    switch ($context) {
      case 'dashboard':
      case 'subscriptionDashboard':
        $url = CRM_Utils_System::url('civicrm/subscription', 'reset=1');
        break;

      case 'search':
        $urlParams = 'force=1';
        if ($qfKey) {
          $urlParams .= "&qfKey=$qfKey";
        }

        $url = CRM_Utils_System::url('civicrm/subscription/search', $urlParams);
        break;

      case 'user':
        $url = CRM_Utils_System::url('civicrm/user', 'reset=1');
        break;

      case 'subscription':
        $url = CRM_Utils_System::url('civicrm/contact/view',
          "reset=1&force=1&cid={$form->_contactId}&selectedChild=subscription"
        );
        break;

      case 'home':
        $url = CRM_Utils_System::url('civicrm/dashboard', 'force=1');
        break;

      case 'activity':
        $url = CRM_Utils_System::url('civicrm/contact/view',
          "reset=1&force=1&cid={$form->_contactId}&selectedChild=activity"
        );
        break;

      case 'standalone':
        $url = CRM_Utils_System::url('civicrm/dashboard', 'reset=1');
        break;

      default:
        $cid = NULL;
        if ($form->_contactId) {
          $cid = '&cid=' . $form->_contactId;
        }
        $url = CRM_Utils_System::url('civicrm/subscription/search',
          'force=1' . $cid
        );
        break;
    }
    $session = CRM_Core_Session::singleton();
    $session->pushUserContext($url);
  }

}
