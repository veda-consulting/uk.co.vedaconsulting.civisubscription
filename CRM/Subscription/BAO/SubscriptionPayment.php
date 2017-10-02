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
class CRM_Subscription_BAO_SubscriptionPayment extends CRM_Subscription_DAO_SubscriptionPayment {

  /**
   * Class constructor.
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * Get subscription payment details.
   *
   * @param int $subscriptionId
   *   Subscription id.
   *
   * @return array
   *   associated array of subscription payment details
   */
  public static function getSubscriptionPayments($subscriptionId) {
    $query = "
SELECT    civicrm_subscription_payment.id id,
          scheduled_amount,
          scheduled_date,
          reminder_date,
          reminder_count,
          actual_amount,
          receive_date,
          civicrm_subscription_payment.currency,
          civicrm_option_value.name as status,
          civicrm_option_value.label as label,
          civicrm_contribution.id as contribution_id
FROM      civicrm_subscription_payment

LEFT JOIN civicrm_contribution ON civicrm_subscription_payment.contribution_id = civicrm_contribution.id
LEFT JOIN civicrm_option_group ON ( civicrm_option_group.name = 'contribution_status' )
LEFT JOIN civicrm_option_value ON ( civicrm_subscription_payment.status_id = civicrm_option_value.value AND
                                    civicrm_option_group.id = civicrm_option_value.option_group_id )
WHERE     subscription_id = %1
";

    $params[1] = array($subscriptionId, 'Integer');
    $payment = CRM_Core_DAO::executeQuery($query, $params);

    $paymentDetails = array();
    while ($payment->fetch()) {
      $paymentDetails[$payment->id]['scheduled_amount'] = $payment->scheduled_amount;
      $paymentDetails[$payment->id]['scheduled_date'] = $payment->scheduled_date;
      $paymentDetails[$payment->id]['reminder_date'] = $payment->reminder_date;
      $paymentDetails[$payment->id]['reminder_count'] = $payment->reminder_count;
      $paymentDetails[$payment->id]['total_amount'] = $payment->actual_amount;
      $paymentDetails[$payment->id]['receive_date'] = $payment->receive_date;
      $paymentDetails[$payment->id]['status'] = $payment->status;
      $paymentDetails[$payment->id]['label'] = $payment->label;
      $paymentDetails[$payment->id]['id'] = $payment->id;
      $paymentDetails[$payment->id]['contribution_id'] = $payment->contribution_id;
      $paymentDetails[$payment->id]['currency'] = $payment->currency;
    }

    return $paymentDetails;
  }

  /**
   * @param array $params
   *
   * @return subscription
   */
  public static function create($params) {
    $transaction = new CRM_Core_Transaction();
    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');

    //calculate the scheduled date for every installment
    $now = date('Ymd') . '000000';
    $statues = $prevScheduledDate = array();
    $prevScheduledDate[1] = CRM_Utils_Date::processDate($params['scheduled_date']);

    if (CRM_Utils_Date::overdue($prevScheduledDate[1], $now)) {
      $statues[1] = array_search('Overdue', $contributionStatus);
    }
    else {
      $statues[1] = array_search('Pending', $contributionStatus);
    }

    for ($i = 1; $i < $params['installments']; $i++) {
      $prevScheduledDate[$i + 1] = self::calculateNextScheduledDate($params, $i);
      if (CRM_Utils_Date::overdue($prevScheduledDate[$i + 1], $now)) {
        $statues[$i + 1] = array_search('Overdue', $contributionStatus);
      }
      else {
        $statues[$i + 1] = array_search('Pending', $contributionStatus);
      }
    }

    if ($params['installment_amount']) {
      $params['scheduled_amount'] = $params['installment_amount'];
    }
    else {
      $params['scheduled_amount'] = round(($params['amount'] / $params['installments']), 2);
    }

    for ($i = 1; $i <= $params['installments']; $i++) {
      // calculate the scheduled amount for every installment.
      if ($i == $params['installments']) {
        $params['scheduled_amount'] = $params['amount'] - ($i - 1) * $params['scheduled_amount'];
      }
      if (!isset($params['contribution_id']) && $params['installments'] > 1) {
        $params['status_id'] = $statues[$i];
      }

      $params['scheduled_date'] = $prevScheduledDate[$i];
      $payment = self::add($params);
      if (is_a($payment, 'CRM_Core_Error')) {
        $transaction->rollback();
        return $payment;
      }

      // we should add contribution id to only first payment record
      if (isset($params['contribution_id'])) {
        unset($params['contribution_id']);
        unset($params['actual_amount']);
      }
    }

    // update subscription status
    self::updateSubscriptionPaymentStatus($params['subscription_id']);

    $transaction->commit();
    return $payment;
  }

