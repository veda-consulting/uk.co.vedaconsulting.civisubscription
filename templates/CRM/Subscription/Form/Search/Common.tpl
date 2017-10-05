{*
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
*}

<tr>
  <td><label>{ts}Payment Scheduled{/ts}</label></td>
</tr>
<tr>
{include file="CRM/Core/DateRange.tpl" fieldName="subscription_payment_date" from='_low' to='_high'}
</tr>
<tr>
  <td colspan="2">
    <label>{ts}Subscription Payment Status{/ts}</label>
      <br />{$form.subscription_payment_status_id.html}
  </td>
</tr>
<tr>
  <td>
    <label>{ts}Subscription Amounts{/ts}</label>
      <br />
    {$form.subscription_amount_low.label} {$form.subscription_amount_low.html} &nbsp;&nbsp; {$form.subscription_amount_high.label} {$form.subscription_amount_high.html}
  </td>
  <td>
    <label>{ts}Subscription Status{/ts}</label>
      <br />{$form.subscription_status_id.html}
  </td>
</tr>
<tr>
  <td><label>{ts}Subscription Made{/ts}</label></td>
</tr>
<tr>
{include file="CRM/Core/DateRange.tpl" fieldName="subscription_create_date" from='_low' to='_high'}
</tr>
<tr>
  <td><label>{ts}Payments Start Date{/ts}</label></td>
</tr>
<tr>
{include file="CRM/Core/DateRange.tpl" fieldName="subscription_start_date" from='_low' to='_high'}
</tr>
<tr>
  <td><label>{ts}Payments Ended Date{/ts}</label></td>
</tr>
<tr>
{include file="CRM/Core/DateRange.tpl" fieldName="subscription_end_date" from='_low' to='_high'}
</tr>
<tr>
  <td>
    <label>{ts}Financial Type{/ts}</label>
    <br />{$form.subscription_financial_type_id.html}
  </td>
  <td>
    <label>{ts}Contribution Page{/ts}</label>
    <br />{$form.subscription_contribution_page_id.html}
  </td>
</tr>
<tr>
  <td>
  <br />
  {$form.subscription_test.label} {help id="is-test" file="CRM/Contact/Form/Search/Advanced"} &nbsp; {$form.subscription_test.html}
  </td>
</tr>
<tr>
  <td colspan="2">
  {$form.subscription_frequency_unit.label}
    <br /> {$form.subscription_frequency_interval.label} &nbsp; {$form.subscription_frequency_interval.html} &nbsp;
  {$form.subscription_frequency_unit.html}
  </td>
</tr>
<tr>
  <td colspan="2">
    {ts}Number of Installments{/ts}
    {$form.subscription_installments_low.label} {$form.subscription_installments_low.html}
    &nbsp;&nbsp; {$form.subscription_installments_high.label} {$form.subscription_installments_high.html}
  </td>
</tr>

<tr>
  <td colspan="2">
    <br /> {$form.subscription_acknowledge_date_is_not_null.label} &nbsp; {$form.subscription_acknowledge_date_is_not_null.html}
    &nbsp;
  </td>
</tr>

{* campaign in subscription search *}
{include file="CRM/Campaign/Form/addCampaignToComponent.tpl"
campaignContext="componentSearch" campaignTrClass='' campaignTdClass=''}

{if $subscriptionGroupTree}
<tr>
  <td colspan="2">
  {include file="CRM/Custom/Form/Search.tpl" groupTree=$subscriptionGroupTree showHideLinks=false}
  </td>
</tr>
{/if}
