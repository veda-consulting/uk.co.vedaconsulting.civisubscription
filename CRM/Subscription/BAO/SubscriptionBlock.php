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
class CRM_Subscription_BAO_SubscriptionBlock extends CRM_Subscription_DAO_SubscriptionBlock {

  /**
   * Class constructor.
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * Retrieve DB object based on input parameters.
   *
   * It also stores all the retrieved values in the default array.
   *
   * @param array $params
   *   (reference ) an assoc array of name/value pairs.
   * @param array $defaults
   *   (reference ) an assoc array to hold the flattened values.
   *
   * @return CRM_Subscription_BAO_SubscriptionBlock
   */
  public static function retrieve(&$params, &$defaults) {
    $subscriptionBlock = new CRM_Subscription_DAO_SubscriptionBlock();
    $subscriptionBlock->copyValues($params);
    if ($subscriptionBlock->find(TRUE)) {
      CRM_Core_DAO::storeValues($subscriptionBlock, $defaults);
      return $subscriptionBlock;
    }
    return NULL;
  }

  /**
   * Takes an associative array and creates a subscriptionBlock object.
   *
   * @param array $params
   *   (reference ) an assoc array of name/value pairs.
   *
   * @return CRM_Subscription_BAO_SubscriptionBlock
   */
  public static function &create(&$params) {
    $transaction = new CRM_Core_Transaction();
    $subscriptionBlock = self::add($params);

    if (is_a($subscriptionBlock, 'CRM_Core_Error')) {
      $subscriptionBlock->rollback();
      return $subscriptionBlock;
    }

    $params['id'] = $subscriptionBlock->id;

    $transaction->commit();

    return $subscriptionBlock;
  }

  /**
   * Add subscriptionBlock.
   *
   * @param array $params
   *   Reference array contains the values submitted by the form.
   *
   *
   * @return object
   */
  public static function add(&$params) {

    if (!empty($params['id'])) {
      CRM_Utils_Hook::pre('edit', 'SubscriptionBlock', $params['id'], $params);
    }
    else {
      CRM_Utils_Hook::pre('create', 'SubscriptionBlock', NULL, $params);
    }

    $subscriptionBlock = new CRM_Subscription_DAO_SubscriptionBlock();

    // fix for subscription_frequency_unit
    $freqUnits = CRM_Utils_Array::value('subscription_frequency_unit', $params);

    if ($freqUnits && is_array($freqUnits)) {
      unset($params['subscription_frequency_unit']);
      $newFreqUnits = array();
      foreach ($freqUnits as $k => $v) {
        if ($v) {
          $newFreqUnits[$k] = $v;
        }
      }

      $freqUnits = $newFreqUnits;
      if (is_array($freqUnits) && !empty($freqUnits)) {
        $freqUnits = implode(CRM_Core_DAO::VALUE_SEPARATOR, array_keys($freqUnits));
        $subscriptionBlock->subscription_frequency_unit = $freqUnits;
      }
      else {
        $subscriptionBlock->subscription_frequency_unit = '';
      }
    }

    $subscriptionBlock->copyValues($params);
    $result = $subscriptionBlock->save();

    if (!empty($params['id'])) {
      CRM_Utils_Hook::post('edit', 'SubscriptionBlock', $subscriptionBlock->id, $subscriptionBlock);
    }
    else {
      CRM_Utils_Hook::post('create', 'Subscription', $subscriptionBlock->id, $subscriptionBlock);
    }

    return $result;
  }

  /**
   * Delete the subscriptionBlock.
   *
   * @param int $id
   *   SubscriptionBlock id.
   *
   * @return mixed|null
   */
  public static function deleteSubscriptionBlock($id) {
    CRM_Utils_Hook::pre('delete', 'SubscriptionBlock', $id, CRM_Core_DAO::$_nullArray);

    $transaction = new CRM_Core_Transaction();

    $results = NULL;

    $dao = new CRM_Subscription_DAO_SubscriptionBlock();
    $dao->id = $id;
    $results = $dao->delete();

    $transaction->commit();

    CRM_Utils_Hook::post('delete', 'SubscriptionBlock', $dao->id, $dao);

    return $results;
  }

