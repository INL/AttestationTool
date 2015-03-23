<?php

$iQuotationId = $_REQUEST['iQuotationId'];
$iOnset = $_REQUEST['iOnset'];
$iLemmaId = $_REQUEST['iLemmaId'];

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

deleteAttestation($iLemmaId, $iQuotationId, $iOnset);

// Make sure the entire lemma belongs to this user now
reviseLemma($iLemmaId, $_REQUEST['iUserId']);

// The error is the empty string if nothing goes wrong
echo mysql_error();

?>