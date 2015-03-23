<?php

$iLemmaId = $_REQUEST['iLemmaId'];
$bNewMarked = $_REQUEST['bNewMarked'];

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

markLemma($iLemmaId, $bNewMarked);

// Make sure the entire lemma belongs to this user now
reviseLemma($iLemmaId, $_REQUEST['iUserId']);

// The error is the empty string if nothing goes wrong
echo mysql_error();

?>