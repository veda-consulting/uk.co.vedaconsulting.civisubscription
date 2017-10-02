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
class CRM_Subscription_BAO_Subscription extends CRM_Subscription_DAO_Subscription {

  /**
   * Static field for all the subscription information that we can potentially export.
   *
   * @var array
   */
  static $_exportableFields = NULL;

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
   * @return CRM_Subscription_BAO_Subscription
   */
  public static function retrieve(&$params, &$defaults) {
    $subscription = new CRM_Subscription_DAO_Subscription();
    $subscription->copyValues($params);
    if ($subscription->find(TRUE)) {
      CRM_Core_DAO::storeValues($subscription, $defaults);
      return $subscription;
    }
    return NULL;
  }

  /**
   * Add subscription.
   *
   * @param array $params
   *   Reference array contains the values submitted by the form.
   *
   *
   * @return object
   */
  public static function add(&$params) {
    if (!empty($params['id'])) {
      CRM_Utils_Hook::pre('edit', 'Subscription', $params['id'], $params);
    }
    else {
      CRM_Utils_Hook::pre('create', 'Subscription', NULL, $params);
    }

    $subscription = new CRM_Subscription_DAO_Subscription();

    // if subscription is complete update end date as current date
    if ($subscription->status_id == 1) {
      $subscription->end_date = date('Ymd');
    }

    $subscription->copyValues($params);

    // set currency for CRM-1496
    if (!isset($subscription->currency)) {
      $subscription->currency = CRM_Core_Config::singleton()->defaultCurrency;
    }

    $result = $subscription->save();

    if (!empty($params['id'])) {
      CRM_Utils_Hook::post('edit', 'Subscription', $subscription->id, $subscription);
    }
    else {
      CRM_Utils_Hook::post('create', 'Subscription', $subscription->id, $subscription);
    }

    return $result;
  }

  /**
   * Given the list of params in the params array, fetch the object
   * and store the values in the values array
   *
   * @param array $params
   *   Input parameters to find object.
   * @param array $values
   *   Output values of the object.
   * @param array $returnProperties
   *   If you want to return specific fields.
   *
   * @return array
   *   associated array of field values
   */
  public static function &getValues(&$params, &$values, $returnProperties = NULL) {
    if (empty($params)) {
      return NULL;
    }
    CRM_Core_DAO::commonRetrieve('CRM_Subscription_BAO_Subscription', $params, $values, $returnProperties);
    return $values;
  }

  /**
   * Takes an associative array and creates a subscription object.
   *
   * @param array $params
   *   (reference ) an assoc array of name/value pairs.
   *
   * @return CRM_Subscription_BAO_Subscription
   */
  public static function create(&$params) {
    // FIXME: a cludgy hack to fix the dates to MySQL format
    $dateFields = array('start_date', 'create_date', 'acknowledge_date', 'modified_date', 'cancel_date', 'end_date');
    foreach ($dateFields as $df) {
      if (isset($params[$df])) {
        $params[$df] = CRM_Utils_Date::isoToMysql($params[$df]);
      }
    }

    $isRecalculateSubscriptionPayment = self::isPaymentsRequireRecalculation($params);
    $transaction = new CRM_Core_Transaction();

    $paymentParams = array();
    $paymentParams['status_id'] = CRM_Utils_Array::value('status_id', $params);
    if (!empty($params['installment_amount'])) {
      $params['amount'] = $params['installment_amount'] * $params['installments'];
    }

    if (!isset($params['subscription_status_id']) && !isset($params['status_id'])) {
      if (isset($params['contribution_id'])) {
        if ($params['installments'] > 1) {
          $params['status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Subscription_BAO_Subscription', 'status_id', 'In Progress');
        }
      }
      else {
        if (!empty($params['id'])) {
          $params['status_id'] = CRM_Subscription_BAO_SubscriptionPayment::calculateSubscriptionStatus($params['id']);
        }
        else {
          $params['status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Subscription_BAO_Subscription', 'status_id', 'Pending');
        }
      }
    }

    $subscription = self::add($params);
    if (is_a($subscription, 'CRM_Core_Error')) {
      $subscription->rollback();
      return $subscription;
    }

    // handle custom data.
    if (!empty($params['custom']) &&
      is_array($params['custom'])
    ) {
      CRM_Core_BAO_CustomValueTable::store($params['custom'], 'civicrm_subscription', $subscription->id);
    }

    // skip payment stuff inedit mode
    if (!isset($params['id']) || $isRecalculateSubscriptionPayment) {

      // if subscription is pending delete all payments and recreate.
      if ($isRecalculateSubscriptionPayment) {
        CRM_Subscription_BAO_SubscriptionPayment::deletePayments($subscription->id);
      }

      // building payment params
      $paymentParams['subscription_id'] = $subscription->id;
      $paymentKeys = array(
        'amount',
        'installments',
        'scheduled_date',
        'frequency_unit',
        'currency',
        'frequency_day',
        'frequency_interval',
        'contribution_id',
        'installment_amount',
        'actual_amount',
      );
      foreach ($paymentKeys as $key) {
        $paymentParams[$key] = CRM_Utils_Array::value($key, $params, NULL);
      }
      CRM_Subscription_BAO_SubscriptionPayment::create($paymentParams);
    }

    $transaction->commit();

    $url = CRM_Utils_System::url('civicrm/contact/view/subscription',
      "action=view&reset=1&id={$subscription->id}&cid={$subscription->contact_id}&context=home"
    );

    $recentOther = array();
    if (CRM_Core_Permission::checkActionPermission('CiviSubscription', CRM_Core_Action::UPDATE)) {
      $recentOther['editUrl'] = CRM_Utils_System::url('civicrm/contact/view/subscription',
        "action=update&reset=1&id={$subscription->id}&cid={$subscription->contact_id}&context=home"
      );
    }
    if (CRM_Core_Permission::checkActionPermission('CiviSubscription', CRM_Core_Action::DELETE)) {
      $recentOther['deleteUrl'] = CRM_Utils_System::url('civicrm/contact/view/subscription',
        "action=delete&reset=1&id={$subscription->id}&cid={$subscription->contact_id}&context=home"
      );
    }

    $contributionTypes = CRM_Contribute_PseudoConstant::financialType();
    $title = CRM_Contact_BAO_Contact::displayName($subscription->contact_id) . ' - (' . ts('Subscription') . ' ' . CRM_Utils_Money::format($subscription->amount, $subscription->currency) . ' - ' . CRM_Utils_Array::value($subscription->financial_type_id, $contributionTypes) . ')';

    // add the recently created Subscription
    CRM_Utils_Recent::add($title,
      $url,
      $subscription->id,
      'Subscription',
      $subscription->contact_id,
      NULL,
      $recentOther
    );

    return $subscription;
  }

