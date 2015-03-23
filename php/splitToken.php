<?php

require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);

printLog('sSplitToken: ' . $_REQUEST['sSplitToken']);
printLog('sSplitToken url decoded: ' . rawurldecode($_REQUEST['sSplitToken']));

// NOTE that rawurldecode doesn't "decode" + signs into spaces
splitToken(rawurldecode($_REQUEST['sSplitToken']), $_REQUEST['iQuotationId'],
	   $_REQUEST['iOnset'], $_REQUEST['bDubiosity'],
	   $_REQUEST['bElliptical'], $_REQUEST['bError'], $_REQUEST['iTypeId']);

// If you update something, the entire lemma belongs to you
reviseLemma($_REQUEST['iLemmaId'], $_REQUEST['iUserId']);

echo mysql_error();