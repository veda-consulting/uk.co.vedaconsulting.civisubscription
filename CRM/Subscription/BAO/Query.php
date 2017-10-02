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
class CRM_Subscription_BAO_Query extends CRM_Core_BAO_Query {
  /**
   * Get subscription fields.
   *
   * @param bool $checkPermission
   *
   * @return array
   */
  public static function getFields($checkPermission = TRUE) {
    return CRM_Subscription_BAO_Subscription::exportableFields($checkPermission);
  }

  /**
   * Build select for Subscription.
   *
   * @param CRM_Contact_BAO_Query $query
   */
  public static function select(&$query) {

    $statusId = implode(',', array_keys(CRM_Core_PseudoConstant::accountOptionValues("contribution_status", NULL, " AND v.name IN  ('Pending', 'Overdue')")));
    if (($query->_mode & CRM_Civisubscription_Utils::MODE_SUBSCRIPTION) || !empty($query->_returnProperties['subscription_id'])) {
      $query->_select['subscription_id'] = 'civicrm_subscription.id as subscription_id';
      $query->_element['subscription_id'] = 1;
      $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
    }

    // add pledge select
    if (!empty($query->_returnProperties['subscription_amount'])) {
      $query->_select['subscription_amount'] = 'civicrm_subscription.amount as subscription_amount';
      $query->_element['subscription_amount'] = 1;
      $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_create_date'])) {
      $query->_select['subscription_create_date'] = 'civicrm_subscription.create_date as subscription_create_date';
      $query->_element['subscription_create_date'] = 1;
      $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_start_date'])) {
      $query->_select['subscription_start_date'] = 'civicrm_subscription.start_date as subscription_start_date';
      $query->_element['subscription_start_date'] = 1;
      $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_status_id'])) {
      $query->_select['subscription_status_id'] = 'subscription_status.value as subscription_status_id';
      $query->_element['subscription_status'] = 1;
      $query->_tables['subscription_status'] = $query->_whereTables['subscription_status'] = 1;
      $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_status'])) {
      $query->_select['subscription_status'] = 'subscription_status.label as subscription_status';
      $query->_element['subscription_status'] = 1;
      $query->_tables['subscription_status'] = $query->_whereTables['subscription_status'] = 1;
      $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_total_paid'])) {
      $query->_select['subscription_total_paid'] = ' (SELECT sum(civicrm_subscription_payment.actual_amount) FROM civicrm_subscription_payment WHERE civicrm_subscription_payment.subscription_id = civicrm_subscription.id AND civicrm_subscription_payment.status_id = 1 ) as subscription_total_paid';
      $query->_element['subscription_total_paid'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_next_pay_date'])) {
      $query->_select['subscription_next_pay_date'] = " (SELECT civicrm_subscription_payment.scheduled_date FROM civicrm_subscription_payment WHERE civicrm_subscription_payment.subscription_id = civicrm_subscription.id AND civicrm_subscription_payment.status_id IN ({$statusId}) ORDER BY civicrm_subscription_payment.scheduled_date ASC LIMIT 0, 1) as subscription_next_pay_date";
      $query->_element['subscription_next_pay_date'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_next_pay_amount'])) {
      $query->_select['subscription_next_pay_amount'] = " (SELECT civicrm_subscription_payment.scheduled_amount FROM civicrm_subscription_payment WHERE civicrm_subscription_payment.subscription_id = civicrm_subscription.id AND civicrm_subscription_payment.status_id IN ({$statusId}) ORDER BY civicrm_subscription_payment.scheduled_date ASC LIMIT 0, 1) as subscription_next_pay_amount";
      $query->_element['subscription_next_pay_amount'] = 1;

      $query->_select['subscription_outstanding_amount'] = " (SELECT sum(civicrm_subscription_payment.scheduled_amount) FROM civicrm_subscription_payment WHERE civicrm_subscription_payment.subscription_id = civicrm_subscription.id AND civicrm_subscription_payment.status_id = 6 ) as subscription_outstanding_amount";
      $query->_element['subscription_outstanding_amount'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_financial_type'])) {
      $query->_select['subscription_financial_type'] = "(SELECT civicrm_financial_type.name FROM civicrm_financial_type WHERE civicrm_financial_type.id = civicrm_subscription.financial_type_id) as subscription_financial_type";
      $query->_element['subscription_financial_type'] = 1;
      $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_contribution_page_id'])) {
      $query->_select['subscription_contribution_page_id'] = 'civicrm_subscription.contribution_page_id as subscription_contribution_page_id';
      $query->_element['subscription_contribution_page_id'] = 1;
      $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_payment_id'])) {
      $query->_select['subscription_payment_id'] = 'civicrm_subscription_payment.id as subscription_payment_id';
      $query->_element['subscription_payment_id'] = 1;
      $query->_tables['civicrm_subscription_payment'] = $query->_whereTables['civicrm_subscription_payment'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_payment_scheduled_amount'])) {
      $query->_select['subscription_payment_scheduled_amount'] = 'civicrm_subscription_payment.scheduled_amount as subscription_payment_scheduled_amount';
      $query->_element['subscription_payment_scheduled_amount'] = 1;
      $query->_tables['civicrm_subscription_payment'] = $query->_whereTables['civicrm_subscription_payment'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_payment_scheduled_date'])) {
      $query->_select['subscription_payment_scheduled_date'] = 'civicrm_subscription_payment.scheduled_date as subscription_payment_scheduled_date';
      $query->_element['subscription_payment_scheduled_date'] = 1;
      $query->_tables['civicrm_subscription_payment'] = $query->_whereTables['civicrm_subscription_payment'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_payment_paid_amount'])) {
      $query->_select['subscription_payment_paid_amount'] = 'civicrm_subscription_payment.actual_amount as subscription_payment_paid_amount';
      $query->_element['subscription_payment_paid_amount'] = 1;
      $query->_tables['civicrm_subscription_payment'] = $query->_whereTables['civicrm_subscription_payment'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_payment_paid_date'])) {
      $query->_select['subscription_payment_paid_date'] = 'payment_contribution.receive_date as subscription_payment_paid_date';
      $query->_element['subscription_payment_paid_date'] = 1;
      $query->_tables['civicrm_subscription_payment'] = $query->_whereTables['civicrm_subscription_payment'] = 1;
      $query->_tables['payment_contribution'] = $query->_whereTables['payment_contribution'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_payment_reminder_date'])) {
      $query->_select['subscription_payment_reminder_date'] = 'civicrm_subscription_payment.reminder_date as subscription_payment_reminder_date';
      $query->_element['subscription_payment_reminder_date'] = 1;
      $query->_tables['civicrm_subscription_payment'] = $query->_whereTables['civicrm_subscription_payment'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_payment_reminder_count'])) {
      $query->_select['subscription_payment_reminder_count'] = 'civicrm_subscription_payment.reminder_count as subscription_payment_reminder_count';
      $query->_element['subscription_payment_reminder_count'] = 1;
      $query->_tables['civicrm_subscription_payment'] = $query->_whereTables['civicrm_subscription_payment'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_payment_status_id'])) {
      $query->_select['subscription_payment_status_id'] = 'payment_status.name as subscription_payment_status_id';
      $query->_element['subscription_payment_status_id'] = 1;
      $query->_tables['payment_status'] = $query->_whereTables['payment_status'] = 1;
      $query->_tables['civicrm_subscription_payment'] = $query->_whereTables['civicrm_subscription_payment'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_payment_status'])) {
      $query->_select['subscription_payment_status'] = 'payment_status.label as subscription_payment_status';
      $query->_element['subscription_payment_status'] = 1;
      $query->_tables['payment_status'] = $query->_whereTables['payment_status'] = 1;
      $query->_tables['civicrm_subscription_payment'] = $query->_whereTables['civicrm_subscription_payment'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_frequency_interval'])) {
      $query->_select['subscription_frequency_interval'] = 'civicrm_subscription.frequency_interval as subscription_frequency_interval';
      $query->_element['subscription_frequency_interval'] = 1;
      $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_frequency_unit'])) {
      $query->_select['subscription_frequency_unit'] = 'civicrm_subscription.frequency_unit as subscription_frequency_unit';
      $query->_element['subscription_frequency_unit'] = 1;
      $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_is_test'])) {
      $query->_select['subscription_is_test'] = 'civicrm_subscription.is_test as subscription_is_test';
      $query->_element['subscription_is_test'] = 1;
      $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
    }
    if (!empty($query->_returnProperties['subscription_campaign_id'])) {
      $query->_select['subscription_campaign_id'] = 'civicrm_subscription.campaign_id as subscription_campaign_id';
      $query->_element['subscription_campaign_id'] = 1;
      $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
    }

    if (!empty($query->_returnProperties['subscription_currency'])) {
      $query->_select['subscription_currency'] = 'civicrm_subscription.currency as subscription_currency';
      $query->_element['subscription_currency'] = 1;
      $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
    }
  }

  /**
   * @param $query
   */
  public static function where(&$query) {
    $grouping = NULL;
    foreach (array_keys($query->_params) as $id) {
      if (empty($query->_params[$id][0])) {
        continue;
      }
      if (substr($query->_params[$id][0], 0, 7) == 'subscription_') {
        if ($query->_mode == CRM_Contact_BAO_QUERY::MODE_CONTACTS) {
          $query->_useDistinct = TRUE;
        }
        $grouping = $query->_params[$id][3];
        self::whereClauseSingle($query->_params[$id], $query);
      }
    }
  }

  /**
   * @param $values
   * @param $query
   */
  public static function whereClauseSingle(&$values, &$query) {
    list($name, $op, $value, $grouping, $wildcard) = $values;

    switch ($name) {
      case 'subscription_create_date_low':
      case 'subscription_create_date_high':
        // process to / from date
        $query->dateQueryBuilder($values,
          'civicrm_subscription', 'subscription_create_date', 'create_date', 'subscription Made'
        );
      case 'subscription_start_date_low':
      case 'subscription_start_date_high':
        // process to / from date
        $query->dateQueryBuilder($values,
          'civicrm_subscription', 'subscription_start_date', 'start_date', 'subscription Start Date'
        );
        return;

      case 'subscription_end_date_low':
      case 'subscription_end_date_high':
        // process to / from date
        $query->dateQueryBuilder($values,
          'civicrm_subscription', 'subscription_end_date', 'end_date', 'subscription End Date'
        );
        return;

      case 'subscription_payment_date_low':
      case 'subscription_payment_date_high':
        // process to / from date
        $query->dateQueryBuilder($values,
          'civicrm_subscription_payment', 'subscription_payment_date', 'scheduled_date', 'Payment Scheduled'
        );
        return;

      case 'subscription_amount':
      case 'subscription_amount_low':
      case 'subscription_amount_high':
        // process min/max amount
        $query->numberRangeBuilder($values,
          'civicrm_subscription', 'subscription_amount', 'amount', 'subscription Amount'
        );
        return;

      case 'subscription_installments_low':
      case 'subscription_installments_high':
        // process min/max amount
        $query->numberRangeBuilder($values,
          'civicrm_subscription', 'subscription_installments', 'installments', 'Number of Installments'
        );
        return;

      case 'subscription_acknowledge_date_is_not_null':
        if ($value) {
          $op = "IS NOT NULL";
          $query->_qill[$grouping][] = ts('subscription Acknowledgement Sent');
        }
        else {
          $op = "IS NULL";
          $query->_qill[$grouping][] = ts('subscription Acknowledgement  Not Sent');
        }
        $query->_where[$grouping][] = CRM_Contact_BAO_Query::buildClause("civicrm_subscription.acknowledge_date", $op);
        return;

      case 'subscription_payment_status_id':
      case 'subscription_status_id':
        if ($name == 'subscription_status_id') {
          $tableName = 'civicrm_subscription';
          $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
          $label = "Subscription Status";
        }
        else {
          $tableName = 'civicrm_subscription_payment';
          $query->_tables['civicrm_subscription_payment'] = $query->_whereTables['civicrm_subscription_payment'] = 1;
          $label = "Subscription Payment Status";
        }
        $name = 'status_id';
        if (!empty($value) && is_array($value) && !in_array(key($value), CRM_Core_DAO::acceptedSQLOperators(), TRUE)) {
          $value = array('IN' => $value);
        }

        $query->_where[$grouping][] = CRM_Contact_BAO_Query::buildClause("$tableName.$name",
          $op,
          $value,
          'Integer'
        );
        list($qillop, $qillVal) = CRM_Contact_BAO_Query::buildQillForFieldValue('CRM_Contribute_DAO_Contribution', 'contribution_status_id', $value, $op);
        $query->_qill[$grouping][] = ts('%1 %2 %3', array(1 => $label, 2 => $qillop, 3 => $qillVal));
        return;

      case 'subscription_test':
      case 'subscription_is_test':
        // We dont want to include all tests for sql OR CRM-7827
        if (!$value || $query->getOperator() != 'OR') {
          $query->_where[$grouping][] = CRM_Contact_BAO_Query::buildClause('civicrm_subscription.is_test',
            $op,
            $value,
            'Boolean'
          );
          if ($value) {
            $query->_qill[$grouping][] = ts('Subscription is a Test');
          }
          $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
        }
        return;

      case 'subscription_financial_type_id':
        $type = CRM_Contribute_PseudoConstant::financialType($value);
        $query->_where[$grouping][] = CRM_Contact_BAO_Query::buildClause('civicrm_subscription.financial_type_id',
          $op,
          $value,
          'Integer'
        );
        $query->_qill[$grouping][] = ts('Financial Type - %1', array(1 => $type));
        $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
        return;

      case 'subscription_contribution_page_id':
        $page = CRM_Contribute_PseudoConstant::contributionPage($value);
        $query->_where[$grouping][] = CRM_Contact_BAO_Query::buildClause('civicrm_subscription.contribution_page_id',
          $op,
          $value,
          'Integer'
        );
        $query->_qill[$grouping][] = ts('Financial Page - %1', array(1 => $page));
        $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
        return;

      case 'subscription_id':
        $query->_where[$grouping][] = CRM_Contact_BAO_Query::buildClause("civicrm_subscription.id",
          $op,
          $value,
          "Integer"
        );
        $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
        return;

      case 'subscription_frequency_interval':
        $query->_where[$grouping][] = "civicrm_subscription.frequency_interval $op $value";
        $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
        return;

      case 'subscription_frequency_unit':
        $query->_where[$grouping][] = "civicrm_subscription.frequency_unit $op $value";
        $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
        return;

      case 'subscription_contact_id':
      case 'subscription_campaign_id':
        $name = str_replace('subscription_', '', $name);
        $query->_where[$grouping][] = CRM_Contact_BAO_Query::buildClause("civicrm_subscription.$name", $op, $value, 'Integer');
        list($op, $value) = CRM_Contact_BAO_Query::buildQillForFieldValue('CRM_Subscription_DAO_Subscription', $name, $value, $op);
        $label = ($name == 'campaign_id') ? 'Campaign' : 'Contact ID';
        $query->_qill[$grouping][] = ts('%1 %2 %3', array(1 => $label, 2 => $op, 3 => $value));
        $query->_tables['civicrm_subscription'] = $query->_whereTables['civicrm_subscription'] = 1;
        return;
    }
  }

  /**
   * From clause.
   *
   * @param string $name
   * @param string $mode
   * @param string $side
   *
   * @return null|string
   */
  public static function from($name, $mode, $side) {
    $from = NULL;

    switch ($name) {
      case 'civicrm_subscription':
        $from = " $side JOIN civicrm_subscription  ON civicrm_subscription.contact_id = contact_a.id ";
        break;

      case 'subscription_status':
        $from .= " $side JOIN civicrm_option_group option_group_subscription_status ON (option_group_subscription_status.name = 'contribution_status')";
        $from .= " $side JOIN civicrm_option_value subscription_status ON (civicrm_subscription.status_id = subscription_status.value AND option_group_subscription_status.id = subscription_status.option_group_id ) ";
        break;

      case 'subscription_financial_type':
        $from .= " $side JOIN civicrm_financial_type ON civicrm_subscription.financial_type_id = civicrm_financial_type.id ";
        break;

      case 'civicrm_subscription_payment':
        $from .= " $side JOIN civicrm_subscription_payment  ON civicrm_subscription_payment.subscription_id = civicrm_subscription.id ";
        break;

      case 'payment_contribution':
        $from .= " $side JOIN civicrm_contribution payment_contribution ON civicrm_subscription_payment.contribution_id  = payment_contribution.id ";
        break;

      case 'payment_status':
        $from .= " $side JOIN civicrm_option_group option_group_payment_status ON (option_group_payment_status.name = 'contribution_status')";
        $from .= " $side JOIN civicrm_option_value payment_status ON (civicrm_subscription_payment.status_id = payment_status.value AND option_group_payment_status.id = payment_status.option_group_id ) ";
        break;
    }

    return $from;
  }

  /**
   * Ideally this function should include fields that are displayed in the selector.
   *
   * @param int $mode
   * @param bool $includeCustomFields
   *
   * @return array|null
   */
  public static function defaultReturnProperties(
    $mode,
    $includeCustomFields = TRUE
  ) {
    $properties = NULL;

    if ($mode & CRM_Civisubscription_Utils::MODE_SUBSCRIPTION) {
      $properties = array(
        'contact_type' => 1,
        'contact_sub_type' => 1,
        'sort_name' => 1,
        'display_name' => 1,
        'subscription_id' => 1,
        'subscription_amount' => 1,
        'subscription_total_paid' => 1,
        'subscription_create_date' => 1,
        'subscription_start_date' => 1,
        'subscription_next_pay_date' => 1,
        'subscription_next_pay_amount' => 1,
        'subscription_status' => 1,
        'subscription_status_id' => 1,
        'subscription_is_test' => 1,
        'subscription_contribution_page_id' => 1,
        'subscription_financial_type' => 1,
        'subscription_frequency_interval' => 1,
        'subscription_frequency_unit' => 1,
        'subscription_currency' => 1,
        'subscription_campaign_id' => 1,
      );
    }
    return $properties;
  }

  /**
   * This includes any extra fields that might need for export etc.
   *
   * @param string $mode
   *
   * @return array|null
   */
  public static function extraReturnProperties($mode) {
    $properties = NULL;

    if ($mode & CRM_Contact_BAO_Query::MODE_SUBSCRIPTION) {
      $properties = array(
        'subscription_balance_amount' => 1,
        'subscription_payment_id' => 1,
        'subscription_payment_scheduled_amount' => 1,
        'subscription_payment_scheduled_date' => 1,
        'subscription_payment_paid_amount' => 1,
        'subscription_payment_paid_date' => 1,
        'subscription_payment_reminder_date' => 1,
        'subscription_payment_reminder_count' => 1,
        'subscription_payment_status_id' => 1,
        'subscription_payment_status' => 1,
      );

      // also get all the custom subscription properties
      $fields = CRM_Core_BAO_CustomField::getFieldsForImport('Subscription');
      if (!empty($fields)) {
        foreach ($fields as $name => $dontCare) {
          $properties[$name] = 1;
        }
      }
    }
    return $properties;
  }

  /**
   * @param CRM_Core_Form $form
   */
  public static function buildSearchForm(&$form) {
    // subscription related dates
    CRM_Core_Form_Date::buildDateRange($form, 'subscription_start_date', 1, '_low', '_high', ts('From'), FALSE);
    CRM_Core_Form_Date::buildDateRange($form, 'subscription_end_date', 1, '_low', '_high', ts('From'), FALSE);
    CRM_Core_Form_Date::buildDateRange($form, 'subscription_create_date', 1, '_low', '_high', ts('From'), FALSE);

    // subscription payment related dates
    CRM_Core_Form_Date::buildDateRange($form, 'subscription_payment_date', 1, '_low', '_high', ts('From'), FALSE);

    $form->addYesNo('subscription_test', ts('subscription is a Test?'), TRUE);
    $form->add('text', 'subscription_amount_low', ts('From'), array('size' => 8, 'maxlength' => 8));
    $form->addRule('subscription_amount_low', ts('Please enter a valid money value (e.g. %1).', array(1 => CRM_Utils_Money::format('9.99', ' '))), 'money');

    $form->add('text', 'subscription_amount_high', ts('To'), array('size' => 8, 'maxlength' => 8));
    $form->addRule('subscription_amount_high', ts('Please enter a valid money value (e.g. %1).', array(1 => CRM_Utils_Money::format('99.99', ' '))), 'money');

    $form->add('select', 'subscription_status_id',
      ts('subscription Status'), CRM_subscription_BAO_subscription::buildOptions('status_id'),
      FALSE, array('class' => 'crm-select2', 'multiple' => 'multiple')
    );

    $form->addYesNo('subscription_acknowledge_date_is_not_null', ts('Acknowledgement sent?'), TRUE);

    $form->add('text', 'subscription_installments_low', ts('From'), array('size' => 8, 'maxlength' => 8));
    $form->addRule('subscription_installments_low', ts('Please enter a number'), 'integer');

    $form->add('text', 'subscription_installments_high', ts('To'), array('size' => 8, 'maxlength' => 8));
    $form->addRule('subscription_installments_high', ts('Please enter number.'), 'integer');

    $form->add('select', 'subscription_payment_status_id',
      ts('subscription Payment Status'), CRM_Subscription_BAO_SubscriptionPayment::buildOptions('status_id'),
      FALSE, array('class' => 'crm-select2', 'multiple' => 'multiple')
    );

    $form->add('select', 'subscription_financial_type_id',
      ts('Financial Type'),
      array('' => ts('- select -')) + CRM_Contribute_PseudoConstant::financialType(),
      FALSE, array('class' => 'crm-select2')
    );

    $form->add('select', 'subscription_contribution_page_id',
      ts('Contribution Page'),
      array('' => ts('- any -')) + CRM_Contribute_PseudoConstant::contributionPage(),
      FALSE, array('class' => 'crm-select2')
    );

    // add fields for subscription frequency
    $form->add('text', 'subscription_frequency_interval', ts('Every'), array('size' => 8, 'maxlength' => 8));
    $form->addRule('subscription_frequency_interval', ts('Please enter valid Subscription Frequency Interval'), 'integer');
    $frequencies = CRM_Core_OptionGroup::values('recur_frequency_units');
    foreach ($frequencies as $val => $label) {
      $freqUnitsDisplay["'{$val}'"] = ts('%1(s)', array(1 => $label));
    }

    $form->add('select', 'subscription_frequency_unit',
      ts('Subscription Frequency'),
      array('' => ts('- any -')) + $freqUnitsDisplay
    );

    self::addCustomFormFields($form, array('Subscription'));

    CRM_Campaign_BAO_Campaign::addCampaignInComponentSearch($form, 'subscription_campaign_id');

    $form->assign('validCiviSubscription', TRUE);
    $form->setDefaults(array('subscription_test' => 0));
  }

  /**
   * @param $tables
   */
  public static function tableNames(&$tables) {
    // add status table
    if (!empty($tables['subscription_status']) || !empty($tables['civicrm_subscription_payment'])) {
      $tables = array_merge(array('civicrm_subscription' => 1), $tables);
    }
  }

}