  /**
   * Is this a change to an existing pending subscription requiring payment schedule changes.
   *
   * If the subscription is pending the code (slightly lazily) deletes & recreates subscription payments.
   *
   * If the payment dates or amounts have been manually edited then this can cause data loss. We can mitigate this to
   * some extent by making sure we have a change that could potentially affect the schedule (rather than just a
   * custom data change or similar).
   *
   * This calculation needs to be performed before update takes place as previous & new subscriptions are compared.
   *
   * @param array $params
   *
   * @return bool
   */
  protected static function isPaymentsRequireRecalculation($params) {
    if (empty($params['is_subscription_pending']) || empty($params['id'])) {
      return FALSE;
    }
    $scheduleChangingParameters = array(
      'amount',
      'frequency_unit',
      'frequency_interval',
      'frequency_day',
      'installments',
      'start_date',
    );
    $existingSubscriptionDAO = new CRM_Subscription_BAO_Subscription();
    $existingSubscriptionDAO->id = $params['id'];
    $existingSubscriptionDAO->find(TRUE);
    foreach ($scheduleChangingParameters as $parameter) {
      if ($parameter == 'start_date') {
        if (strtotime($params[$parameter]) != strtotime($existingSubscriptionDAO->$parameter)) {
          return TRUE;
        }
      }
      elseif ($params[$parameter] != $existingSubscriptionDAO->$parameter) {
        return TRUE;
      }
    }
  }

  /**
   * Delete the subscription.
   *
   * @param int $id
   *   Subscription id.
   *
   * @return mixed
   */
  public static function deleteSubscription($id) {
    CRM_Utils_Hook::pre('delete', 'Subscription', $id, CRM_Core_DAO::$_nullArray);

    $transaction = new CRM_Core_Transaction();

    // check for no Completed Payment records with the subscription
    $payment = new CRM_Subscription_DAO_SubscriptionPayment();
    $payment->subscription_id = $id;
    $payment->find();

    while ($payment->fetch()) {
      // also delete associated contribution.
      if ($payment->contribution_id) {
        CRM_Contribute_BAO_Contribution::deleteContribution($payment->contribution_id);
      }
      $payment->delete();
    }

    $dao = new CRM_Subscription_DAO_Subscription();
    $dao->id = $id;
    $results = $dao->delete();

    $transaction->commit();

    CRM_Utils_Hook::post('delete', 'Subscription', $dao->id, $dao);

    // delete the recently created Subscription
    $subscriptionRecent = array(
      'id' => $id,
      'type' => 'Subscription',
    );
    CRM_Utils_Recent::del($subscriptionRecent);

    return $results;
  }

  /**
   * Get the amount details date wise.
   *
   * @param string $status
   * @param string $startDate
   * @param string $endDate
   *
   * @return array|null
   */
  public static function getTotalAmountAndCount($status = NULL, $startDate = NULL, $endDate = NULL) {
    $where = array();
    $select = $from = $queryDate = NULL;
    $statusId = CRM_Core_PseudoConstant::getKey('CRM_Subscription_BAO_Subscription', 'status_id', $status);

    switch ($status) {
      case 'Completed':
        $where[] = 'status_id != ' . CRM_Core_PseudoConstant::getKey('CRM_Subscription_BAO_Subscription', 'status_id', 'Cancelled');
        break;

      case 'Cancelled':
      case 'In Progress':
      case 'Pending':
      case 'Overdue':
        $where[] = 'status_id = ' . $statusId;
        break;
    }

    if ($startDate) {
      $where[] = "create_date >= '" . CRM_Utils_Type::escape($startDate, 'Timestamp') . "'";
    }
    if ($endDate) {
      $where[] = "create_date <= '" . CRM_Utils_Type::escape($endDate, 'Timestamp') . "'";
    }

    $whereCond = implode(' AND ', $where);

    $query = "
SELECT sum( amount ) as subscription_amount, count( id ) as subscription_count, currency
FROM   civicrm_subscription
WHERE  $whereCond AND is_test=0
GROUP BY  currency
";
    $start = substr($startDate, 0, 8);
    $end = substr($endDate, 0, 8);
    $pCount = 0;
    $pamount = array();
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      $pCount += $dao->subscription_count;
      $pamount[] = CRM_Utils_Money::format($dao->subscription_amount, $dao->currency);
    }

