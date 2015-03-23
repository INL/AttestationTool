<?php

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

reviseLemma($_REQUEST['iLemmaId'], $_REQUEST['iUserId']);

// The error is the empty string if nothing is wrong
echo mysql_error();

?>