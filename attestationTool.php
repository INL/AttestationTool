<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

<title>IMPACT Attestation Tool v2015.03.17</title>

<link rel="shortcut icon" type="image/ico" href="./favicon.ico" />

<link rel="stylesheet" type="text/css" href="./styles/attestationTool.css">
   </link>

<script type="text/javascript" src="./js/globals.js">
</script>
<script type="text/javascript" src="./js/color.js">
</script>

<script type="text/javascript" src="./js/keyFunctions.js">
</script>

<script type="text/javascript" src="./js/ajaxFunctions.js">
</script>

<script type="text/javascript" src="./js/attestationTool.js">
</script>

<?php

require_once('./php/attestationToolBox.php');

$aBackgroundColors; # Global variable

# three things can happen here:
# 1. user logs in to export data (export box is checked)
# 2. user logs in to work on annotations (export box is NOT checked)
# 3. default, show inlog form

# possibility #1
if (isset($_REQUEST['sDatabase']) && isset($_REQUEST['bExport']) && isset($_REQUEST['sUser'])){   # this will be 'on' or an empty string

  $sHost = $_SERVER['HTTP_HOST'];
  $sExportType = $_REQUEST['sExportType'];
  header('Location: ./php/'.$sExportType.'?sDatabase='.$_REQUEST['sDatabase']);
}

# possibility #2
# user logs in so as to annotate
else if( isset($_REQUEST['sUser']) ) {
  $sUser = get_magic_quotes_gpc() ? $_REQUEST['sUser']
    : addslashes ($_REQUEST['sUser']); 
  $sDatabase = (isset($_REQUEST['sDatabase'])) ? $_REQUEST['sDatabase']
    : false;

  chooseDb($sDatabase);

  $sJavascriptArrayDeclarations = getBackgroundColorInfo($sDatabase);

  // make sure double attestations will never be added
  // (see: http://dba.stackexchange.com/questions/24531/mysql-create-index-if-not-exists)
  
  $oResult = mysql_query("SELECT COUNT(1) IndexIsThere ".
	"FROM INFORMATION_SCHEMA.STATISTICS ".
	"WHERE table_schema=DATABASE() ".
	"AND `COLUMN_NAME` in ('quotationId', 'onset') ".
	"AND `TABLE_NAME` = 'attestations' ".
	"AND `NON_UNIQUE` = 0;");
	
  if ($oRow = mysql_fetch_assoc ($oResult)) {
	if ($oRow['IndexIsThere'] != '2')
	{
	 mysql_query("ALTER TABLE attestations ADD UNIQUE INDEX quotationIdOnsetUnique (quotationId, onset);");
	}	
  }  
  
  // User check
  
  $oResult= mysql_query("SELECT id, name FROM revisors WHERE name = '$sUser'");
  echo mysql_error();

  $iUserId = 0;
  
  if ($oRow = mysql_fetch_assoc ($oResult)) {
    // Set the user name to the version in the database (so the user can type
    // in 'kAtrIEN', but it will always be displayed as 'Katrien'...)
    $sUser = $oRow['name'];
    $iUserId = $oRow['id'];

    print '<script type="text/javascript">' .
      " var sDatabase = '$sDatabase';\n" .
      " var sUser = '$sUser';\n" .
      " var iUserId = $iUserId;\n" .
	  " var bUseCtrlKey = ". ($GLOBALS['bUseCtrlKey'] ? "true":"false") .";";
    if( $sJavascriptArrayDeclarations ) // Only if the database has a types table (required since 2014 version)
      print $sJavascriptArrayDeclarations;
    print "</script>\n";
?>

</head>
<!-- NOTE that we update the totals column every 10 seconds -->
<body onLoad="javascript: document.onkeydown = keyDown; document.onkeyup = keyUp; document.onmouseup = endSelection; fillAttestationsDiv(false, false, false, false); addClickEventListener();">  <!-- onClick="javascript: hideTypeMenu();"> -->

<!-- Div for the messages -->
<div id=ajaxDiv></div>

<!-- For the numbers -->
<table width=100% border=0>
 <tr>
  <td align=left valign=top height=12px><span id=userStats>&nbsp;</span></td>
  <td align=center valign=top><span id=lastEdited>&nbsp;</span></td>
  <td align=right valign=top><span id=totalStats>&nbsp;</span></td>
 </tr>
</table>

<!-- This is the main div that the headword and its quotes are in -->
<div id=attestationsDiv></div>

<?php
 // NOTE that the file menu simple isn't there when we are not in multi types
 // mode. This fact is used in the Javascript file.
 if( $sJavascriptArrayDeclarations )
  print "<!-- Div for showing the different types one can choose from -->\n" .
    "<div id=typeMenu style='visibility: hidden'></div>\n";

  mysql_free_result($oResult);
		   } // End of: User check
  else // If there is no user called like this
    print "Oops! User <b>$sUser</b> is not known. Please try again...\n";
} 


# possibility #3
# Print inlog form
else { 
?>

</head>
<body onLoad="javascript: document.loginForm.sUser.focus(); if(navigator.userAgent.indexOf('Firefox')<0) {alert('This piece of software only works in Firefox. Please close your current browser and start again in Firefox. Thank you!');};" class=withBackground>

<center>
<h1>IMPACT Attestation Tool</h1>

<table>
 <tr>
  <td align=left>
    <form action="./attestationTool.php" method="post" name="loginForm" target="_self">
    <table>
    <tr>
    <td style="padding: 0px 30px 0px 30px">
	
	
	<?php
	// Display the projects list
	// This list must be set in /php/globals.php
	
	$iProjectListCounter = 1;
	$aProjectList = $GLOBALS['asProject'];
	$iProjectListLength = count($aProjectList);
	
	foreach ($aProjectList as $sProjectCode => $sProjectDescription) {
	
		
		print '<p>'.
		'<input type="radio" name="sDatabase" value="'.$sProjectCode.'"';
		if ($sProjectCode == $GLOBALS['sChecked'])
			print ' checked';
		print '>'.$sProjectDescription.
		'<br>'; 
		
		if ($iProjectListCounter >= 5 )
			{
			$iProjectListCounter = 1;
			print '</td><td style="padding: 0px 30px 0px 30px">';
			}
		else
			$iProjectListCounter++;
	}
	?>

    </td>
    </tr>
    </table>
    <br>
   User name <input id=sUser name=sUser type="text" autocomplete="on" size="15" maxlength="25" value="">
   <br>
   <br>
   Export data <input id=bExport name=bExport type="checkbox"><br>
   <select name=sExportType>
	<option value="export2alto.php" selected>Alto</option>
	<option value="export2mnw.php">MNW</option>	
	
   </select>
    </form>
  </td>
 </tr>
</table>

</center>

<div class=instructions>
<a href="./pages/instructions.html"><u>>> Instructions</u></a>
<p>
</div>

<?php
    } // End of: else { // Print inlog form
?>

</body>
</html>
