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

/**
 * This class generates form components for processing a subscription payment.
 */
class CRM_Subscription_Form_Payment extends CRM_Core_Form {

  /**
   * The id of the subscription payment that we are proceessing.
   *
   * @var int
   */
  public $_id;

  /**
   * Set variables up before form is built.
   */
  public function preProcess() {
    // check for edit permission
    if (!CRM_Core_Permission::check('edit subscriptions')) {
      CRM_Core_Error::fatal(ts('You do not have permission to access this page.'));
    }

    $this->_id = CRM_Utils_Request::retrieve('ppId', 'Positive', $this);

    CRM_Utils_System::setTitle(ts('Edit Scheduled Subscription Payment'));
  }

  /**
   * Set default values for the form.
   * the default values are retrieved from the database.
   */
  public function setDefaultValues() {
    $defaults = array();
    if ($this->_id) {
      $params['id'] = $this->_id;
      CRM_Subscription_BAO_SubscriptionPayment::retrieve($params, $defaults);
      list($defaults['scheduled_date']) = CRM_Utils_Date::setDateDefaults($defaults['scheduled_date']);
      if (isset($defaults['contribution_id'])) {
        $this->assign('subscriptionPayment', TRUE);
      }
      $status = CRM_Core_PseudoConstant::getName('CRM_Subscription_BAO_Subscription', 'status_id', $defaults['status_id']);
      $this->assign('status', $status);
    }
    $defaults['option_type'] = 1;
    return $defaults;
  }

  /**
   * Build the form object.
   */
  public function buildQuickForm() {
    // add various dates
    $this->addDate('scheduled_date', ts('Scheduled Date'), TRUE);

    $this->addMoney('scheduled_amount',
      ts('Scheduled Amount'), TRUE,
      array('readonly' => TRUE),
      TRUE,
      'currency',
      NULL,
      TRUE
    );

    $optionTypes = array(
      '1' => ts('Adjust Subscription Payment Schedule?'),
      '2' => ts('Adjust Total Subscription Amount?'),
    );
    $element = $this->addRadio('option_type',
      NULL,
      $optionTypes,
      array(), '<br/>'
    );

    $this->addButtons(array(
        array(
          'type' => 'next',
          'name' => ts('Save'),
          'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
          'isDefault' => TRUE,
        ),
        array(
          'type' => 'cancel',
          'name' => ts('Cancel'),
        ),
      )
    );
  }

  /**
   * Process the form submission.
   */
  public function postProcess() {
    // get the submitted form values.
    $formValues = $this->controller->exportValues($this->_name);
    $params = array();
    $formValues['scheduled_date'] = CRM_Utils_Date::processDate($formValues['scheduled_date']);
    $params['scheduled_date'] = CRM_Utils_Date::format($formValues['scheduled_date']);
    $params['currency'] = CRM_Utils_Array::value('currency', $formValues);
    $now = date('Ymd');

    if (CRM_Utils_Date::overdue(CRM_Utils_Date::customFormat($params['scheduled_date'], '%Y%m%d'), $now)) {
      $params['status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Subscription_BAO_Subscription', 'status_id', 'Overdue');
    }
    else {
      $params['status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Subscription_BAO_Subscription', 'status_id', 'Pending');
    }

    $params['id'] = $this->_id;
    $subscriptionId = CRM_Core_DAO::getFieldValue('CRM_Subscription_DAO_SubscriptionPayment', $params['id'], 'subscription_id');

    CRM_Subscription_BAO_SubscriptionPayment::add($params);
    $adjustTotalAmount = FALSE;
    if (CRM_Utils_Array::value('option_type', $formValues) == 2) {
      $adjustTotalAmount = TRUE;
    }

    $subscriptionScheduledAmount = CRM_Core_DAO::getFieldValue('CRM_Subscription_DAO_SubscriptionPayment',
      $params['id'],
      'scheduled_amount',
      'id'
    );

    $oldestPaymentAmount = CRM_Subscription_BAO_SubscriptionPayment::getOldestSubscriptionPayment($subscriptionId, 2);
    if (($oldestPaymentAmount['count'] != 1) && ($oldestPaymentAmount['id'] == $params['id'])) {
      $oldestPaymentAmount = CRM_Subscription_BAO_SubscriptionPayment::getOldestSubscriptionPayment($subscriptionId);
    }
    if (($formValues['scheduled_amount'] - $subscriptionScheduledAmount) >= $oldestPaymentAmount['amount']) {
      $adjustTotalAmount = TRUE;
    }
    // update subscription status
    CRM_Subscription_BAO_SubscriptionPayment::updateSubscriptionPaymentStatus($subscriptionId,
      array($params['id']),
      $params['status_id'],
      NULL,
      $formValues['scheduled_amount'],
      $adjustTotalAmount
    );

    $statusMsg = ts('Subscription Payment Schedule has been updated.');
    CRM_Core_Session::setStatus($statusMsg, ts('Saved'), 'success');
  }

}
