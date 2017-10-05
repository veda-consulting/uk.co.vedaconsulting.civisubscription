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
class CRM_Subscription_Page_Payment extends CRM_Core_Page {

  /**
   * the main function that is called when the page loads, it decides the which action has to be taken for the page.
   *
   * @return null
   */
  public function run() {
    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE, 'browse');
    $this->_context = CRM_Utils_Request::retrieve('context', 'String', $this);

    $this->assign('action', $this->_action);
    $this->assign('context', $this->_context);

    $this->_contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $this);

    CRM_Subscription_Page_Tab::setContext($this);

    if ($this->_action & CRM_Core_Action::UPDATE) {
      $this->edit();
    }
    else {
      $subscriptionId = CRM_Utils_Request::retrieve('subscriptionId', 'Positive', $this);

      $paymentDetails = CRM_Subscription_BAO_SubscriptionPayment::getSubscriptionPayments($subscriptionId);

      $this->assign('rows', $paymentDetails);
      $this->assign('subscriptionId', $subscriptionId);
      $this->assign('contactId', $this->_contactId);

      // check if we can process credit card contributions
      $this->assign('newCredit', CRM_Core_Config::isEnabledBackOfficeCreditCardPayments());

      // check is the user has view/edit signer permission
      $permission = 'view';
      if (CRM_Core_Permission::check('edit subscriptions')) {
        $permission = 'edit';
      }
      $this->assign('permission', $permission);
    }

    return parent::run();
  }

  /**
   * called when action is update or new.
   *
   * @return null
   */
  public function edit() {
    $controller = new CRM_Core_Controller_Simple('CRM_Subscription_Form_Payment',
      'Update Subscription Payment',
      $this->_action
    );

    $subscriptionPaymentId = CRM_Utils_Request::retrieve('ppId', 'Positive', $this);

    $controller->setEmbedded(TRUE);
    $controller->set('id', $subscriptionPaymentId);

    return $controller->run();
  }

}
