DELETE FROM civicrm_component WHERE name = 'CiviSubscription' AND namespace = 'CRM_Subscription';

SELECT @option_group_id_cvOpt := max(id) FROM civicrm_option_group WHERE name = 'contact_view_options';

DELETE FROM civicrm_option_value WHERE option_group_id = @option_group_id_cvOpt AND name = 'CiviSubscription';

DROP TABLE IF EXISTS civicrm_subscription;
DROP TABLE IF EXISTS civicrm_subscription_block;
DROP TABLE IF EXISTS civicrm_subscription_payment;

DROP TABLE IF EXISTS civicrm_order;
DROP TABLE IF EXISTS civicrm_order_detail;