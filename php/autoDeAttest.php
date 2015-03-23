<?php

$iLemmaId = $_REQUEST['iLemmaId'];
$iUserId = $_REQUEST['iUserId'];
$sWordToBeDeAttested = urldecode($_REQUEST['sWordToBeDeAttested']);
$iQuotationId = $_REQUEST['iQuotationId'];
$iOnset= $_REQUEST['iOnset'];

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

autoDeAttest($iLemmaId, $sWordToBeDeAttested);

// If you update something, the entire lemma belongs to you
reviseLemma($iLemmaId, $_REQUEST['iUserId']);

echo mysql_error();