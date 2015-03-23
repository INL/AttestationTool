<?php

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

setCurrentAttError($_REQUEST['iQuotationId'], $_REQUEST['iOnset'],
		   $_REQUEST['bNewError']);

// Make sure the entire lemma belongs to this user now
reviseLemma($_REQUEST['iLemmaId'], $_REQUEST['iUserId']);

// The error is the empty string if nothing goes wrong
echo mysql_error();


?>