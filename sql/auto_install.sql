INSERT INTO civicrm_component (name, namespace) VALUES ('CiviSubscription', 'CRM_Subscription');

-- CRM-16901 Recurring contribution tab in display preference
SELECT @option_group_id_cvOpt := max(id) FROM civicrm_option_group WHERE name = 'contact_view_options';
SELECT @max_val := MAX(ROUND(op.value)) FROM civicrm_option_value op  WHERE op.option_group_id  = @option_group_id_cvOpt;
SELECT @max_wt := ROUND(val.weight) FROM civicrm_option_value val WHERE val.option_group_id = @option_group_id_cvOpt;

INSERT INTO
   `civicrm_option_value` (`option_group_id`, `label`, `value`, `name`, `grouping`, `filter`, `is_default`, `weight`, `is_optgroup`, `is_reserved`, `is_active`, `component_id`, `visibility_id`)
VALUES
  (@option_group_id_cvOpt, 'Subscriptions', @max_val+1, 'CiviSubscription', NULL, 0, NULL,  @max_wt+1, 0, 0, 1, NULL, NULL);

  --
-- Table structure for table `civicrm_subscription`
--

CREATE TABLE `civicrm_subscription` (
  `id` int(10) unsigned NOT NULL COMMENT 'subscription ID',
  `contact_id` int(10) unsigned NOT NULL COMMENT 'Foreign key to civicrm_contact.id .',
  `financial_type_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to Financial Type',
  `contribution_page_id` int(10) unsigned DEFAULT NULL COMMENT 'The Contribution Page which triggered this contribution',
  `amount` decimal(20,2) NOT NULL COMMENT 'Total subscriptiond amount.',
  `original_installment_amount` decimal(20,2) NOT NULL COMMENT 'Original amount for each of the installments.',
  `currency` varchar(3) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '3 character string, value from config setting or input via user.',
  `frequency_unit` varchar(8) COLLATE utf8_unicode_ci DEFAULT 'month' COMMENT 'Time units for recurrence of subscription payments.',
  `frequency_interval` int(10) unsigned NOT NULL DEFAULT '1' COMMENT 'Number of time units for recurrence of subscription payments.',
  `frequency_day` int(10) unsigned NOT NULL DEFAULT '3' COMMENT 'Day in the period when the subscription payment is due e.g. 1st of month, 15th etc. Use this to set the scheduled dates for subscription payments.',
  `installments` int(10) unsigned DEFAULT '1' COMMENT 'Total number of payments to be made.',
  `start_date` datetime NOT NULL COMMENT 'The date the first scheduled subscription occurs.',
  `create_date` datetime NOT NULL COMMENT 'When this subscription record was created.',
  `acknowledge_date` datetime DEFAULT NULL COMMENT 'When a subscription acknowledgement message was sent to the contributor.',
  `modified_date` datetime DEFAULT NULL COMMENT 'Last updated date for this subscription record.',
  `cancel_date` datetime DEFAULT NULL COMMENT 'Date this subscription was cancelled by contributor.',
  `end_date` datetime DEFAULT NULL COMMENT 'Date this subscription finished successfully (total subscription payments equal to or greater than subscriptiond amount).',
  `max_reminders` int(10) unsigned DEFAULT '1' COMMENT 'The maximum number of payment reminders to send for any given payment.',
  `initial_reminder_day` int(10) unsigned DEFAULT '5' COMMENT 'Send initial reminder this many days prior to the payment due date.',
  `additional_reminder_day` int(10) unsigned DEFAULT '5' COMMENT 'Send additional reminder this many days after last one sent, up to maximum number of reminders.',
  `status_id` int(10) unsigned DEFAULT NULL COMMENT 'Implicit foreign key to civicrm_option_values in the contribution_status option group.',
  `is_test` tinyint(4) DEFAULT '0',
  `campaign_id` int(10) unsigned DEFAULT NULL COMMENT 'The campaign for which this subscription has been initiated.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `civicrm_subscription_block`
--

CREATE TABLE `civicrm_subscription_block` (
  `id` int(10) unsigned NOT NULL COMMENT 'subscription ID',
  `entity_table` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'physical tablename for entity being joined to subscription, e.g. civicrm_contact',
  `entity_id` int(10) unsigned NOT NULL COMMENT 'FK to entity table specified in entity_table column.',
  `subscription_frequency_unit` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Delimited list of supported frequency units',
  `is_subscription_interval` tinyint(4) DEFAULT '0' COMMENT 'Is frequency interval exposed on the contribution form.',
  `max_reminders` int(10) unsigned DEFAULT '1' COMMENT 'The maximum number of payment reminders to send for any given payment.',
  `initial_reminder_day` int(10) unsigned DEFAULT '5' COMMENT 'Send initial reminder this many days prior to the payment due date.',
  `additional_reminder_day` int(10) unsigned DEFAULT '5' COMMENT 'Send additional reminder this many days after last one sent, up to maximum number of reminders.',
  `subscription_start_date` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'The date the first scheduled subscription occurs.',
  `is_subscription_start_date_visible` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'If true - recurring start date is shown.',
  `is_subscription_start_date_editable` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'If true - recurring start date is editable.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `civicrm_subscription_payment`
--

CREATE TABLE `civicrm_subscription_payment` (
  `id` int(10) unsigned NOT NULL,
  `subscription_id` int(10) unsigned NOT NULL COMMENT 'FK to subscription table',
  `contribution_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to contribution table.',
  `scheduled_amount` decimal(20,2) NOT NULL COMMENT 'subscriptiond amount for this payment (the actual contribution amount might be different).',
  `actual_amount` decimal(20,2) DEFAULT NULL COMMENT 'Actual amount that is paid as the subscriptiond installment amount.',
  `currency` varchar(3) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '3 character string, value from config setting or input via user.',
  `scheduled_date` datetime NOT NULL COMMENT 'The date the subscription payment is supposed to happen.',
  `reminder_date` datetime DEFAULT NULL COMMENT 'The date that the most recent payment reminder was sent.',
  `reminder_count` int(10) unsigned DEFAULT '0' COMMENT 'The number of payment reminders sent.',
  `status_id` int(10) unsigned DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `civicrm_subscription`
--
ALTER TABLE `civicrm_subscription`
  ADD PRIMARY KEY (`id`),
  ADD KEY `index_status` (`status_id`),
  ADD KEY `FK_civicrm_subscription_contact_id` (`contact_id`),
  ADD KEY `FK_civicrm_subscription_financial_type_id` (`financial_type_id`),
  ADD KEY `FK_civicrm_subscription_contribution_page_id` (`contribution_page_id`),
  ADD KEY `FK_civicrm_subscription_campaign_id` (`campaign_id`);

--
-- Indexes for table `civicrm_subscription_block`
--
ALTER TABLE `civicrm_subscription_block`
  ADD PRIMARY KEY (`id`),
  ADD KEY `index_entity` (`entity_table`,`entity_id`);

--
-- Indexes for table `civicrm_subscription_payment`
--
ALTER TABLE `civicrm_subscription_payment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `index_contribution_subscription` (`contribution_id`,`subscription_id`),
  ADD KEY `index_status` (`status_id`),
  ADD KEY `FK_civicrm_subscription_payment_subscription_id` (`subscription_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `civicrm_subscription`
--
ALTER TABLE `civicrm_subscription`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'subscription ID';
--
-- AUTO_INCREMENT for table `civicrm_subscription_block`
--
ALTER TABLE `civicrm_subscription_block`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'subscription ID';
--
-- AUTO_INCREMENT for table `civicrm_subscription_payment`
--
ALTER TABLE `civicrm_subscription_payment`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `civicrm_subscription`
--
ALTER TABLE `civicrm_subscription`
  ADD CONSTRAINT `FK_civicrm_subscription_campaign_id` FOREIGN KEY (`campaign_id`) REFERENCES `civicrm_campaign` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_civicrm_subscription_contact_id` FOREIGN KEY (`contact_id`) REFERENCES `civicrm_contact` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_civicrm_subscription_contribution_page_id` FOREIGN KEY (`contribution_page_id`) REFERENCES `civicrm_contribution_page` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_civicrm_subscription_financial_type_id` FOREIGN KEY (`financial_type_id`) REFERENCES `civicrm_financial_type` (`id`);

--
-- Constraints for table `civicrm_subscription_payment`
--
ALTER TABLE `civicrm_subscription_payment`
  ADD CONSTRAINT `FK_civicrm_subscription_payment_contribution_id` FOREIGN KEY (`contribution_id`) REFERENCES `civicrm_contribution` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_civicrm_subscription_payment_subscription_id` FOREIGN KEY (`subscription_id`) REFERENCES `civicrm_subscription` (`id`) ON DELETE CASCADE;

-- Create order table
CREATE TABLE IF NOT EXISTS `civicrm_order` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `subscription_id` int(11) DEFAULT NULL COMMENT 'CiviCRM Subscription ID',
  `contribution_recur_id` int(11) DEFAULT NULL COMMENT 'CiviCRM Contribution Recur ID',
  `amount` decimal(20,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Currency.',
  `create_date` datetime NULL COMMENT 'When this order record was created.',
  `modified_date` datetime NULL COMMENT 'When this order record was modified.',
  `cancel_date` datetime NULL COMMENT 'When this order record was cancelled.',
  `status_id` int(10) unsigned DEFAULT NULL COMMENT 'Implicit foreign key to civicrm_option_values in the contribution_status option group.',
  `is_test` tinyint(4) DEFAULT '0',
  `campaign_id` int(10) unsigned DEFAULT NULL COMMENT 'The campaign for which this order has been initiated.',
  PRIMARY KEY (`id`)
);

-- Create order details table
CREATE TABLE IF NOT EXISTS `civicrm_order_detail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique ID',
  `order_id` int(11) DEFAULT NULL COMMENT 'CiviCRM Order ID',
  `entity_id` int(11) DEFAULT NULL COMMENT 'Entity ID',
  `entity_table` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Entity Table',
  `financial_type_id` int(11) DEFAULT NULL COMMENT 'Financial Type ID',
  `amount` decimal(20,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
);
