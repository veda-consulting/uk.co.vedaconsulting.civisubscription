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
{if $context eq 'user'}
<div class="view-content">
{if $subscription_rows}
{strip}
<table class="selector">
  <tr class="columnheader">
  {foreach from=$subscription_columnHeaders item=header}
    <th>{$header.name}</th>
  {/foreach}
  </tr>
  {counter start=0 skip=1 print=false}
  {foreach from=$subscription_rows item=row}
  <tr id='rowid{$row.subscription_id}' class="{cycle values="odd-row,even-row"} {if $row.subscription_status_name eq 'Overdue' } disabled{/if} crm-subscription crm-subscription_{$row.subscription_id} ">
    <td class="crm-subscription-subscription_amount">{$row.subscription_amount|crmMoney:$row.subscription_currency}</td>
    <td class="crm-subscription-subscription_total_paid">{$row.subscription_total_paid|crmMoney:$row.subscription_currency}</td>
    <td class="crm-subscription-subscription_amount">{$row.subscription_amount-$row.subscription_total_paid|crmMoney:$row.subscription_currency}</td>
    <td class="crm-subscription-subscription_contribution_type">{$row.subscription_financial_type}</td>
    <td class="crm-subscription-subscription_create_date">{$row.subscription_create_date|truncate:10:''|crmDate}</td>
    <td class="crm-subscription-subscription_next_pay_date">{$row.subscription_next_pay_date|truncate:10:''|crmDate}</td>
    <td class="crm-subscription-subscription_next_pay_amount">{$row.subscription_next_pay_amount|crmMoney:$row.subscription_currency}</td>
    <td class="crm-subscription-subscription_status crm-subscription-subscription_status_{$row.subscription_status}">{$row.subscription_status}</td>
    <td>
      {if $row.subscription_contribution_page_id and ($row.subscription_status_name neq 'Completed') and ( $row.contact_id eq $loggedUserID ) }
        <a href="{crmURL p='civicrm/contribute/transact' q="reset=1&id=`$row.subscription_contribution_page_id`&subscriptionId=`$row.subscription_id`"}">{ts}Make Payment{/ts}</a><br/>
      {/if}
      <a class="crm-expand-row" title="{ts}view payments{/ts}" href="{crmURL p='civicrm/subscription/payment' q="action=browse&context=`$context`&subscriptionId=`$row.subscription_id`&cid=`$row.contact_id`"}">{ts}Payments{/ts}</a>
    </td>
   </tr>
  {/foreach}
</table>
{/strip}
{crmScript file='js/crm.expandRow.js'}
{else}
<div class="messages status no-popup">
         <div class="icon inform-icon"></div>
             {ts}There are no Subscriptions for your record.{/ts}
         </div>
{/if}
{*subscription row if*}

{*Display honor block*}
{if $subscriptionHonor && $subscriptionHonorRows}
{strip}
<div class="help">
    <p>{ts}Subscriptions made in your honor.{/ts}</p>
</div>
  <table class="selector">
    <tr class="columnheader">
        <th>{ts}Subscriptionr{/ts}</th>
        <th>{ts}Amount{/ts}</th>
        <th>{ts}Type{/ts}</th>
        <th>{ts}Financial Type{/ts}</th>
        <th>{ts}Create date{/ts}</th>
        <th>{ts}Acknowledgment Sent{/ts}</th>
   <th>{ts}Acknowledgment Date{/ts}</th>
        <th>{ts}Status{/ts}</th>
        <th></th>
    </tr>
  {foreach from=$subscriptionHonorRows item=row}
     <tr id='rowid{$row.honorId}' class="{cycle values="odd-row,even-row"}">
     <td class="crm-subscription-display_name"><a href="{crmURL p="civicrm/contact/view" q="reset=1&cid=`$row.honorId`"}" id="view_contact">{$row.display_name}</a></td>
     <td class="crm-subscription-amount">{$row.amount|crmMoney:$row.subscription_currency}</td>
     <td class="crm-subscription-honor-type">{$row.honor_type}</td>
           <td class="crm-subscription-type">{$row.type}</td>
           <td class="crm-subscription-create_date">{$row.create_date|truncate:10:''|crmDate}</td>
           <td align="center">{if $row.acknowledge_date}{ts}Yes{/ts}{else}{ts}No{/ts}{/if}</td>
           <td class="crm-subscription-acknowledge_date">{$row.acknowledge_date|truncate:10:''|crmDate}</td>
           <td class="crm-subscription-status">{$row.status}</td>
    </tr>
        {/foreach}
</table>
{/strip}
{/if}
</div>
{* main if close*}
{/if}
