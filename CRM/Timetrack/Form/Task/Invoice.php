<?php

/**
 * This class provides the functionality to invoice punches.
 */
class CRM_Timetrack_Form_Task_Invoice extends CRM_Contact_Form_Task {
  protected $defaults;
  protected $punchIds;

  /**
   * build all the data structures needed to build the form
   *
   * @return void
   * @access public
   */
  function preProcess() {
    // CRM_Contact_Form_Task_EmailCommon::preProcessFromAddress($this);

    parent::preProcess();
  }

  function setDefaultValues() {
    return $this->defaults;
  }

  /**
   * Build the form
   *
   * @access public
   *
   * @return void
   */
  public function buildQuickForm() {
    $this->defaults = array();
    $smarty = CRM_Core_Smarty::singleton();

    $case_id = $this->getCaseID();
    $client_id = CRM_Timetrack_Utils::getCaseContact($case_id);
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $client_id));
    $period_start = $this->getPeriodStart();
    $period_end = $this->getPeriodEnd();

    CRM_Utils_System::setTitle(ts('New invoice for %1', array(1 => $contact['display_name'])));

    $this->addElement('text', 'client_name', ts('Client'))->freeze();
    $this->defaults['client_name'] = $contact['display_name'];

    $this->addElement('text', 'invoice_title', ts('Invoice title'));
    $this->defaults['invoice_title'] = $contact['display_name'] . ' ' . substr($period_end, 0, 10);

    $this->addElement('text', 'invoice_period_start', ts('From'));
    $this->defaults['invoice_period_start'] = $period_start;

    $this->addElement('text', 'invoice_period_end', ts('To'));
    $this->defaults['invoice_period_end'] = $period_end;

    $this->addDate('invoice_date', ts('Invoice date'), TRUE);
    $this->defaults['invoice_date'] = date('m/d/Y');

    $this->add('text', 'ledger_order_id', ts('Ledger order ID'), 'size="7"', FALSE);
    $this->defaults['ledger_order_id'] = '';

    $this->add('text', 'ledger_bill_id', ts('Ledger invoice ID'), 'size="7"', TRUE);
    $this->defaults['ledger_invoice_id'] = '';

    $tasks = $this->getBillingPerTasks();

    foreach ($tasks as $key => $val) {
      $this->addElement('text', 'task_' . $key . '_label');
      $this->addElement('text', 'task_' . $key . '_hours')->freeze();
      $this->addElement('text', 'task_' . $key . '_hours_billed');
      $this->addElement('text', 'task_' . $key . '_unit');
      $this->addElement('text', 'task_' . $key . '_cost');
      $this->addElement('text', 'task_' . $key . '_amount');

      $this->defaults['task_' . $key . '_label'] = $val['title'];
      $this->defaults['task_' . $key . '_hours'] = $this->getTotalHours($val['punches'], 'duration');
      $this->defaults['task_' . $key . '_hours_billed'] = $this->getTotalHours($val['punches'], 'duration_rounded');
      $this->defaults['task_' . $key . '_unit'] = ts('hour'); // FIXME
      $this->defaults['task_' . $key . '_cost'] = 85; // FIXME

      // This gets recalculated in JS on page load / change.
      $this->defaults['task_' . $key . '_amount'] = $this->defaults['task_' . $key . '_hours_billed'] * $this->defaults['task_' . $key . '_cost'];
    }

    for ($key = 0; $key < 5; $key++) {
      $this->addElement('text', 'task_extra' . $key . '_label');
      $this->addElement('text', 'task_extra' . $key . '_hours_billed');
      $this->addElement('text', 'task_extra' . $key . '_unit');
      $this->addElement('text', 'task_extra' . $key . '_cost');
      $this->addElement('text', 'task_extra' . $key . '_amount');

      $tasks['extra' . $key] = array(
        'title' => '',
        'punches' => array(),
      );
    }

    $this->addDefaultButtons(ts('Save'));

    $smarty->assign('invoice_tasks', $tasks);
  }

  /**
   * process the form after the input has been submitted and validated
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    $case_id = $this->getCaseID();
    $params = $this->exportValues();

    $line_items = array();
    $total_hours_billed = 0;

    $tasks = $this->getBillingPerTasks();

    foreach ($tasks as $key => $val) {
      $total_hours_billed += $params['task_' . $key . '_hours_billed'];
    }

    for ($key = 0; $key < 5; $key++) {
      $total_hours_billed += $params['task_extra' . $key . '_hours_billed'];
    }

    // NB: created_date can't be set manually becase it is a timestamp
    // and the DB layer explicitely ignores timestamps (there is a trigger
    // defined in timetrack.php).
    $result = civicrm_api3('Timetrackinvoice', 'create', array(
      'case_id' => $case_id,
      'title' => $params['invoice_title'],
      'state' => 3, // FIXME, expose to UI, pseudoconstant, etc.
      'ledger_order_id' => $params['ledger_order_id'],
      'ledger_bill_id' => $params['ledger_bill_id'],
      'hours_billed' => $total_hours_billed,
    ));

    $order_id = $result['id'];

    foreach ($tasks as $key => $val) {
      $result = civicrm_api3('Timetrackinvoicelineitem', 'create', array(
        'order_id' => $order_id,
        'title' => $params['task_' . $key . '_title'],
        'hours_billed' => $params['task_' . $key . '_hours_billed'],
        'cost' => $params['task_' . $key . '_cost'],
        'unit' => $params['task_' . $key . '_unit'],
      ));

      $line_item_id = $result['id'];

      // Assign punches to line item / order.
      foreach ($val['punches'] as $pkey => $pval) {
        CRM_Core_DAO::executeQuery('UPDATE kpunch SET korder_id = %1, korder_line_id = %2 WHERE pid = %3', array(
          1 => array($order_id, 'Positive'),
          2 => array($line_item_id, 'Positive'),
          3 => array($pval['pid'], 'Positive'),
        ));
      }
    }

    for ($key = 0; $key < 5; $key++) {
      // FIXME: not sure what to consider sufficient to charge an 'extra' line.
      if ($params['task_extra' . $key . '_cost']) {
        $result = civicrm_api3('Timetrackinvoicelineitem', 'create', array(
          'order_id' => $order_id,
          'title' => $params['task_extra' . $key . '_label'],
          'hours_billed' => $params['task_extra' . $key . '_hours_billed'],
          'cost' => $params['task_extra' . $key . '_cost'],
          'unit' => $params['task_extra' . $key . '_unit'],
        ));
      }
    }

    CRM_Core_Session::setStatus(ts('The order #%1 has been created.', array(1 => $order_id)), '', 'success');
  }

  /**
   * Assuming the punches are all linked to a same case, we find the client name
   * from a random punch.
   */
  function getCaseID() {
    $pid = $this->_contactIds[0];

    $sql = "SELECT civicrm_case.id as case_id
            FROM kpunch
            LEFT JOIN ktask kt ON (kt.nid = kpunch.nid)
            LEFT JOIN node as task_civireport ON (task_civireport.nid = kt.nid)
            LEFT JOIN kcontract ON (kcontract.nid = kt.parent)
            LEFT JOIN korder as invoice_civireport ON (invoice_civireport.nid = kpunch.order_reference)
            LEFT JOIN civicrm_value_infos_base_contrats_1 as cval ON (cval.kproject_node_2 = kt.parent)
            LEFT JOIN civicrm_case ON (civicrm_case.id = cval.entity_id)
            WHERE kpunch.pid = %1";

    return CRM_Core_DAO::singleValueQuery($sql, array(
      1 => array($pid, 'Positive'),
    ));
  }

  function getPeriodStart() {
    $ids = $this->getPunchIds();
    return CRM_Core_DAO::singleValueQuery("SELECT FROM_UNIXTIME(MIN(begin)) as begin FROM kpunch WHERE pid IN (" . implode(',', $ids) . ")");
  }

  function getPeriodEnd() {
    $ids = $this->getPunchIds();
    return CRM_Core_DAO::singleValueQuery("SELECT FROM_UNIXTIME(MAX(begin)) as begin FROM kpunch WHERE pid IN (" . implode(',', $ids) . ")");
  }

  function getBillingPerTasks() {
    $tasks = array();

    $ids = $this->getPunchIds();
    $dao = CRM_Core_DAO::executeQuery("SELECT n.nid, n.title, p.pid, p.begin, p.duration, p.comment FROM kpunch p LEFT JOIN node n ON (n.nid = p.nid) WHERE pid IN (" . implode(',', $ids) . ")");

    while ($dao->fetch()) {
      if (! isset($tasks[$dao->nid])) {
        $tasks[$dao->nid] = array(
          'title' => $dao->title,
          'punches' => array(),
        );
      }

      $tasks[$dao->nid]['punches'][] = array(
        'pid' => $dao->pid,
        'begin' => $dao->begin,
        'duration' => CRM_Timetrack_Utils::roundUpSeconds($dao->duration, 1),
        'duration_rounded' => CRM_Timetrack_Utils::roundUpSeconds($dao->duration),
        'comment' => $dao->comment,
      );
    }

    return $tasks;
  }

  function getPunchIds() {
    if (isset($this->punchIds)) {
      return $this->punchIds;
    }

    $this->punchIds = array();

    foreach ($this->_contactIds as $cid) {
      $this->punchIds[] = intval($cid);
    }

    return $this->punchIds;
  }

  function getTotalHours($punches, $field = 'duration') {
    $total = 0;

    foreach ($punches as $p) {
      $total += $p[$field];
    }

    return $total;
  }
}
