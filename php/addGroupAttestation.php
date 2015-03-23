<?php

$iGroupOnset = $_REQUEST['iGroupOnset'];
$iGroupQuotationId = $_REQUEST['iGroupQuotationId'];
$iGroupPos = $_REQUEST['iGroupPos'];
$sClassName = $_REQUEST['sClassName'];
$iLemmaId = $_REQUEST['iLemmaId'];
$iQuotationId = $_REQUEST['iQuotationId'];
$iOnset = $_REQUEST['iOnset'];
$iOffset = $_REQUEST['iOffset'];
$iPos = $_REQUEST['iPos'];
$sWordForm = str_replace("'", '\\\'', $_REQUEST['sWordForm']);
$iTypeId = isset($_REQUEST["iTypeId"]) ? $_REQUEST["iTypeId"] : false;
$sAttestationComment = isset($_REQUEST["sAttestationComment"]) ? $_REQUEST["sAttestationComment"] : "";

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

// This file is only called when there was an attestation alt-clicked already.
// When the current word form was not an attestation yet, we attest it here.
// NOTE that this goes both for the multi-type case (where the word form will
// be attestested with the same type the first alt-clicked word form had), as
// for the normal case.
$bWasAttestedAlready = 1;
if( $sClassName == 'lowlighted') {
  addAttestation($iLemmaId, $iQuotationId, $iOnset, $iOffset, $sWordForm,
		 $iTypeId, $sAttestationComment);
  $bWasAttestedAlready = 0;
}

addGroupAttestation($iGroupOnset, $iGroupPos, $iGroupQuotationId, $iOnset,
		    $iPos, $bWasAttestedAlready);

// Make sure the entire lemma belongs to this user now
reviseLemma($iLemmaId, $_REQUEST['iUserId']);

// The error is the empty string if nothing goes wrong
echo mysql_error();

?>