    $subscription_amount = array(
      'subscription_amount' => implode(', ', $pamount),
      'subscription_count' => $pCount,
      'purl' => CRM_Utils_System::url('civicrm/subscription/search',
        "reset=1&force=1&pstatus={$statusId}&pstart={$start}&pend={$end}&test=0"
      ),
    );

    $where = array();
    switch ($status) {
      case 'Completed':
        $select = 'sum( total_amount ) as received_subscription , count( cd.id ) as received_count';
        $where[] = 'cp.status_id = ' . $statusId . ' AND cp.contribution_id = cd.id AND cd.is_test=0';
        $queryDate = 'receive_date';
        $from = ' civicrm_contribution cd, civicrm_subscription_payment cp';
        break;

      case 'Cancelled':
        $select = 'sum( total_amount ) as received_subscription , count( cd.id ) as received_count';
        $where[] = 'cp.status_id = ' . $statusId . ' AND cp.contribution_id = cd.id AND cd.is_test=0';
        $queryDate = 'receive_date';
        $from = ' civicrm_contribution cd, civicrm_subscription_payment cp';
        break;

      case 'Pending':
        $select = 'sum( scheduled_amount )as received_subscription , count( cp.id ) as received_count';
        $where[] = 'cp.status_id = ' . $statusId . ' AND subscription.is_test=0';
        $queryDate = 'scheduled_date';
        $from = ' civicrm_subscription_payment cp INNER JOIN civicrm_subscription subscription on cp.subscription_id = subscription.id';
        break;

      case 'Overdue':
        $select = 'sum( scheduled_amount ) as received_subscription , count( cp.id ) as received_count';
        $where[] = 'cp.status_id = ' . $statusId . ' AND subscription.is_test=0';
        $queryDate = 'scheduled_date';
        $from = ' civicrm_subscription_payment cp INNER JOIN civicrm_subscription subscription on cp.subscription_id = subscription.id';
        break;
    }

    if ($startDate) {
      $where[] = " $queryDate >= '" . CRM_Utils_Type::escape($startDate, 'Timestamp') . "'";
    }
    if ($endDate) {
      $where[] = " $queryDate <= '" . CRM_Utils_Type::escape($endDate, 'Timestamp') . "'";
    }

    $whereCond = implode(' AND ', $where);

