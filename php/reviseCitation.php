<?php

// This script is used to mark ciataions as good/bad

$iQuotationId = $_REQUEST['iQuotationId'];
$iNewSpecialAttention = $_REQUEST['iSpecialAttention'];
$iUserId = $_REQUEST['iUserId'];
$iLemmaId = $_REQUEST['iLemmaId'];

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

$sQuery = "UPDATE quotations SET specialAttention = $iNewSpecialAttention " .
"WHERE id = $iQuotationId";

$oResult = mysql_query ($sQuery, $GLOBALS['db']);

echo mysql_error();

// Make sure the entire lemma belongs to this user
reviseLemma($iLemmaId, $iUserId);

// De error is de lege string als niks mis is
echo mysql_error();

?>