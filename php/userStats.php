<?php

require_once('./attestationToolBox.php');

chooseDb($_REQUEST['sDatabase']);
$iUserId = $_REQUEST['iUserId'];
$sUser = $_REQUEST['sUser'];

$sQuery =
"SELECT COUNT(*) AS nrOfLemmata, nrOfQuotations " .
"  FROM lemmata, " .
"       (SELECT COUNT(quotations.id) nrOfQuotations " .
"          FROM quotations, lemmata " .
"         WHERE lemmata.id = quotations.lemmaid " .
"           AND revisorid = $iUserId) tmp " .
" WHERE revisorId = $iUserId GROUP BY revisorId";

$oResult = mysql_query ($sQuery, $GLOBALS['db']);

// The error is the empty string if nothing goes wrong
$sMySqlError = mysql_error();

if( strlen($sMySqlError) ) {
  print $sMySqlError;
}
else {
  if( $oRow = mysql_fetch_assoc ($oResult) ) {
    print "$sUser has revised " . $oRow['nrOfLemmata'] . " lemma";
    if( $oRow['nrOfLemmata'] != 1) print "ta";
    if( $oRow['nrOfLemmata'] > 0)
      print " (" . $oRow['nrOfQuotations'] . " citations)";
    print " so far.";
  }
  else
    print "You haven't revised anything yet...";
  mysql_free_result($oResult);
}

?>