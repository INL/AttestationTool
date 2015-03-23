<?php

$iLemmaId = $_REQUEST['iLemmaId'];
$iQuotationId = $_REQUEST['iQuotationId'];
$iNewOnset = $_REQUEST['iNewOnset'];
$iNewOffset = $_REQUEST['iNewOffset'];
$sNewWordForm = str_replace("'", '\\\'', $_REQUEST['sNewWordForm']);
$iTypeId = isset($_REQUEST["iTypeId"]) ? $_REQUEST["iTypeId"] : false;
$sAttestationComment = isset($_REQUEST["sAttestationComment"]) ? $_REQUEST["sAttestationComment"] : false;

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

// Make a new attestation
// NOTE that we do typed as well as non-typed attestations here depending on
// the way this file was called.
addAttestation($iLemmaId, $iQuotationId, $iNewOnset, $iNewOffset,
	       $sNewWordForm, $iTypeId, $sAttestationComment);

// Make sure the entire lemma belongs to this user now
reviseLemma($iLemmaId, $_REQUEST['iUserId']);

// The error is the empty string if nothing goes wrong
echo mysql_error();

?>