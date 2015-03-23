<?php

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

$iLemmaId = $_REQUEST['iLemmaId'];
$iQuotationId = $_REQUEST['iQuotationId'];
$iStartPos = $_REQUEST['iStartPos'];
$iEndPos = $_REQUEST['iEndPos'];
$sTokenTuples = str_replace("'", '\\\'', $_REQUEST['sTokenTuples']);
$iFirstAttestationType = $_REQUEST['iFirstAttestationType'];
$iDefaultType = $_REQUEST['iDefaultType'];
$iFirstOnset = $_REQUEST['iFirstOnset'];
$iFirstOffset = $_REQUEST['iFirstOffset'];
$sFirstWordForm = str_replace("'", '\\\'', $_REQUEST['sFirstWordForm']);
$bMultiType = isset($_REQUEST["bMultiType"]) ? $_REQUEST["bMultiType"] : false;

makeGroupAttestation($iLemmaId, $iQuotationId, $iStartPos, $iEndPos,
		     $sTokenTuples, $iFirstAttestationType, $iDefaultType,
		     $iFirstOnset, $iFirstOffset, $sFirstWordForm,
		     $bMultiType);

// Make sure the entire lemma belongs to this user now
reviseLemma($iLemmaId, $_REQUEST['iUserId']);

// The error is the empty string if nothing goes wrong
echo mysql_error();

?>