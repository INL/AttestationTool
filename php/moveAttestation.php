<?php

$iQuotationId = $_REQUEST['iQuotationId'];
$iOldOnset = $_REQUEST['iOldOnset'];
$iNewOnset = $_REQUEST['iNewOnset'];
$iNewOffset = $_REQUEST['iNewOffset'];
$iNewPos = $_REQUEST['iNewPos'];
$sNewWordForm = str_replace("'", '\\\'', $_REQUEST['sNewWordForm']);
$iLemmaId = $_REQUEST['iLemmaId'];

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

// First move the attestation in the group (if it is a member of one)
$sQuery = "UPDATE groupAttestations,attestations " .
"SET groupAttestations.pos = $iNewPos " .
"WHERE groupAttestations.attestationId = attestations.id" .
"  AND attestations.quotationId = $iQuotationId" .
"  AND onset = $iOldOnset";

$oResult = mysql_query($sQuery, $GLOBALS['db']);
echo mysql_error();

// Then move the attestation itself.
// NOTE that we unmark any errors or dubiosities.
$sQuery = "UPDATE attestations SET onset = $iNewOnset, offset = $iNewOffset, ".
  "error = 0, dubious = 0, elliptical = 0, wordForm = '" . $sNewWordForm .
  "' WHERE quotationId = $iQuotationId AND onset = $iOldOnset";

$oResult = mysql_query($sQuery, $GLOBALS['db']);
echo mysql_error();

// If you update something, the entire lemma belongs to you
reviseLemma($iLemmaId, $_REQUEST['iUserId']);

// The error is the empty string if nothing goes wrong
echo mysql_error();

?>