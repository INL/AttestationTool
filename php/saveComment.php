<?php

$iLemmaId = $_REQUEST['iLemmaId'];
$sComment = $_REQUEST['sComment'];

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

saveComment($iLemmaId, $sComment);

// Make sure the entire lemma belongs to this user now
reviseLemma($iLemmaId, $_REQUEST['iUserId']);

// The error is the empty string if nothing goes wrong
echo mysql_error();

?>