  /**
   * Add subscription payment.
   *
   * @param array $params
   *   Associate array of field.
   *
   * @return CRM_Subscription_DAO_SubscriptionPayment
   *   subscription payment id
   */
  public static function add($params) {
    if (!empty($params['id'])) {
      CRM_Utils_Hook::pre('edit', 'SubscriptionPayment', $params['id'], $params);
    }
    else {
      CRM_Utils_Hook::pre('create', 'SubscriptionPayment', NULL, $params);
    }

    $payment = new CRM_Subscription_DAO_SubscriptionPayment();
    $payment->copyValues($params);

    // set currency for CRM-1496
    if (!isset($payment->currency)) {
      $payment->currency = CRM_Core_Config::singleton()->defaultCurrency;
    }

    $result = $payment->save();

    if (!empty($params['id'])) {
      CRM_Utils_Hook::post('edit', 'SubscriptionPayment', $payment->id, $payment);
    }
    else {
      CRM_Utils_Hook::post('create', 'SubscriptionPayment', $payment->id, $payment);
    }

    return $result;
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
   * @return CRM_Subscription_BAO_SubscriptionPayment
   */
  public static function retrieve(&$params, &$defaults) {
    $payment = new CRM_Subscription_BAO_SubscriptionPayment();
    $payment->copyValues($params);
    if ($payment->find(TRUE)) {
      CRM_Core_DAO::storeValues($payment, $defaults);
      return $payment;
    }
    return NULL;
  }

  /**
   * Delete subscription payment.
   *
   * @param int $id
   *
   * @return int
   *   subscription payment id
   */
  public static function del($id) {
    $payment = new CRM_Subscription_DAO_SubscriptionPayment();
    $payment->id = $id;
    if ($payment->find()) {
      $payment->fetch();

      CRM_Utils_Hook::pre('delete', 'SubscriptionPayment', $id, $payment);

      $result = $payment->delete();

      CRM_Utils_Hook::post('delete', 'SubscriptionPayment', $id, $payment);

      return $result;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Delete all subscription payments.
   *
   * @param int $id
   *   Subscription id.
   *
   * @return bool
   */
  public static function deletePayments($id) {
    if (!CRM_Utils_Rule::positiveInteger($id)) {
      return FALSE;
    }

    $transaction = new CRM_Core_Transaction();

    $payment = new CRM_Subscription_DAO_SubscriptionPayment();
    $payment->subscription_id = $id;

    if ($payment->find()) {
      while ($payment->fetch()) {
        //also delete associated contribution.
        if ($payment->contribution_id) {
          CRM_Contribute_BAO_Contribution::deleteContribution($payment->contribution_id);
        }
        self::del($payment->id);
      }
    }

    $transaction->commit();

    return TRUE;
  }

  /**
   * On delete contribution record update associated subscription payment and subscription.
   *
   * @param int $contributionID
   *   Contribution id.
   *
   * @return bool
   */
  public static function resetSubscriptionPayment($contributionID) {
    // get all status
    $allStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');

    $transaction = new CRM_Core_Transaction();

    $payment = new CRM_Subscription_DAO_SubscriptionPayment();
    $payment->contribution_id = $contributionID;
    if ($payment->find(TRUE)) {
      $payment->contribution_id = 'null';
      $payment->status_id = array_search('Pending', $allStatus);
      $payment->scheduled_date = NULL;
      $payment->reminder_date = NULL;
      $payment->scheduled_amount = $payment->actual_amount;
      $payment->actual_amount = 'null';
      $payment->save();

      //update subscription status.
      $subscriptionID = $payment->subscription_id;
      $subscriptionStatusID = self::calculateSubscriptionStatus($subscriptionID);
      CRM_Core_DAO::setFieldValue('CRM_Subscription_DAO_Subscription', $subscriptionID, 'status_id', $subscriptionStatusID);

      $payment->free();
    }

    $transaction->commit();
    return TRUE;
  }

  /**
   * Update Subscription Payment Status.
   *
   * @param int $subscriptionID
   *   , id of subscription.
   * @param array $paymentIDs
   *   , ids of subscription payment(s) to update.
   * @param int $paymentStatusID
   *   , payment status to set.
   * @param int $subscriptionStatusID
   *   Subscription status to change (if needed).
   * @param float|int $actualAmount , actual amount being paid
   * @param bool $adjustTotalAmount
   *   , is amount being paid different from scheduled amount?.
   * @param bool $isScriptUpdate
   *   , is function being called from bin script?.
   *
   * @return int
   *   $newStatus, updated status id (or 0)
   */
  public static function updateSubscriptionPaymentStatus(
    $subscriptionID,
    $paymentIDs = NULL,
    $paymentStatusID = NULL,
    $subscriptionStatusID = NULL,
    $actualAmount = 0,
    $adjustTotalAmount = FALSE,
    $isScriptUpdate = FALSE
  ) {
    $totalAmountClause = '';
    $paymentContributionId = NULL;
    $editScheduled = FALSE;

    // get all statuses
    $allStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');

    // if we get do not get contribution id means we are editing the scheduled payment.
    if (!empty($paymentIDs)) {
      $editScheduled = FALSE;
      $payments = implode(',', $paymentIDs);
      $paymentContributionId = CRM_Core_DAO::getFieldValue('CRM_Subscription_DAO_SubscriptionPayment',
        $payments,
        'contribution_id',
        'id'
      );

      if (!$paymentContributionId) {
        $editScheduled = TRUE;
      }
    }

    // if payment ids are passed, we update payment table first, since payments statuses are not dependent on subscription status
    if ((!empty($paymentIDs) || $subscriptionStatusID == array_search('Cancelled', $allStatus)) && (!$editScheduled || $isScriptUpdate)) {
      if ($subscriptionStatusID == array_search('Cancelled', $allStatus)) {
        $paymentStatusID = $subscriptionStatusID;
      }

      self::updateSubscriptionPayments($subscriptionID, $paymentStatusID, $paymentIDs, $actualAmount, $paymentContributionId, $isScriptUpdate);
    }
    if (!empty($paymentIDs) && $actualAmount) {
      $payments = implode(',', $paymentIDs);
      $subscriptionScheduledAmount = CRM_Core_DAO::getFieldValue('CRM_Subscription_DAO_SubscriptionPayment',
        $payments,
        'scheduled_amount',
        'id'
      );

      $subscriptionStatusId = self::calculateSubscriptionStatus($subscriptionID);
      // Actual Subscription Amount
      $actualSubscriptionAmount = CRM_Core_DAO::getFieldValue('CRM_Subscription_DAO_Subscription',
        $subscriptionID,
        'amount',
        'id'
      );
      // while editing scheduled  we need to check if we are editing last pending
      $lastPending = FALSE;
      if (!$paymentContributionId) {
        $checkPendingCount = self::getOldestSubscriptionPayment($subscriptionID, 2);
        if ($checkPendingCount['count'] == 1) {
          $lastPending = TRUE;
        }
      }

      // check if this is the last payment and adjust the actual amount.
      if ($subscriptionStatusId && $subscriptionStatusId == array_search('Completed', $allStatus) || $lastPending) {
        // last scheduled payment
        if ($actualAmount >= $subscriptionScheduledAmount) {
          $adjustTotalAmount = TRUE;
        }
        elseif (!$adjustTotalAmount) {
          // actual amount is less than the scheduled amount, so enter new subscription payment record
          $subscriptionFrequencyUnit = CRM_Core_DAO::getFieldValue('CRM_Subscription_DAO_Subscription', $subscriptionID, 'frequency_unit', 'id');
          $subscriptionFrequencyInterval = CRM_Core_DAO::getFieldValue('CRM_Subscription_DAO_Subscription', $subscriptionID, 'frequency_interval', 'id');
          $subscriptionScheduledDate = CRM_Core_DAO::getFieldValue('CRM_Subscription_DAO_SubscriptionPayment', $payments, 'scheduled_date', 'id');
          $scheduled_date = CRM_Utils_Date::processDate($subscriptionScheduledDate);
          $date['year'] = (int) substr($scheduled_date, 0, 4);
          $date['month'] = (int) substr($scheduled_date, 4, 2);
          $date['day'] = (int) substr($scheduled_date, 6, 2);
          $newDate = date('YmdHis', mktime(0, 0, 0, $date['month'], $date['day'], $date['year']));
          $ScheduledDate = CRM_Utils_Date::format(CRM_Utils_Date::intervalAdd($subscriptionFrequencyUnit,
            $subscriptionFrequencyInterval, $newDate
          ));
          $subscriptionParams = array(
            'status_id' => array_search('Pending', $allStatus),
            'subscription_id' => $subscriptionID,
            'scheduled_amount' => ($subscriptionScheduledAmount - $actualAmount),
            'scheduled_date' => $ScheduledDate,
          );
          $payment = self::add($subscriptionParams);
          // while editing schedule,  after adding a new subscription payemnt update the scheduled amount of the current payment
          if (!$paymentContributionId) {
            CRM_Core_DAO::setFieldValue('CRM_Subscription_DAO_SubscriptionPayment', $payments, 'scheduled_amount', $actualAmount);
          }
        }
      }
      elseif (!$adjustTotalAmount) {
        // not last schedule amount and also not selected to adjust Total
        $paymentContributionId = CRM_Core_DAO::getFieldValue('CRM_Subscription_DAO_SubscriptionPayment',
          $payments,
          'contribution_id',
          'id'
        );
        self::adjustSubscriptionPayment($subscriptionID, $actualAmount, $subscriptionScheduledAmount, $paymentContributionId, $payments, $paymentStatusID);
        // while editing schedule,  after adding a new subscription payemnt update the scheduled amount of the current payment
        if (!$paymentContributionId) {
          CRM_Core_DAO::setFieldValue('CRM_Subscription_DAO_SubscriptionPayment', $payments, 'scheduled_amount', $actualAmount);
        }
        // after adjusting all payments check if the actual amount was greater than the actual remaining amount , if so then update the total subscription amount.
        $subscriptionStatusId = self::calculateSubscriptionStatus($subscriptionID);
        $balanceQuery = "
 SELECT sum( civicrm_subscription_payment.actual_amount )
 FROM civicrm_subscription_payment
 WHERE civicrm_subscription_payment.subscription_id = %1
 AND civicrm_subscription_payment.status_id = 1
 ";
        $totalPaidParams = array(1 => array($subscriptionID, 'Integer'));
        $totalPaidAmount = CRM_Core_DAO::singleValueQuery($balanceQuery, $totalPaidParams);
        $remainingTotalAmount = ($actualSubscriptionAmount - $totalPaidAmount);
        if (($subscriptionStatusId && $subscriptionStatusId == array_search('Completed', $allStatus)) && (($actualAmount > $remainingTotalAmount) || ($actualAmount >= $actualSubscriptionAmount))) {
          $totalAmountClause = ", civicrm_subscription.amount = {$totalPaidAmount}";
        }
      }
      if ($adjustTotalAmount) {
        $newTotalAmount = ($actualSubscriptionAmount + ($actualAmount - $subscriptionScheduledAmount));
        $totalAmountClause = ", civicrm_subscription.amount = {$newTotalAmount}";
        if (!$paymentContributionId) {
          CRM_Core_DAO::setFieldValue('CRM_Subscription_DAO_SubscriptionPayment', $payments, 'scheduled_amount', $actualAmount);
        }
      }
    }

    $cancelDateClause = $endDateClause = NULL;
    // update subscription and payment status if status is Completed/Cancelled.
    if ($subscriptionStatusID && $subscriptionStatusID == array_search('Cancelled', $allStatus)) {
      $paymentStatusID = $subscriptionStatusID;
      $cancelDateClause = ", civicrm_subscription.cancel_date = CURRENT_TIMESTAMP ";
    }
    else {
      // get subscription status
      $subscriptionStatusID = self::calculateSubscriptionStatus($subscriptionID);
    }

    if ($subscriptionStatusID == array_search('Completed', $allStatus)) {
      $endDateClause = ", civicrm_subscription.end_date = CURRENT_TIMESTAMP ";
    }

    // update subscription status
    $query = "
UPDATE civicrm_subscription
 SET   civicrm_subscription.status_id = %1
       {$cancelDateClause} {$endDateClause} {$totalAmountClause}
WHERE  civicrm_subscription.id = %2
";

    $params = array(
      1 => array($subscriptionStatusID, 'Integer'),
      2 => array($subscriptionID, 'Integer'),
    );

    $dao = CRM_Core_DAO::executeQuery($query, $params);

    return $subscriptionStatusID;
  }

  /**
   * Calculate the base scheduled date. This function effectively 'rounds' the $params['scheduled_date'] value
   * to the first payment date with respect to the frequency day  - ie. if payments are on the 15th of the month the date returned
   * will be the 15th of the relevant month. Then to calculate the payments you can use intervalAdd ie.
   * CRM_Utils_Date::intervalAdd( $params['frequency_unit'], $i * ($params['frequency_interval']) , calculateBaseScheduledDate( &$params )))
   *
   * @param array $params
   *
   * @return array
   *   Next scheduled date as an array
   */
  public static function calculateBaseScheduleDate(&$params) {
    $date = array();
    $scheduled_date = CRM_Utils_Date::processDate($params['scheduled_date']);
    $date['year'] = (int) substr($scheduled_date, 0, 4);
    $date['month'] = (int) substr($scheduled_date, 4, 2);
    $date['day'] = (int) substr($scheduled_date, 6, 2);
    // calculation of schedule date according to frequency day of period
    // frequency day is not applicable for daily installments
    if ($params['frequency_unit'] != 'day' && $params['frequency_unit'] != 'year') {
      if ($params['frequency_unit'] != 'week') {
        // CRM-18316: To calculate subscription scheduled dates at the end of a month.
        $date['day'] = $params['frequency_day'];
        $lastDayOfMonth = date('t', mktime(0, 0, 0, $date['month'], 1, $date['year']));
        if ($lastDayOfMonth < $date['day']) {
          $date['day'] = $lastDayOfMonth;
        }
      }
      elseif ($params['frequency_unit'] == 'week') {

        // for week calculate day of week ie. Sunday,Monday etc. as next payment date
        $dayOfWeek = date('w', mktime(0, 0, 0, $date['month'], $date['day'], $date['year']));
        $frequencyDay = $params['frequency_day'] - $dayOfWeek;

        $scheduleDate = explode("-", date('n-j-Y', mktime(0, 0, 0, $date['month'],
          $date['day'] + $frequencyDay, $date['year']
        )));
        $date['month'] = $scheduleDate[0];
        $date['day'] = $scheduleDate[1];
        $date['year'] = $scheduleDate[2];
      }
    }
    $newdate = date('YmdHis', mktime(0, 0, 0, $date['month'], $date['day'], $date['year']));
    return $newdate;
  }

  /**
   * Calculate next scheduled subscription payment date. Function calculates next subscription payment date.
   *
   * @param array $params
   *   must include frequency unit & frequency interval
   * @param int $paymentNo
   *   number of payment in sequence (e.g. 1 for first calculated payment (treat initial payment as 0)
   * @param string $basePaymentDate
   *   date to calculate payments from. This would normally be the
   *   first day of the subscription (default) & is calculated off the 'scheduled date' param. Returned date will
   *   be equal to basePaymentDate normalised to fit the 'subscription pattern' + number of installments
   *
   * @return string
   *   formatted date
   */
  public static function calculateNextScheduledDate(&$params, $paymentNo, $basePaymentDate = NULL) {
    $interval = $paymentNo * ($params['frequency_interval']);
    if (!$basePaymentDate) {
      $basePaymentDate = self::calculateBaseScheduleDate($params);
    }

    //CRM-18316 - change $basePaymentDate for the end dates of the month eg: 29, 30 or 31.
    if ($params['frequency_unit'] == 'month' && in_array($params['frequency_day'], array(29, 30, 31))) {
      $frequency = $params['frequency_day'];
      extract(date_parse($basePaymentDate));
      $lastDayOfMonth = date('t', mktime($hour, $minute, $second, $month + $interval, 1, $year));
      // Take the last day in case the current month is Feb or frequency_day is set to 31.
      if (in_array($lastDayOfMonth, array(28, 29)) || $frequency == 31) {
        $frequency = 0;
        $interval++;
      }
      $basePaymentDate = array(
        'M' => $month,
        'd' => $frequency,
        'Y' => $year,
      );
    }

    return CRM_Utils_Date::format(
      CRM_Utils_Date::intervalAdd(
        $params['frequency_unit'],
        $interval,
        $basePaymentDate
      )
    );
  }

  /**
   * Calculate the subscription status.
   *
   * @param int $subscriptionId
   *   Subscription id.
   *
   * @return int
   *   $statusId calculated status id of subscription
   */
  public static function calculateSubscriptionStatus($subscriptionId) {
    $paymentStatusTypes = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');

    // retrieve all subscription payments for this particular subscription
    $allSubscriptionPayments = $allStatus = array();
    $returnProperties = array('status_id');
    CRM_Core_DAO::commonRetrieveAll('CRM_Subscription_DAO_SubscriptionPayment', 'subscription_id', $subscriptionId, $allSubscriptionPayments, $returnProperties);

    // build subscription payment statuses
    foreach ($allSubscriptionPayments as $key => $value) {
      $allStatus[$value['id']] = $paymentStatusTypes[$value['status_id']];
    }

    if (array_search('Overdue', $allStatus)) {
      $statusId = array_search('Overdue', $paymentStatusTypes);
    }
    elseif (array_search('Completed', $allStatus)) {
      if (count(array_count_values($allStatus)) == 1) {
        $statusId = array_search('Completed', $paymentStatusTypes);
      }
      else {
        $statusId = array_search('In Progress', $paymentStatusTypes);
      }
    }
    else {
      $statusId = array_search('Pending', $paymentStatusTypes);
    }

    return $statusId;
  }

  /**
   * Update subscription payment table.
   *
   * @param int $subscriptionId
   *   Subscription id.
   * @param int $paymentStatusId
   *   Payment status id to set.
   * @param array $paymentIds
   *   Payment ids to be updated.
   * @param float|int $actualAmount , actual amount being paid
   * @param int $contributionId
   *   , Id of associated contribution when payment is recorded.
   * @param bool $isScriptUpdate
   *   , is function being called from bin script?.
   *
   */
  public static function updateSubscriptionPayments(
    $subscriptionId,
    $paymentStatusId,
    $paymentIds = NULL,
    $actualAmount = 0,
    $contributionId = NULL,
    $isScriptUpdate = FALSE
  ) {
    $allStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
    $paymentClause = NULL;
    if (!empty($paymentIds)) {
      $payments = implode(',', $paymentIds);
      $paymentClause = " AND civicrm_subscription_payment.id IN ( {$payments} )";
    }
    $actualAmountClause = NULL;
    $contributionIdClause = NULL;
    if (isset($contributionId) && !$isScriptUpdate) {
      $contributionIdClause = ", civicrm_subscription_payment.contribution_id = {$contributionId}";
      $actualAmountClause = ", civicrm_subscription_payment.actual_amount = {$actualAmount}";
    }

    $query = "
UPDATE civicrm_subscription_payment
SET    civicrm_subscription_payment.status_id = {$paymentStatusId}
       {$actualAmountClause} {$contributionIdClause}
WHERE  civicrm_subscription_payment.subscription_id = %1
       {$paymentClause}
";

    // get all status
    $params = array(1 => array($subscriptionId, 'Integer'));

    $dao = CRM_Core_DAO::executeQuery($query, $params);
  }

  /**
   * Update subscription payment table when reminder is sent.
   *
   * @param int $paymentId
   *   Payment id.
   */
  public static function updateReminderDetails($paymentId) {
    $query = "
UPDATE civicrm_subscription_payment
SET civicrm_subscription_payment.reminder_date = CURRENT_TIMESTAMP,
    civicrm_subscription_payment.reminder_count = civicrm_subscription_payment.reminder_count + 1
WHERE  civicrm_subscription_payment.id = {$paymentId}
";
    $dao = CRM_Core_DAO::executeQuery($query);
  }

  /**
   * Get oldest pending or in progress subscription payments.
   *
   * @param int $subscriptionID
   *   Subscription id.
   *
   * @param int $limit
   *
   * @return array
   *   associated array of subscription details
   */
  public static function getOldestSubscriptionPayment($subscriptionID, $limit = 1) {
    // get pending / overdue statuses
    $subscriptionStatuses = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');

    // get pending and overdue payments
    $status[] = array_search('Pending', $subscriptionStatuses);
    $status[] = array_search('Overdue', $subscriptionStatuses);

    $statusClause = " IN (" . implode(',', $status) . ")";

    $query = "
SELECT civicrm_subscription_payment.id id, civicrm_subscription_payment.scheduled_amount amount, civicrm_subscription_payment.currency, civicrm_subscription_payment.scheduled_date,civicrm_subscription.financial_type_id
FROM civicrm_subscription, civicrm_subscription_payment
WHERE civicrm_subscription.id = civicrm_subscription_payment.subscription_id
  AND civicrm_subscription_payment.status_id {$statusClause}
  AND civicrm_subscription.id = %1
ORDER BY civicrm_subscription_payment.scheduled_date ASC
LIMIT 0, %2
";

    $params[1] = array($subscriptionID, 'Integer');
    $params[2] = array($limit, 'Integer');
    $payment = CRM_Core_DAO::executeQuery($query, $params);
    $count = 1;
    $paymentDetails = array();
    while ($payment->fetch()) {
      $paymentDetails[] = array(
        'id' => $payment->id,
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'schedule_date' => $payment->scheduled_date,
        'financial_type_id' => $payment->financial_type_id,
        'count' => $count,
      );
      $count++;
    }
    return end($paymentDetails);
  }

  /**
   * @param int $subscriptionID
   * @param $actualAmount
   * @param $subscriptionScheduledAmount
   * @param int $paymentContributionId
   * @param int $pPaymentId
   * @param int $paymentStatusID
   */
  public static function adjustSubscriptionPayment($subscriptionID, $actualAmount, $subscriptionScheduledAmount, $paymentContributionId = NULL, $pPaymentId = NULL, $paymentStatusID = NULL) {
    $allStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
    if ($paymentStatusID == array_search('Cancelled', $allStatus) || $paymentStatusID == array_search('Refunded', $allStatus)) {
      $query = "
SELECT civicrm_subscription_payment.id id
FROM  civicrm_subscription_payment
WHERE civicrm_subscription_payment.contribution_id = {$paymentContributionId}
";
      $paymentsAffected = CRM_Core_DAO::executeQuery($query);
      $paymentIDs = array();
      while ($paymentsAffected->fetch()) {
        $paymentIDs[] = $paymentsAffected->id;
      }
      // Reset the affected values by the amount paid more than the scheduled amount
      foreach ($paymentIDs as $key => $value) {
        $payment = new CRM_Subscription_DAO_SubscriptionPayment();
        $payment->id = $value;
        if ($payment->find(TRUE)) {
          $payment->contribution_id = 'null';
          $payment->status_id = array_search('Pending', $allStatus);
          $payment->scheduled_date = NULL;
          $payment->reminder_date = NULL;
          $payment->scheduled_amount = $subscriptionScheduledAmount;
          $payment->actual_amount = 'null';
          $payment->save();
        }
      }

      // Cancel the initial paid amount
      CRM_Core_DAO::setFieldValue('CRM_Subscription_DAO_SubscriptionPayment', reset($paymentIDs), 'status_id', $paymentStatusID, 'id');
      CRM_Core_DAO::setFieldValue('CRM_Subscription_DAO_SubscriptionPayment', reset($paymentIDs), 'actual_amount', $actualAmount, 'id');

      // Add new payment after the last payment for the subscription
      $allPayments = self::getSubscriptionPayments($subscriptionID);
      $lastPayment = array_pop($allPayments);

      $subscriptionFrequencyUnit = CRM_Core_DAO::getFieldValue('CRM_Subscription_DAO_Subscription', $subscriptionID, 'frequency_unit', 'id');
      $subscriptionFrequencyInterval = CRM_Core_DAO::getFieldValue('CRM_Subscription_DAO_Subscription', $subscriptionID, 'frequency_interval', 'id');
      $subscriptionScheduledDate = $lastPayment['scheduled_date'];
      $scheduled_date = CRM_Utils_Date::processDate($subscriptionScheduledDate);
      $date['year'] = (int) substr($scheduled_date, 0, 4);
      $date['month'] = (int) substr($scheduled_date, 4, 2);
      $date['day'] = (int) substr($scheduled_date, 6, 2);
      $newDate = date('YmdHis', mktime(0, 0, 0, $date['month'], $date['day'], $date['year']));
      $ScheduledDate = CRM_Utils_Date::format(CRM_Utils_Date::intervalAdd($subscriptionFrequencyUnit, $subscriptionFrequencyInterval, $newDate));
      $subscriptionParams = array(
        'status_id' => array_search('Pending', $allStatus),
        'subscription_id' => $subscriptionID,
        'scheduled_amount' => $subscriptionScheduledAmount,
        'scheduled_date' => $ScheduledDate,
      );
      $payment = self::add($subscriptionParams);
    }
    else {
      $oldestPayment = self::getOldestSubscriptionPayment($subscriptionID);
      if (!$paymentContributionId) {
        // means we are editing payment scheduled payment, so get the second pending to update.
        $oldestPayment = self::getOldestSubscriptionPayment($subscriptionID, 2);
        if (($oldestPayment['count'] != 1) && ($oldestPayment['id'] == $pPaymentId)) {
          $oldestPayment = self::getOldestSubscriptionPayment($subscriptionID);
        }
      }

      if ($oldestPayment) {
        // not the last scheduled payment and the actual amount is less than the expected , add it to oldest pending.
        if (($actualAmount != $subscriptionScheduledAmount) && (($actualAmount < $subscriptionScheduledAmount) || (($actualAmount - $subscriptionScheduledAmount) < $oldestPayment['amount']))) {
          $oldScheduledAmount = $oldestPayment['amount'];
          $newScheduledAmount = $oldScheduledAmount + ($subscriptionScheduledAmount - $actualAmount);
          // store new amount in oldest pending payment record.
          CRM_Core_DAO::setFieldValue('CRM_Subscription_DAO_SubscriptionPayment',
            $oldestPayment['id'],
            'scheduled_amount',
            $newScheduledAmount
          );
          if (CRM_Core_DAO::getFieldValue('CRM_Subscription_DAO_SubscriptionPayment', $oldestPayment['id'], 'contribution_id', 'id')) {
            CRM_Core_DAO::setFieldValue('CRM_Subscription_DAO_SubscriptionPayment',
              $oldestPayment['id'],
              'contribution_id',
              $paymentContributionId
            );
          }
        }
        elseif (($actualAmount > $subscriptionScheduledAmount) && (($actualAmount - $subscriptionScheduledAmount) >= $oldestPayment['amount'])) {
          // here the actual amount is greater than expected and also greater than the next installment amount, so update the next installment as complete and again add it to next subsequent pending payment
          // set the actual amount of the next pending to '0', set contribution Id to current contribution Id and status as completed
          $paymentId = array($oldestPayment['id']);
          self::updateSubscriptionPayments($subscriptionID, array_search('Completed', $allStatus), $paymentId, 0, $paymentContributionId);
          CRM_Core_DAO::setFieldValue('CRM_Subscription_DAO_SubscriptionPayment', $oldestPayment['id'], 'scheduled_amount', 0, 'id');
          $oldestPayment = self::getOldestSubscriptionPayment($subscriptionID);
          if (!$paymentContributionId) {
            // means we are editing payment scheduled payment.
            $oldestPaymentAmount = self::getOldestSubscriptionPayment($subscriptionID, 2);
          }
          $newActualAmount = ($actualAmount - $subscriptionScheduledAmount);
          $newSubscriptionScheduledAmount = $oldestPayment['amount'];
          if (!$paymentContributionId) {
            $newActualAmount = ($actualAmount - $subscriptionScheduledAmount);
            $newSubscriptionScheduledAmount = $oldestPaymentAmount['amount'];
            // means we are editing payment scheduled payment, so update scheduled amount.
            CRM_Core_DAO::setFieldValue('CRM_Subscription_DAO_SubscriptionPayment',
              $oldestPaymentAmount['id'],
              'scheduled_amount',
              $newActualAmount
            );
          }
          if ($newActualAmount > 0) {
            self::adjustSubscriptionPayment($subscriptionID, $newActualAmount, $newSubscriptionScheduledAmount, $paymentContributionId);
          }
        }
      }
    }
  }

  /**
   * Override buildOptions to hack out some statuses.
   *
   * @todo instead of using & hacking the shared optionGroup contribution_status use a separate one.
   *
   * @param string $fieldName
   * @param string $context
   * @param array $props
   *
   * @return array|bool
   */
  public static function buildOptions($fieldName, $context = NULL, $props = array()) {
    $result = parent::buildOptions($fieldName, $context, $props);
    if ($fieldName == 'status_id') {
      $result = array_diff($result, array('Failed', 'In Progress'));
    }
    return $result;
  }

}
