<?php

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

unlockLemma($_REQUEST['iLemmaId']);

?>