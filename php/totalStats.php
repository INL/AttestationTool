<?php

require_once('./attestationToolBox.php');

chooseDb($_REQUEST['sDatabase']);

// Special OED behaviour
$sHideCondition =
  (substr($_REQUEST['sDatabase'], 0, 19) == "AttestationTool_OED")
  ? " WHERE hide = 0 "
  : '';

// NOTE: Lemmate that have their revisor id set to a negative value are 
// so-called locked. We count them as being unrevised yet.
$sQuery = "SELECT COUNT(*) totalNrOfLemmata, SUM(isNotRevised) nrNotRevised " .
 "      FROM (SELECT IF(revisorid IS NULL OR revisorid < 0,1,0) isNotRevised ".
 " FROM lemmata$sHideCondition) tmp";

$oResult = mysql_query ($sQuery, $GLOBALS['db']);

// The error is the empty string if nothing goes wrong
$sMySqlError = mysql_error();

if( strlen($sMySqlError) )
  print $sMySqlError;
else { 
  if( $oRow = mysql_fetch_assoc($oResult) )
    print $oRow['totalNrOfLemmata'] . " lemmata in all. " .
      $oRow['nrNotRevised'] . " to be revised yet...";
  else
    print "No lemmata revised yet";
  mysql_free_result($oResult);
}

?>