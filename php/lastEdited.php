<?php

require_once('./attestationToolBox.php');

chooseDb($_REQUEST['sDatabase']);

$sQuery = "SELECT DISTINCT revisorId, revisionDate " .
"FROM lemmata WHERE id = " . $_REQUEST['iLemmaId'];

$oResult = mysql_query ($sQuery, $GLOBALS['db']);

// The error is the empty string if nothing goes wrong
$sMySqlError = mysql_error();

if( strlen($sMySqlError) )
  print $sMySqlError;
else {
  if( $oRow = mysql_fetch_assoc ($oResult) )
    if( $oRow['revisorId'] > 0 )
      print "Last edited " . $oRow['revisionDate'];
    else
      print "&nbsp;";
  else
    print "No revision date for lemma with id '". $_REQUEST['iLemmaId'] . "'";
  mysql_free_result($oResult);
}
?>