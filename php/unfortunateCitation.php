<?php

// This script is used to mark ciataions as good/bad

$iQuotationId = $_REQUEST['iQuotationId'];
$iNewUnfortunate = $_REQUEST['iUnfortunate'];
$iUserId = $_REQUEST['iUserId'];
$iLemmaId =  $_REQUEST['iLemmaId'];

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

$sQuery = "UPDATE quotations SET unfortunate = $iNewUnfortunate " .
"WHERE id = $iQuotationId";

$oResult = mysql_query ($sQuery, $GLOBALS['db']);

echo mysql_error();

// Make sure the entire lemma belongs to this user
reviseLemma($iLemmaId, $iUserId);

// The error is an empty string if nothing goes wrong
echo mysql_error();

?>