<?php

$iLemmaId = $_REQUEST['iLemmaId'];
$iUserId = $_REQUEST['iUserId'];
$sNewWord = urldecode($_REQUEST['sNewWord']);
$iTypeId = (isset($_REQUEST['iTypeId'])) ? $_REQUEST['iTypeId'] : false;
$sIdOfLatestAttestedWord = $_REQUEST['sIdOfLatestAttestedWord'];

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

autoAttest($iLemmaId, $iUserId, $sNewWord, $iTypeId, $_REQUEST['bDubious'],
	   $_REQUEST['bElliptical'], $_REQUEST['bErroneous'], $sIdOfLatestAttestedWord);

// If you update something, the entire lemma belongs to you
reviseLemma($iLemmaId, $_REQUEST['iUserId']);

echo mysql_error();