    $query = "
 SELECT $select, cp.currency
 FROM $from
 WHERE  $whereCond
 GROUP BY  cp.currency
";
    if ($select) {
      $dao = CRM_Core_DAO::executeQuery($query);
      $amount = array();
      $count = 0;

      while ($dao->fetch()) {
        $count += $dao->received_count;
        $amount[] = CRM_Utils_Money::format($dao->received_subscription, $dao->currency);
      }

      if ($count) {
        return array_merge($subscription_amount, array(
          'received_amount' => implode(', ', $amount),
          'received_count' => $count,
          'url' => CRM_Utils_System::url('civicrm/subscription/search',
            "reset=1&force=1&status={$statusId}&start={$start}&end={$end}&test=0"
          ),
        ));
      }
    }
    else {
      return $subscription_amount;
    }
    return NULL;
  }

  /**
   * Get list of subscriptions In Honor of contact Ids.
   *
   * @param int $honorId
   *   In Honor of Contact ID.
   *
   * @return array
   *   return the list of subscription fields
   */
  public static function getHonorContacts($honorId) {
    $params = array();
    $honorDAO = new CRM_Contribute_DAO_ContributionSoft();
    $honorDAO->contact_id = $honorId;
    $honorDAO->find();

    // get all status.
    while ($honorDAO->fetch()) {
      $subscriptionPaymentDAO = new CRM_Subscription_DAO_SubscriptionPayment();
      $subscriptionPaymentDAO->contribution_id = $honorDAO->contribution_id;
      if ($subscriptionPaymentDAO->find(TRUE)) {
        $subscriptionDAO = new CRM_Subscription_DAO_Subscription();
        $subscriptionDAO->id = $subscriptionPaymentDAO->subscription_id;
        if ($subscriptionDAO->find(TRUE)) {
          $params[$subscriptionDAO->id] = array(
            'honor_type' => CRM_Core_PseudoConstant::getLabel('CRM_Contribute_BAO_ContributionSoft', 'soft_credit_type_id', $honorDAO->soft_credit_type_id),
            'honorId' => $subscriptionDAO->contact_id,
            'amount' => $subscriptionDAO->amount,
            'status' => CRM_Contribute_PseudoConstant::contributionStatus($subscriptionDAO->status_id),
            'create_date' => $subscriptionDAO->create_date,
            'acknowledge_date' => $subscriptionDAO->acknowledge_date,
            'type' => CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType',
              $subscriptionDAO->financial_type_id, 'name'
            ),
            'display_name' => CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact',
              $subscriptionDAO->contact_id, 'display_name'
            ),
          );
        }
      }
    }

    return $params;
  }

  /**
   * Send Acknowledgment and create activity.
   *
   * @param CRM_Core_Form $form
   *   Form object.
   * @param array $params
   *   An assoc array of name/value pairs.
   */
  public static function sendAcknowledgment(&$form, $params) {
    //handle Acknowledgment.
    $allPayments = $payments = array();

    // get All Payments status types.
    $paymentStatusTypes = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
    $returnProperties = array('status_id', 'scheduled_amount', 'scheduled_date', 'contribution_id');
    // get all paymnets details.
    CRM_Core_DAO::commonRetrieveAll('CRM_Subscription_DAO_SubscriptionPayment', 'subscription_id', $params['id'], $allPayments, $returnProperties);

    if (!empty($allPayments)) {
      foreach ($allPayments as $payID => $values) {
        $contributionValue = $contributionStatus = array();
        if (isset($values['contribution_id'])) {
          $contributionParams = array('id' => $values['contribution_id']);
          $returnProperties = array('contribution_status_id', 'receive_date');
          CRM_Core_DAO::commonRetrieve('CRM_Contribute_DAO_Contribution',
            $contributionParams, $contributionStatus, $returnProperties
          );
          $contributionValue = array(
            'status' => CRM_Utils_Array::value('contribution_status_id', $contributionStatus),
            'receive_date' => CRM_Utils_Array::value('receive_date', $contributionStatus),
          );
        }
        $payments[$payID] = array_merge($contributionValue,
          array(
            'amount' => CRM_Utils_Array::value('scheduled_amount', $values),
            'due_date' => CRM_Utils_Array::value('scheduled_date', $values),
          )
        );

        // get the first valid payment id.
        if (!isset($form->paymentId) && ($paymentStatusTypes[$values['status_id']] == 'Pending' ||
            $paymentStatusTypes[$values['status_id']] == 'Overdue'
          )
        ) {
          $form->paymentId = $values['id'];
        }
      }
    }

    // assign subscription fields value to template.
    $subscriptionFields = array(
      'create_date',
      'total_subscription_amount',
      'frequency_interval',
      'frequency_unit',
      'installments',
      'frequency_day',
      'scheduled_amount',
      'currency',
    );
    foreach ($subscriptionFields as $field) {
      if (!empty($params[$field])) {
        $form->assign($field, $params[$field]);
      }
    }

    // assign all payments details.
    if ($payments) {
      $form->assign('payments', $payments);
    }

    // handle domain token values
    $domain = CRM_Core_BAO_Domain::getDomain();
    $tokens = array(
      'domain' => array('name', 'phone', 'address', 'email'),
      'contact' => CRM_Core_SelectValues::contactTokens(),
    );
    $domainValues = array();
    foreach ($tokens['domain'] as $token) {
      $domainValues[$token] = CRM_Utils_Token::getDomainTokenReplacement($token, $domain);
    }
    $form->assign('domain', $domainValues);

    // handle contact token values.
    $ids = array($params['contact_id']);
    $fields = array_merge(array_keys(CRM_Contact_BAO_Contact::importableFields()),
      array('display_name', 'checksum', 'contact_id')
    );
    foreach ($fields as $key => $val) {
      $returnProperties[$val] = TRUE;
    }
    $details = CRM_Utils_Token::getTokenDetails($ids,
      $returnProperties,
      TRUE, TRUE, NULL,
      $tokens,
      get_class($form)
    );
    $form->assign('contact', $details[0][$params['contact_id']]);

    // handle custom data.
    if (!empty($params['hidden_custom'])) {
      $groupTree = CRM_Core_BAO_CustomGroup::getTree('Subscription', CRM_Core_DAO::$_nullObject, $params['id']);
      $subscriptionParams = array(array('subscription_id', '=', $params['id'], 0, 0));
      $customGroup = array();
      // retrieve custom data
      foreach ($groupTree as $groupID => $group) {
        $customFields = $customValues = array();
        if ($groupID == 'info') {
          continue;
        }
        foreach ($group['fields'] as $k => $field) {
          $field['title'] = $field['label'];
          $customFields["custom_{$k}"] = $field;
        }

        // to build array of customgroup & customfields in it
        CRM_Core_BAO_UFGroup::getValues($params['contact_id'], $customFields, $customValues, FALSE, $subscriptionParams);
        $customGroup[$group['title']] = $customValues;
      }

      $form->assign('customGroup', $customGroup);
    }

    // handle acknowledgment email stuff.
    list($subscriptionrDisplayName,
      $subscriptionrEmail
      ) = CRM_Contact_BAO_Contact_Location::getEmailDetails($params['contact_id']);

    // check for online subscription.
    if (!empty($params['receipt_from_email'])) {
      $userName = CRM_Utils_Array::value('receipt_from_name', $params);
      $userEmail = CRM_Utils_Array::value('receipt_from_email', $params);
    }
    elseif (!empty($params['from_email_id'])) {
      $receiptFrom = $params['from_email_id'];
    }
    elseif ($userID = CRM_Core_Session::singleton()->get('userID')) {
      // check for logged in user.
      list($userName, $userEmail) = CRM_Contact_BAO_Contact_Location::getEmailDetails($userID);
    }
    else {
      // set the domain values.
      $userName = CRM_Utils_Array::value('name', $domainValues);
      $userEmail = CRM_Utils_Array::value('email', $domainValues);
    }

    if (!isset($receiptFrom)) {
      $receiptFrom = "$userName <$userEmail>";
    }

    list($sent, $subject, $message, $html) = CRM_Core_BAO_MessageTemplate::sendTemplate(
      array(
        'groupName' => 'msg_tpl_workflow_subscription',
        'valueName' => 'subscription_acknowledge',
        'contactId' => $params['contact_id'],
        'from' => $receiptFrom,
        'toName' => $subscriptionrDisplayName,
        'toEmail' => $subscriptionrEmail,
      )
    );

    // check if activity record exist for this subscription
    // Acknowledgment, if exist do not add activity.
    $activityType = 'Subscription Acknowledgment';
    $activity = new CRM_Activity_DAO_Activity();
    $activity->source_record_id = $params['id'];
    $activity->activity_type_id = CRM_Core_OptionGroup::getValue('activity_type',
      $activityType,
      'name'
    );

    // FIXME: Translate
    $details = 'Total Amount ' . CRM_Utils_Money::format($params['total_subscription_amount'], CRM_Utils_Array::value('currency', $params)) . ' To be paid in ' . $params['installments'] . ' installments of ' . CRM_Utils_Money::format($params['scheduled_amount'], CRM_Utils_Array::value('currency', $params)) . ' every ' . $params['frequency_interval'] . ' ' . $params['frequency_unit'] . '(s)';

    if (!$activity->find()) {
      $activityParams = array(
        'subject' => $subject,
        'source_contact_id' => $params['contact_id'],
        'source_record_id' => $params['id'],
        'activity_type_id' => CRM_Core_OptionGroup::getValue('activity_type',
          $activityType,
          'name'
        ),
        'activity_date_time' => CRM_Utils_Date::isoToMysql($params['acknowledge_date']),
        'is_test' => $params['is_test'],
        'status_id' => 2,
        'details' => $details,
        'campaign_id' => CRM_Utils_Array::value('campaign_id', $params),
      );

      // lets insert assignee record.
      if (!empty($params['contact_id'])) {
        $activityParams['assignee_contact_id'] = $params['contact_id'];
      }

      if (is_a(CRM_Activity_BAO_Activity::create($activityParams), 'CRM_Core_Error')) {
        CRM_Core_Error::fatal("Failed creating Activity for acknowledgment");
      }
    }
  }

  /**
   * Combine all the exportable fields from the lower levels object.
   *
   * @param bool $checkPermission
   *
   * @return array
   *   array of exportable Fields
   */
  public static function exportableFields($checkPermission = TRUE) {
    if (!self::$_exportableFields) {
      if (!self::$_exportableFields) {
        self::$_exportableFields = array();
      }

      $fields = CRM_Subscription_DAO_Subscription::export();

      $fields = array_merge($fields, CRM_Subscription_DAO_SubscriptionPayment::export());

      // set title to calculated fields
      $calculatedFields = array(
        'subscription_total_paid' => array('title' => ts('Total Paid')),
        'subscription_balance_amount' => array('title' => ts('Balance Amount')),
        'subscription_next_pay_date' => array('title' => ts('Next Payment Date')),
        'subscription_next_pay_amount' => array('title' => ts('Next Payment Amount')),
        'subscription_payment_paid_amount' => array('title' => ts('Paid Amount')),
        'subscription_payment_paid_date' => array('title' => ts('Paid Date')),
        'subscription_payment_status' => array(
          'title' => ts('Subscription Payment Status'),
          'name' => 'subscription_payment_status',
          'data_type' => CRM_Utils_Type::T_STRING,
        ),
      );

      $subscriptionFields = array(
        'subscription_status' => array(
          'title' => 'Subscription Status',
          'name' => 'subscription_status',
          'data_type' => CRM_Utils_Type::T_STRING,
        ),
        'subscription_frequency_unit' => array(
          'title' => 'Subscription Frequency Unit',
          'name' => 'subscription_frequency_unit',
          'data_type' => CRM_Utils_Type::T_ENUM,
        ),
        'subscription_frequency_interval' => array(
          'title' => 'Subscription Frequency Interval',
          'name' => 'subscription_frequency_interval',
          'data_type' => CRM_Utils_Type::T_INT,
        ),
        'subscription_contribution_page_id' => array(
          'title' => 'Subscription Contribution Page Id',
          'name' => 'subscription_contribution_page_id',
          'data_type' => CRM_Utils_Type::T_INT,
        ),
      );

      $fields = array_merge($fields, $subscriptionFields, $calculatedFields);

      // add custom data
      $fields = array_merge($fields, CRM_Core_BAO_CustomField::getFieldsForImport('Subscription', FALSE, FALSE, FALSE, $checkPermission));
      self::$_exportableFields = $fields;
    }

    return self::$_exportableFields;
  }

  /**
   * Get pending or in progress subscriptions.
   *
   * @param int $contactID
   *   Contact id.
   *
   * @return array
   *   associated array of subscription id(s)
   */
  public static function getContactSubscriptions($contactID) {
    $subscriptionDetails = array();
    $subscriptionStatuses = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');

    $status = array();

    // get pending and in progress status
    foreach (array(
               'Pending',
               'In Progress',
               'Overdue',
             ) as $name) {
      if ($statusId = array_search($name, $subscriptionStatuses)) {
        $status[] = $statusId;
      }
    }
    if (empty($status)) {
      return $subscriptionDetails;
    }

    $statusClause = " IN (" . implode(',', $status) . ")";

    $query = "
 SELECT civicrm_subscription.id id
 FROM civicrm_subscription
 WHERE civicrm_subscription.status_id  {$statusClause}
  AND civicrm_subscription.contact_id = %1
";

    $params[1] = array($contactID, 'Integer');
    $subscription = CRM_Core_DAO::executeQuery($query, $params);

    while ($subscription->fetch()) {
      $subscriptionDetails[] = $subscription->id;
    }

    return $subscriptionDetails;
  }

  /**
   * Get subscription record count for a Contact.
   *
   * @param int $contactID
   *
   * @return int
   *   count of subscription records
   */
  public static function getContactSubscriptionCount($contactID) {
    $query = "SELECT count(*) FROM civicrm_subscription WHERE civicrm_subscription.contact_id = {$contactID} AND civicrm_subscription.is_test = 0";
    return CRM_Core_DAO::singleValueQuery($query);
  }

  /**
   * @param array $params
   *
   * @return array
   */
  public static function updateSubscriptionStatus($params) {

    $returnMessages = array();

    $sendReminders = CRM_Utils_Array::value('send_reminders', $params, FALSE);

    $allStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');

    // unset statues that we never use for subscriptions
    foreach (array(
               'Completed',
               'Cancelled',
               'Failed',
             ) as $statusKey) {
      if ($key = CRM_Utils_Array::key($statusKey, $allStatus)) {
        unset($allStatus[$key]);
      }
    }

    $statusIds = implode(',', array_keys($allStatus));
    $updateCnt = 0;

    $query = "
SELECT  subscription.contact_id              as contact_id,
        subscription.id                      as subscription_id,
        subscription.amount                  as amount,
        payment.scheduled_date         as scheduled_date,
        subscription.create_date             as create_date,
        payment.id                     as payment_id,
        subscription.currency                as currency,
        subscription.contribution_page_id    as contribution_page_id,
        payment.reminder_count         as reminder_count,
        subscription.max_reminders           as max_reminders,
        payment.reminder_date          as reminder_date,
        subscription.initial_reminder_day    as initial_reminder_day,
        subscription.additional_reminder_day as additional_reminder_day,
        subscription.status_id               as subscription_status,
        payment.status_id              as payment_status,
        subscription.is_test                 as is_test,
        subscription.campaign_id             as campaign_id,
        SUM(payment.scheduled_amount)  as amount_due,
        ( SELECT sum(civicrm_subscription_payment.actual_amount)
        FROM civicrm_subscription_payment
        WHERE civicrm_subscription_payment.status_id = 1
        AND  civicrm_subscription_payment.subscription_id = subscription.id
        ) as amount_paid
        FROM      civicrm_subscription subscription, civicrm_subscription_payment payment
        WHERE     subscription.id = payment.subscription_id
        AND     payment.status_id IN ( {$statusIds} ) AND subscription.status_id IN ( {$statusIds} )
        GROUP By  payment.id
        ";

    $dao = CRM_Core_DAO::executeQuery($query);

    $now = date('Ymd');
    $subscriptionDetails = $contactIds = $subscriptionPayments = $subscriptionStatus = array();
    while ($dao->fetch()) {
      $checksumValue = CRM_Contact_BAO_Contact_Utils::generateChecksum($dao->contact_id);

      $subscriptionDetails[$dao->payment_id] = array(
        'scheduled_date' => $dao->scheduled_date,
        'amount_due' => $dao->amount_due,
        'amount' => $dao->amount,
        'amount_paid' => $dao->amount_paid,
        'create_date' => $dao->create_date,
        'contact_id' => $dao->contact_id,
        'subscription_id' => $dao->subscription_id,
        'checksumValue' => $checksumValue,
        'contribution_page_id' => $dao->contribution_page_id,
        'reminder_count' => $dao->reminder_count,
        'max_reminders' => $dao->max_reminders,
        'reminder_date' => $dao->reminder_date,
        'initial_reminder_day' => $dao->initial_reminder_day,
        'additional_reminder_day' => $dao->additional_reminder_day,
        'subscription_status' => $dao->subscription_status,
        'payment_status' => $dao->payment_status,
        'is_test' => $dao->is_test,
        'currency' => $dao->currency,
        'campaign_id' => $dao->campaign_id,
      );

      $contactIds[$dao->contact_id] = $dao->contact_id;
      $subscriptionStatus[$dao->subscription_id] = $dao->subscription_status;

      if (CRM_Utils_Date::overdue(CRM_Utils_Date::customFormat($dao->scheduled_date, '%Y%m%d'),
          $now
        ) && $dao->payment_status != array_search('Overdue', $allStatus)
      ) {
        $subscriptionPayments[$dao->subscription_id][$dao->payment_id] = $dao->payment_id;
      }
    }

    // process the updating script...

    foreach ($subscriptionPayments as $subscriptionId => $paymentIds) {
      // 1. update the subscription /subscription payment status. returns new status when an update happens
      $returnMessages[] = "Checking if status update is needed for Subscription Id: {$subscriptionId} (current status is {$allStatus[$subscriptionStatus[$subscriptionId]]})";

      $newStatus = CRM_Subscription_BAO_SubscriptionPayment::updateSubscriptionPaymentStatus($subscriptionId, $paymentIds,
        array_search('Overdue', $allStatus), NULL, 0, FALSE, TRUE
      );
      if ($newStatus != $subscriptionStatus[$subscriptionId]) {
        $returnMessages[] = "- status updated to: {$allStatus[$newStatus]}";
        $updateCnt += 1;
      }
    }

    if ($sendReminders) {
      // retrieve domain tokens
      $domain = CRM_Core_BAO_Domain::getDomain();
      $tokens = array(
        'domain' => array('name', 'phone', 'address', 'email'),
        'contact' => CRM_Core_SelectValues::contactTokens(),
      );

      $domainValues = array();
      foreach ($tokens['domain'] as $token) {
        $domainValues[$token] = CRM_Utils_Token::getDomainTokenReplacement($token, $domain);
      }

      // get the domain email address, since we don't carry w/ object.
      $domainValue = CRM_Core_BAO_Domain::getNameAndEmail();
      $domainValues['email'] = $domainValue[1];

      // retrieve contact tokens

      // this function does NOT return Deceased contacts since we don't want to send them email
      list($contactDetails) = CRM_Utils_Token::getTokenDetails($contactIds,
        NULL,
        FALSE, FALSE, NULL,
        $tokens, 'CRM_UpdateSubscriptionRecord'
      );

      // assign domain values to template
      $template = CRM_Core_Smarty::singleton();
      $template->assign('domain', $domainValues);

      // set receipt from
      $receiptFrom = '"' . $domainValues['name'] . '" <' . $domainValues['email'] . '>';

      foreach ($subscriptionDetails as $paymentId => $details) {
        if (array_key_exists($details['contact_id'], $contactDetails)) {
          $contactId = $details['contact_id'];
          $subscriptionrName = $contactDetails[$contactId]['display_name'];
        }
        else {
          continue;
        }

        if (empty($details['reminder_date'])) {
          $nextReminderDate = new DateTime($details['scheduled_date']);
          $details['initial_reminder_day'] = empty($details['initial_reminder_day']) ? 0 : $details['initial_reminder_day'];
          $nextReminderDate->modify("-" . $details['initial_reminder_day'] . "day");
          $nextReminderDate = $nextReminderDate->format("Ymd");
        }
        else {
          $nextReminderDate = new DateTime($details['reminder_date']);
          $details['additional_reminder_day'] = empty($details['additional_reminder_day']) ? 0 : $details['additional_reminder_day'];
          $nextReminderDate->modify("+" . $details['additional_reminder_day'] . "day");
          $nextReminderDate = $nextReminderDate->format("Ymd");
        }
        if (($details['reminder_count'] < $details['max_reminders'])
          && ($nextReminderDate <= $now)
        ) {

          $toEmail = $doNotEmail = $onHold = NULL;

          if (!empty($contactDetails[$contactId]['email'])) {
            $toEmail = $contactDetails[$contactId]['email'];
          }

          if (!empty($contactDetails[$contactId]['do_not_email'])) {
            $doNotEmail = $contactDetails[$contactId]['do_not_email'];
          }

          if (!empty($contactDetails[$contactId]['on_hold'])) {
            $onHold = $contactDetails[$contactId]['on_hold'];
          }

          // 2. send acknowledgement mail
          if ($toEmail && !($doNotEmail || $onHold)) {
            // assign value to template
            $template->assign('amount_paid', $details['amount_paid'] ? $details['amount_paid'] : 0);
            $template->assign('contact', $contactDetails[$contactId]);
            $template->assign('next_payment', $details['scheduled_date']);
            $template->assign('amount_due', $details['amount_due']);
            $template->assign('checksumValue', $details['checksumValue']);
            $template->assign('contribution_page_id', $details['contribution_page_id']);
            $template->assign('subscription_id', $details['subscription_id']);
            $template->assign('scheduled_payment_date', $details['scheduled_date']);
            $template->assign('amount', $details['amount']);
            $template->assign('create_date', $details['create_date']);
            $template->assign('currency', $details['currency']);
            list($mailSent, $subject, $message, $html) = CRM_Core_BAO_MessageTemplate::sendTemplate(
              array(
                'groupName' => 'msg_tpl_workflow_subscription',
                'valueName' => 'subscription_reminder',
                'contactId' => $contactId,
                'from' => $receiptFrom,
                'toName' => $subscriptionrName,
                'toEmail' => $toEmail,
              )
            );

            // 3. update subscription payment details
            if ($mailSent) {
              CRM_Subscription_BAO_SubscriptionPayment::updateReminderDetails($paymentId);
              $activityType = 'Subscription Reminder';
              $activityParams = array(
                'subject' => $subject,
                'source_contact_id' => $contactId,
                'source_record_id' => $paymentId,
                'assignee_contact_id' => $contactId,
                'activity_type_id' => CRM_Core_OptionGroup::getValue('activity_type',
                  $activityType,
                  'name'
                ),
                'due_date_time' => CRM_Utils_Date::isoToMysql($details['scheduled_date']),
                'is_test' => $details['is_test'],
                'status_id' => 2,
                'campaign_id' => $details['campaign_id'],
              );
              try {
                civicrm_api3('activity', 'create', $activityParams);
              }
              catch (CiviCRM_API3_Exception $e) {
                $returnMessages[] = "Failed creating Activity for Subscription Reminder: " . $e->getMessage();
                return array('is_error' => 1, 'message' => $returnMessages);
              }
              $returnMessages[] = "Payment reminder sent to: {$subscriptionrName} - {$toEmail}";
            }
          }
        }
      }
      // end foreach on $subscriptionDetails
    }
    // end if ( $sendReminders )
    $returnMessages[] = "{$updateCnt} records updated.";

    return array('is_error' => 0, 'messages' => implode("\n\r", $returnMessages));
  }

  /**
   * Mark a subscription (and any outstanding payments) as cancelled.
   *
   * @param int $subscriptionID
   */
  public static function cancel($subscriptionID) {
    $paymentIDs = self::findCancelablePayments($subscriptionID);
    $status = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
    $cancelled = array_search('Cancelled', $status);
    CRM_Subscription_BAO_SubscriptionPayment::updateSubscriptionPaymentStatus($subscriptionID, $paymentIDs, NULL,
      $cancelled, 0, FALSE, TRUE
    );
  }

  /**
   * Find payments which can be safely canceled.
   *
   * @param int $subscriptionID
   * @return array
   *   Array of int (civicrm_subscription_payment.id)
   */
  public static function findCancelablePayments($subscriptionID) {
    $statuses = array_flip(CRM_Contribute_PseudoConstant::contributionStatus());

    $paymentDAO = new CRM_Subscription_DAO_SubscriptionPayment();
    $paymentDAO->subscription_id = $subscriptionID;
    $paymentDAO->whereAdd(sprintf("status_id IN (%d,%d)",
      $statuses['Overdue'],
      $statuses['Pending']
    ));
    $paymentDAO->find();

    $paymentIDs = array();
    while ($paymentDAO->fetch()) {
      $paymentIDs[] = $paymentDAO->id;
    }
    return $paymentIDs;
  }

  /**
   * Is this subscription free from financial transactions (this is important to know as we allow editing
   * when no transactions have taken place - the editing process currently involves deleting all subscription payments & contributions
   * & recreating so we want to block that if appropriate).
   *
   * @param int $subscriptionID
   * @param int $subscriptionStatusID
   * @return bool
   *   do financial transactions exist for this subscription?
   */
  public static function subscriptionHasFinancialTransactions($subscriptionID, $subscriptionStatusID) {
    if (empty($subscriptionStatusID)) {
      // why would this happen? If we can see where it does then we can see if we should look it up.
      // but assuming from form code it CAN be empty.
      return TRUE;
    }
    if (self::isTransactedStatus($subscriptionStatusID)) {
      return TRUE;
    }

    return civicrm_api3('subscription_payment', 'getcount', array(
      'subscription_id' => $subscriptionID,
      'contribution_id' => array('NOT NULL' => TRUE),
    ));
  }

  /**
   * Does this subscription / subscription payment status mean that a financial transaction has taken place?
   * @param int $statusID
   *   Subscription status id.
   *
   * @return bool
   *   is it a transactional status?
   */
  protected static function isTransactedStatus($statusID) {
    if (!in_array($statusID, self::getNonTransactionalStatus())) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get array of non transactional statuses.
   * @return array
   *   non transactional status ids
   */
  protected static function getNonTransactionalStatus() {
    $paymentStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
    return array_flip(array_intersect($paymentStatus, array('Overdue', 'Pending')));
  }


  /**
   * Create array for recur record for subscription.
   * @return array
   *   params for recur record
   */
  public static function buildRecurParams($params) {
    $recurParams = array(
      'is_recur' => TRUE,
      'auto_renew' => TRUE,
      'frequency_unit' => $params['subscription_frequency_unit'],
      'frequency_interval' => $params['subscription_frequency_interval'],
      'installments' => $params['subscription_installments'],
      'start_date' => $params['receive_date'],
    );
    return $recurParams;
  }

  /**
   * Get subscription start date.
   *
   * @return string
   *   start date
   */
  public static function getSubscriptionStartDate($date, $subscriptionBlock) {
    $startDate = (array) json_decode($subscriptionBlock['subscription_start_date']);
    list($field, $value) = each($startDate);
    if (!empty($date) && !CRM_Utils_Array::value('is_subscription_start_date_editable', $subscriptionBlock)) {
      return $date;
    }
    if (empty($date)) {
      $date = $value;
    }
    switch ($field) {
      case 'contribution_date':
        if (empty($date)) {
          $date = date('Ymd');
        }
        break;

      case 'calendar_date':
        $date = date('Ymd', strtotime($date));
        break;

      case 'calendar_month':
        $date = self::getPaymentDate($date);
        $date = date('Ymd', strtotime($date));
        break;

      default:
        break;

    }
    return $date;
  }

  /**
   * Get first payment date for subscription.
   *
   */
  public static function getPaymentDate($day) {
    if ($day == 31) {
      // Find out if current month has 31 days, if not, set it to 30 (last day).
      $t = date('t');
      if ($t != $day) {
        $day = $t;
      }
    }
    $current = date('d');
    switch (TRUE) {
      case ($day == $current):
        $date = date('m/d/Y');
        break;

      case ($day > $current):
        $date = date('m/d/Y', mktime(0, 0, 0, date('m'), $day, date('Y')));
        break;

      case ($day < $current):
        $date = date('m/d/Y', mktime(0, 0, 0, date('m', strtotime("+1 month")), $day, date('Y')));
        break;

      default:
        break;

    }
    return $date;
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
      $result = array_diff($result, array('Failed'));
    }
    return $result;
  }

}