  /**
   * Return Subscription  Block info in Contribution Pages.
   *
   * @param int $pageID
   *   Contribution page id.
   *
   * @return array
   */
  public static function getSubscriptionBlock($pageID) {
    $subscriptionBlock = array();

    $dao = new CRM_Subscription_DAO_SubscriptionBlock();
    $dao->entity_table = 'civicrm_contribution_page';
    $dao->entity_id = $pageID;
    if ($dao->find(TRUE)) {
      CRM_Core_DAO::storeValues($dao, $subscriptionBlock);
    }

    return $subscriptionBlock;
  }

  /**
   * Build Subscription Block in Contribution Pages.
   *
   * @param CRM_Core_Form $form
   */
  public static function buildSubscriptionBlock($form) {
    //build subscription payment fields.
    if (!empty($form->_values['subscription_id'])) {
      //get all payments required details.
      $allPayments = array();
      $returnProperties = array(
        'status_id',
        'scheduled_date',
        'scheduled_amount',
        'currency',
        'subscription_start_date',
      );
      CRM_Core_DAO::commonRetrieveAll('CRM_Subscription_DAO_SubscriptionPayment', 'subscription_id',
        $form->_values['subscription_id'], $allPayments, $returnProperties
      );
      // get all status
      $allStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');

      $nextPayment = array();
      $isNextPayment = FALSE;
      $overduePayments = array();
      foreach ($allPayments as $payID => $value) {
        if ($allStatus[$value['status_id']] == 'Overdue') {
          $overduePayments[$payID] = array(
            'id' => $payID,
            'scheduled_amount' => CRM_Utils_Rule::cleanMoney($value['scheduled_amount']),
            'scheduled_amount_currency' => $value['currency'],
            'scheduled_date' => CRM_Utils_Date::customFormat($value['scheduled_date'],
              '%B %d'
            ),
          );
        }
        elseif (!$isNextPayment &&
          $allStatus[$value['status_id']] == 'Pending'
        ) {
          // get the next payment.
          $nextPayment = array(
            'id' => $payID,
            'scheduled_amount' => CRM_Utils_Rule::cleanMoney($value['scheduled_amount']),
            'scheduled_amount_currency' => $value['currency'],
            'scheduled_date' => CRM_Utils_Date::customFormat($value['scheduled_date'],
              '%B %d'
            ),
          );
          $isNextPayment = TRUE;
        }
      }

      // build check box array for payments.
      $payments = array();
      if (!empty($overduePayments)) {
        foreach ($overduePayments as $id => $payment) {
          $label = ts("%1 - due on %2 (overdue)", array(
            1 => CRM_Utils_Money::format(CRM_Utils_Array::value('scheduled_amount', $payment), CRM_Utils_Array::value('scheduled_amount_currency', $payment)),
            2 => CRM_Utils_Array::value('scheduled_date', $payment),
          ));
          $paymentID = CRM_Utils_Array::value('id', $payment);
          $payments[] = $form->createElement('checkbox', $paymentID, NULL, $label, array('amount' => CRM_Utils_Array::value('scheduled_amount', $payment)));
        }
      }

      if (!empty($nextPayment)) {
        $label = ts("%1 - due on %2", array(
          1 => CRM_Utils_Money::format(CRM_Utils_Array::value('scheduled_amount', $nextPayment), CRM_Utils_Array::value('scheduled_amount_currency', $nextPayment)),
          2 => CRM_Utils_Array::value('scheduled_date', $nextPayment),
        ));
        $paymentID = CRM_Utils_Array::value('id', $nextPayment);
        $payments[] = $form->createElement('checkbox', $paymentID, NULL, $label, array('amount' => CRM_Utils_Array::value('scheduled_amount', $nextPayment)));
      }
      // give error if empty or build form for payment.
      if (empty($payments)) {
        CRM_Core_Error::fatal(ts("Oops. It looks like there is no valid payment status for online payment."));
      }
      else {
        $form->assign('is_subscription_payment', TRUE);
        $form->addGroup($payments, 'subscription_amount', ts('Make Subscription Payment(s):'), '<br />');
      }
    }
    else {

      $subscriptionBlock = self::getSubscriptionBlock($form->_id);

      // build form for subscription creation.
      $subscriptionOptions = array(
        '0' => ts('I want to make a one-time contribution'),
        '1' => ts('I subscription to contribute this amount every'),
      );
      $form->addRadio('is_subscription', ts('Subscription Frequency Interval'), $subscriptionOptions,
        NULL, array('<br/>')
      );
      $form->addElement('text', 'subscription_installments', ts('Installments'), array('size' => 3));

      if (!empty($subscriptionBlock['is_subscription_interval'])) {
        $form->assign('is_subscription_interval', CRM_Utils_Array::value('is_subscription_interval', $subscriptionBlock));
        $form->addElement('text', 'subscription_frequency_interval', NULL, array('size' => 3));
      }
      else {
        $form->add('hidden', 'subscription_frequency_interval', 1);
      }
      // Frequency unit drop-down label suffixes switch from *ly to *(s)
      $freqUnitVals = explode(CRM_Core_DAO::VALUE_SEPARATOR, $subscriptionBlock['subscription_frequency_unit']);
      $freqUnits = array();
      $frequencyUnits = CRM_Core_OptionGroup::values('recur_frequency_units');
      foreach ($freqUnitVals as $key => $val) {
        if (array_key_exists($val, $frequencyUnits)) {
          $freqUnits[$val] = !empty($subscriptionBlock['is_subscription_interval']) ? "{$frequencyUnits[$val]}(s)" : $frequencyUnits[$val];
        }
      }
      $form->addElement('select', 'subscription_frequency_unit', NULL, $freqUnits);
      // CRM-18854
      if (CRM_Utils_Array::value('is_subscription_start_date_visible', $subscriptionBlock)) {
        if (CRM_Utils_Array::value('subscription_start_date', $subscriptionBlock)) {
          $defaults = array();
          $date = (array) json_decode($subscriptionBlock['subscription_start_date']);
          list($field, $value) = each($date);
          switch ($field) {
            case 'contribution_date':
              $form->addDate('start_date', ts('First installment payment'));
              $paymentDate = $value = date('m/d/Y');
              list($defaults['start_date'], $defaults['start_date_time']) = CRM_Utils_Date::setDateDefaults(NULL);
              $form->assign('is_date', TRUE);
              break;

            case 'calendar_date':
              $form->addDate('start_date', ts('First installment payment'));
              list($defaults['start_date'], $defaults['start_date_time']) = CRM_Utils_Date::setDateDefaults($value);
              $form->assign('is_date', TRUE);
              $paymentDate = $value;
              break;

            case 'calendar_month':
              $month = CRM_Utils_Date::getCalendarDayOfMonth();
              $form->add('select', 'start_date', ts('Day of month installments paid'), $month);
              $paymentDate = CRM_Subscription_BAO_Subscription::getPaymentDate($value);
              list($defaults['start_date'], $defaults['start_date_time']) = CRM_Utils_Date::setDateDefaults($paymentDate);
              break;

            default:
              break;

          }
          $form->setDefaults($defaults);
          $form->assign('start_date_display', $paymentDate);
          $form->assign('start_date_editable', FALSE);
          if (CRM_Utils_Array::value('is_subscription_start_date_editable', $subscriptionBlock)) {
            $form->assign('start_date_editable', TRUE);
            if ($field == 'calendar_month') {
              $form->assign('is_date', FALSE);
              $form->setDefaults(array('start_date' => $value));
            }
          }
        }
      }
    }
  }

}
