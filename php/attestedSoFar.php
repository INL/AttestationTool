<?php

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

print attestedSoFar($_REQUEST['iLemmaId']);

?>