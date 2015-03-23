<?php

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

setCurrentAttDubious($_REQUEST['iQuotationId'], $_REQUEST['iOnset'],
		     $_REQUEST['bNewDubious']);

// Make sure the entire lemma belongs to this user now
reviseLemma($_REQUEST['iLemmaId'], $_REQUEST['iUserId']);

// The error is the empty string if nothing goes wrong
echo mysql_error();


?>