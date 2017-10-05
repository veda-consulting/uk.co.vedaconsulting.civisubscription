<?php

require_once 'CRM/Core/Page.php';

class CRM_Civisubscription_Utils {

  const MODE_SUBSCRIPTION = 32768;

  public static function updateSubscriptionPayment($subscriptionId, $contributionId) {

  	if (empty($subscriptionId) || empty($contributionId)) {
  		return;
  	}

  	// Get contribution details
  	$contribParams = array(
  		'id' => $contributionId,
  		'sequential' => 1,
  	);
  	$contribResult = CRM_Offlinemembershipwizard_Utils::CiviCRMAPIWrapper('Contribution', 'get', $contribParams);
  	$contribDetails = $contribResult['values'][0];

  	$statusId = $contribDetails['contribution_status_id'];

  	// Get first subscription payment
  	$selectSql = "SELECT id FROM civicrm_subscription_payment WHERE subscription_id = %1  ORDER BY id LIMIT 1";
  	$selectParams[1] = array($subscriptionId, 'Integer');
  	$selectDao = CRM_Core_DAO::executeQuery($selectSql, $selectParams);
  	if ($selectDao->fetch()) {
  		$updateSql = "UPDATE civicrm_subscription_payment SET contribution_id = %1, status_id = %2 WHERE id = %3";
  		$updateParams = array(
  			1 => array($contributionId, 'Integer'),
  			2 => array($statusId, 'Integer'),
  			3 => array($selectDao->id, 'Integer'),
  		);
  		CRM_Core_DAO::executeQuery($updateSql, $updateParams);
  	}
  }

} // End of CRM_Civisubscription_Utils
