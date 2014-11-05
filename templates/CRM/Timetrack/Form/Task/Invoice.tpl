<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.client_name.label}</div>
  <div class="content">{$form.client_name.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.invoice_title.label}</div>
  <div class="content">{$form.invoice_title.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.invoice_period_start.label}</div>
  <div class="content">{$form.invoice_period_start.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.invoice_period_end.label}</div>
  <div class="content">{$form.invoice_period_end.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.invoice_date.label}</div>
  <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=invoice_date}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.total_punches.label}</div>
  <div class="content">{$form.total_punches.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.ledger_order_id.label}</div>
  <div class="content">{$form.ledger_order_id.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section crm-timetrack-general-section">
  <div class="label">{$form.ledger_bill_id.label}</div>
  <div class="content">{$form.ledger_bill_id.html}</div>
  <div class="clear"></div>
</div>

<table>
  <thead>
    <tr>
      <th>{ts}Task{/ts}</th>
      <th>{ts}Hours{/ts}</th>
      <th>{ts}Rounded{/ts}</th>
      <th>{ts}Unit{/ts}</th>
      <th>{ts}Cost{/ts}</th>
      <th>{ts}Amount{/ts}</th>
    </tr>
  </thead>
  <tbody>
    {foreach item=task key=foo from=$invoice_tasks}
      {assign var="label" value='task_'|cat:$foo|cat:'_label'}
      {assign var="hours" value='task_'|cat:$foo|cat:'_hours'}
      {assign var="hoursbilled" value='task_'|cat:$foo|cat:'_hours_billed'}
      {assign var="unit" value='task_'|cat:$foo|cat:'_unit'}
      {assign var="cost" value='task_'|cat:$foo|cat:'_cost'}
      {assign var="amount" value='task_'|cat:$foo|cat:'_amount'}
      <tr>
        <td>{$form.$label.html}</td>
        <td>{$form.$hours.html}</td>
        <td>{$form.$hoursbilled.html}</td>
        <td>{$form.$unit.html}</td>
        <td>{$form.$cost.html}</td>
        <td>{$form.$amount.html}</td>
      </tr>
    {/foreach}
  </tbody>
</table>

<div class="crm-buttons">
  {$form.buttons.html}
</div>
