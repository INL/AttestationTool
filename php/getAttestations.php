<?php

$sUser = get_magic_quotes_gpc() ? $_REQUEST["sUser"]
: addslashes ($_REQUEST["sUser"]); 
$iUserId = isset($_REQUEST["iUserId"]) ? $_REQUEST["iUserId"] : '';
$sDatabase = isset($_REQUEST["sDatabase"]) ? $_REQUEST["sDatabase"] : '';
$sPrevNext = isset($_REQUEST["sPrevNext"]) ? $_REQUEST["sPrevNext"] : false;
$iUserLemmaId = isset($_REQUEST["iLemmaId"]) ? $_REQUEST["iLemmaId"] : false;
$sLastDate = isset($_REQUEST["sLastDate"]) ? urldecode($_REQUEST["sLastDate"])
: false;
$sNewWords = isset($_REQUEST["sNewWords"]) ? urldecode($_REQUEST["sNewWords"])
: false;

// Default global variables
$iLemmaId = 0;
$sRevDate = 'unknown';

require_once('./attestationToolBox.php');

chooseDb($sDatabase);

// Unfortunately, we have to do this time and time again since we cannot use
// global variables (e.g. from attestationToolBox.php...).
$aBackgroundColors = getBackgroundColors($sDatabase);

printAttestations($sDatabase, $sUser, $iUserId, $sPrevNext, $iUserLemmaId,
		  $sLastDate);

///////////////////////////////////////////////////////////////////////////////
// Functions //////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////

function printAttestations($sDatabase, $sUser, $iUserId, $sPrevNext,
			   $iUserLemmaId, $sLastDate) {
  // Special OED behaviour
  $bOed = (substr($sDatabase, 0, 19) == "AttestationTool_OED");

  list($sQuery, $bLockTables) =
	   buildQuery($iUserId, $sPrevNext, $iUserLemmaId, $sLastDate, $bOed);

  printResult($sDatabase, $sUser, $iUserId, getResults($sQuery, $bLockTables),
	      $sPrevNext, $sLastDate, $bLockTables);
}

function getResults($sQuery, $bLockTables) {
  if( $bLockTables )
    mysql_query("LOCK TABLES lemmata WRITE, lemmata AS l1 READ, " .
		"lemmata AS l2 READ, lemmata AS l3 READ, quotations READ, " .
		"lemmata AS l4 READ, lemmata AS l5 READ, " .
		"quotations AS q READ, attestations READ, " .
		"groupAttestations READ", $GLOBALS['db']);

  $oResult = mysql_query($sQuery);

  print mysql_error();

  return $oResult;
}

function buildQuery($iUserId, $sPrevNext, $iUserLemmaId, $sLastDate, $bOed) {
  $bLockTables = false;

  $sHideCondition = ( $bOed ) ? " AND hide = 0 " : '';

  // The part for finding a new lemma id is used twice. Both in the main query
  // and in the sub query (called tmp). This sub query will get tremendously
  // slow if we don't narrow it down somewhat in this way.
  // NOTE that we give the table lemmata an alias here (l3) for the sub query.
  // Later on this alias is substituted for l2, which it needs to have for the
  // main query. This has to be done to be able to lock all the tables.
  $sLemmaIdPart = '';
  if( $sPrevNext ) {
    $sLemmaIdPart = "= (SELECT id FROM lemmata WHERE revisorId = $iUserId" .
      $sHideCondition;
    if( $sLastDate ) { // If the user filled in a date
      if( $sPrevNext == 'next' )
	$sLemmaIdPart .=
	  " AND revisionDate > '$sLastDate' ORDER BY revisionDate LIMIT 1)";
      else { // sPrevNext = prev
	if( $sLastDate != 'unknown')
	  $sLemmaIdPart .= " AND revisionDate < '$sLastDate'";
	if( $sLastDate == 'now') // Skip one
	  $sLemmaIdPart .= " ORDER BY revisionDate DESC LIMIT 1,1)";
	else
	  $sLemmaIdPart .= " ORDER BY revisionDate DESC LIMIT 1)";
      }
    }
    else { // If it's just the previous/next one, sorted by Id
      if( $sPrevNext == 'next' )
	$sLemmaIdPart .=
	  " AND lemmata.id > $iUserLemmaId ORDER BY id LIMIT 1)";
      else // sPrevNext = prev
	$sLemmaIdPart .=
	  " AND lemmata.id < $iUserLemmaId ORDER BY id DESC LIMIT 1)";
    }
  } // No previous/next.
  else {
    if( $iUserLemmaId ) { // In case of e.g token splitting
      $sLemmaIdPart = "= $iUserLemmaId"; // Geen hide condition
      
    }
    else { // Otherwise get a brand new one
      // Subquery to find out the id of the first lemma that hasn't been
      // revised yet.
      $sLemmaIdPart =
	"= (SELECT id FROM lemmata l3 WHERE revisorId IS NULL" .
	$sHideCondition . " LIMIT 1)";
    }
  }

  // NOTE that we LEFT JOIN on the pos table and assign a default reliability
  // of 1000 in case there was no attestation. This is to make sure they end
  // up on the top of the screen (they are sorted by the reliability)
  $sQuery =
    // New for OED: 'marked' and 'comment'
    "SELECT lemmata.id, marked, quotations.id as quotationId, " .
    " externalLemmaId, " . # <- for OED (link to article)
    " total.nrOfLemmata, lemma, comment, specialAttention, unfortunate, " .
    "revisionDate, tokenizedQuotation, ".
    // NEW for OED
    "partOfSpeech, dateFrom, dateTo, " .
    //
    "externalLemmaId, tmp.reliability, tmp.onsets, tmp.dubiousOnsets, " .
    "tmp.ellipticalOnsets, tmp.errorOnsets, tmp.groupAtts";
  if( $GLOBALS['aBackgroundColors'])
    $sQuery .= ", tmp.typeIds";

  // FROM part. We do this beforehand because in the prev/next case we add two
  // columns.
  $sFromPart = " FROM " .
    // Sub query for adding the reliability scores and concatenating the
    // onsets
    // NOTE the LEFT JOIN by which we make sure that quotes without
    // attestations are shown as well
    "(SELECT q.id as quoteId, " .
    "        IF(attestations.reliability IS NULL, 1000, " .
    "                          SUM(attestations.reliability)) AS reliability,".
    "        GROUP_CONCAT(IF(dubious, onset, NULL)) AS dubiousOnsets,".
    "        GROUP_CONCAT(IF(elliptical, onset, NULL)) AS ellipticalOnsets,".
    "        GROUP_CONCAT(IF(error, onset, NULL)) AS errorOnsets,".
    "        GROUP_CONCAT(onset) AS onsets," .
    "        GROUP_CONCAT(CONCAT(groupAttestations.id, '|', " .
    "                        groupAttestations.pos)) AS groupAtts";
  if( $GLOBALS['aBackgroundColors'] )
    $sFromPart .= ", GROUP_CONCAT(typeId) AS typeIds ";
  $sFromPart .=
    "   FROM quotations q" .
    "   LEFT JOIN attestations ON (attestations.quotationId = q.id) " .
    "   LEFT JOIN groupAttestations ON (attestations.quotationId = q.id" .
    "                  AND groupAttestations.attestationId = attestations.id)".
    "  WHERE q.lemmaId $sLemmaIdPart" .
    "  GROUP BY quoteId) tmp, " .
    // Sub query for determining the amount of lemmata done so far by this
    // user. This is done so we know whether we should print a previous
    // button or not.
    "(SELECT COUNT(*) nrOfLemmata " .
    " FROM lemmata l1" . 
    " WHERE revisorId = $iUserId) total, quotations, lemmata " .
    "WHERE ";

  // If we should get the next/previous one for this user
  if( $sPrevNext ) {
    // Columns too see if there another lemma before/after this one
    $sQuery .=
      ", IF(lemmata.id >= (SELECT IF(MAX(id) IS NULL, -1, MAX(id))" .
      " FROM lemmata WHERE revisorId = $iUserId), 1, 0) AS isLastLemma ";
    $sQuery .=
      ", IF(lemmata.id <= (SELECT IF(MIN(id) IS NULL, -1, MIN(id))" .
      " FROM lemmata WHERE revisorId = $iUserId), 1, 0) AS isFirstLemma ";

    $sQuery .= $sFromPart;

    // The user searches by date, or has clicked the 'Previous' or 'Next'
    // button: just find the lemma he/she revised before/after this one.
    $sQuery .= "lemmata.id = quotations.lemmaId " .
      "AND tmp.quoteId = quotations.id " .
      "AND lemmata.id $sLemmaIdPart ";
  } // No previous/next.
  else {
    
    $sQuery .=
      ", IF(lemmata.id >= (SELECT IF(MAX(id) IS NULL, -1, MAX(id))" .
      " FROM lemmata l4 WHERE revisorId = $iUserId), 1, 0) AS isLastLemma ";
    $sQuery .=
      ", IF(lemmata.id <= (SELECT IF(MIN(id) IS NULL, -1, MIN(id))" .
      " FROM lemmata l5 WHERE revisorId = $iUserId), 1, 0) AS isFirstLemma ";
    

    $bLockTables = true;
    $sQuery .= "$sFromPart lemmata.id = quotations.lemmaid " .
      "AND tmp.quoteId = quotations.id ";

    if( $iUserLemmaId ) // In case of e.g token splitting
      $sQuery .= "AND lemmata.id $sLemmaIdPart ";
    else { // Otherwise get a brand new one
      // Subquery to find out the id of the first lemma that hasn't been
      // revised yet.
      // Here we give the lemmata table its l2 alias
      $sLemmaIdPart = str_replace("lemmata l3", "lemmata l2", $sLemmaIdPart);
      $sQuery .= "AND lemmata.id $sLemmaIdPart ";
    }
  }
  
  $sQuery .="ORDER BY reliability DESC, (0-specialAttention), (0-unfortunate)";

  return array($sQuery, $bLockTables);
}

function printResult($sDatabase, $sUser, $iUserId, $oResult, $sPrevNext,
		     $sLastDate, $bLockTables) {
  $bPrevButton = 0;
  $bNextButton = 0;
  
  if (! $oResult) {
    print "No result found.<br>\n";
    return;
  }

  $bFirst = true;
  // Array for printing in Javascript later on
  $aQuotationIds = array();
  $aNrOfTokens = array();

  while( $oRow = mysql_fetch_assoc ($oResult) ) {
    if( $bFirst ) {
      $sRevDate = ( $oRow['revisionDate'] ) ? $oRow['revisionDate']
	: 'unknown';
      $iLemmaId = $oRow['id'];
      print "<table width=100% border=0><tr>";
      print "<td align=left width=25%>\n";

      // Here comes a new table for all the buttons. Having them in a table
      // makes it easier to display them on the same spot always

      // Previous button
      print "<table border=0><tr>\n";
      print "<td width=64px>";
      if( $oRow['nrOfLemmata'] > 0 &&
	  ((! isset($oRow['isFirstLemma'])) || $oRow['isFirstLemma'] == 0)) {
	print "<span class=textButton " .
	  // When nothing happened to this lemma, its revision date will not be
	  // set so we should unlock it.
	  "onClick=\"javascript:  if(sRevDate == 'unknown') " .
	  "unlockLemma($iLemmaId); " .
	  "fillAttestationsDiv($iLemmaId, 'prev', false, false);\" " .
	  "onMouseOver=\"javascript: this.style.cursor='pointer';\" " .
	  "title=\"Previous [F2 or 'd']\">" .
	  "Previous</span>\n";
	$bPrevButton = 1;
      }
      else
	print "<span class=textButtonEmpty>Previous</span>";
      print "</td>";
      // Next/Save/New button
      print "<td width=36px>";
      /// Hier
      if( ($sPrevNext && $sLastDate != 'unknown' &&
	   ! (isset($oRow['isLastLemma']) && $oRow['isLastLemma'])) ||
	  (isset($oRow['isLastLemma']) && $oRow['isLastLemma'] == 0)
	  ) {
	print  // Next
	  "<span class=textButton onClick=\"javascript:" .
	  "fillAttestationsDiv($iLemmaId, 'next', false, false);\"  " .
	  "onMouseOver=\"javascript: this.style.cursor='pointer';\" " .
	  "title=\"Next [F4 or 'f']\">Next</span></td>" .
	  // New
	  "<td width=88px align=center>" .
	  "<span class=textButton " .
	  "onMouseOver=\"javascript: this.style.cursor='pointer';\" " .
	  "onClick=\"javascript: " .
	  "fillAttestationsDiv(false, false, false, false);\" " .
	  // Quick & dirty spacing
	  "title='New [Spacebar]'>" .
	  "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" .
	  "New&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</a></td>\n";
	$bNextButton = 1;
      }
      else
	if( $sRevDate == 'unknown') {
	  // In this case you can only get a new one if you revise this one
	  // Empty 'Next' column
	  print "<span class=textButtonEmpty>Next</span></td>" .
	    // Save & new
	    "<td width=88px>" .
	    "<span class=textButton " .
	    "onMouseOver=\"javascript: this.style.cursor='pointer';\" " .
	    "onClick=\"javascript: if ( !warnForSaveAndNewButton() ) return;" .
	    "reviseLemma($iLemmaId); " .
	    // New citation
	    "fillAttestationsDiv(false, false, false, false);\" ".
	    "title='Save & New [Spacebar]'>Save&nbsp;&&nbsp;new</span>" .
	    "</td>\n";
	  // Also, the lemma is locked so no one else can get it on their
	  // screens simultaneously
	  lockLemma($iLemmaId, $iUserId);
	}
	else // Subtle difference, don't save (as it already was saved)
	  // Empty 'Next' button
	  print "<span class=textButtonEmpty>Next</span></td>" .
	    // New
	    "<td width=88px align=center>" .
	    "<span class=textButton " .
	    "onMouseOver=\"javascript: this.style.cursor='pointer';\" " .
	    "onClick=\"javascript: " .
	    "fillAttestationsDiv(false, false, false, false); \" " .
	    // Quick & dirty spacing
	    "title='New [Spacebar]'>" .
	    "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" .
	    "New&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</a></td>\n";
      // Close button.
      print "<td><span class=textButton " .
	"onMouseOver=\"javascript: this.style.cursor='pointer';\" " .
	// When nothing happened to this lemma, its revision date will not be
	// set so we should unlock it.
	"onClick=\"javascript: if(sRevDate == 'unknown') " .
	" unlockLemma($iLemmaId); " .
	" emptyScreen(); " .
	// Clear the interval for the moment
	"clearInterval(iIntervalId); " .
	" document.getElementById('attestationsDiv').innerHTML = " .
	"'Thank you very much. You can now close this window or " .
	"<a href=\'./attestationTool.php\'><u>log in</u></a> again.';\" " .
	">Close</span></td>\n";
      print "</tr></table>\n";
      // End of the buttons table 
      print "</td>";
      // Auto (de-)attestation
      print "<td width=10% align=center>" .
	// Auto attest
	"<span id=autoAttest " .
	"onMouseOver=\"javascript: this.style.cursor='pointer';\" " .
	"onClick=\"javascript: autoAttest($iLemmaId);\" " .
	"title='Auto attest [key: a]'>".
	"<img src='./images/auto.gif'></span>&nbsp;&nbsp;\n" .
	// Auto de-attest
	"<span id=autoDeAttest " .
	"onMouseOver=\"javascript: this.style.cursor='pointer';\" " .
	"onClick=\"javascript: autoDeAttest($iLemmaId);\" " .
	"title='Auto de-attest [key: z]'>".
	"<img src='./images/autoDeAttest.gif'></span>" .
	"</td>\n";
      // Database
      print "<td width=25% style='color: #AEAEAE; text-align: center;'>" .
	"Database: '$sDatabase'</td>\n";
      // Search by date
      print "<td align=right><span id=searchByDate " .
	" class=textButton onClick='javascript:\n" .
	"var sUserDate = document.getElementById(\"userDate\").value;\n" .
	"if( isValidDate(sUserDate) ) {\n" .
	" if(sRevDate == \"unknown\") " .
	"   unlockLemma($iLemmaId); " .
	" fillAttestationsDiv(false,\"prev\", encodeURIComponent(sUserDate), false);".
	"}\n" .
	"else\n" .
	" alert(sUserDate + \" is not a valid date (YYYY-MM-DD hh:mm:ss)\");'\n" .
	"onMouseOver='javascript: this.style.cursor=\"pointer\";'>Before</span>\n" .
	"<span class=textButton onClick='javascript:\n" .
	"var sUserDate = document.getElementById(\"userDate\").value;\n" .
	"if( isValidDate(sUserDate) ) {" .
	" if(sRevDate == \"unknown\") " .
	"   unlockLemma($iLemmaId); " .
	" fillAttestationsDiv(false,\"next\",encodeURIComponent(sUserDate), false);".
	"}\n" .
	"else\n" .
	" alert(sUserDate + \" is not a valid date (YYYY-MM-DD hh:mm:ss)\");'\n" .
	"onMouseOver='javascript: this.style.cursor=\"pointer\";'>After</span>\n" .
	"<input id=userDate type=text size=21 maxlength=19 " .
	"value=\"YYYY-MM-DD hh:mm:ss\" onFocus='javascript: document.onkeydown = dummyKey; if( this.value == \"YYYY-MM-DD hh:mm:ss\") this.value = \"\";' onBlur='javascript: document.onkeydown = keyDown;'></td>";
      print "</tr></table>\n";
      
      print "<table width=100% border=0><tr>";
      // New for OED: mark the lemma
      ///      print "<td>&nbsp;&nbsp;Lemma:&nbsp;<span class=highlighted " .
      $sMarkedStyle = ($oRow['marked'] && ($oRow['marked'] == 1) )
	? 'lemmaMarked' : 'lemmaUnmarked';
      print "<td>" .
	"<div id=lemmaMark class=$sMarkedStyle " .
	"title=\"Mark this lemma ['m']\" " .
	"onMouseOver='javascript: this.style.cursor=\"pointer\";' " .
	"onClick='javascript: markLemma(this);'>" .
	"&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div></td>" .
	// New for OED: comment
	"<td>" .
	"<div id=addCommentDiv" .
	" onMouseOver='javascript: this.style.cursor=\"pointer\";' " .
	"onClick='javascript: makeCommentBoxEditable();'>" .
	"<img src='./images/comment.png' title='Add comment [F8]'>" .
	"</div></td>" .
	"<td>Lemma:&nbsp;<span class=highlighted " .
	"style='background-color: #FDD017'>" .
	// Spaces can occur, and we replace them by non-breaking ones as that 
	// looks better.
	// Also, we subsitute dashes for &#8209; which are also non-breakable.

	str_replace('-', '&#8209;', str_replace(' ', '&nbsp;', $oRow['lemma']));
      if( strlen($oRow['partOfSpeech']) )
	print ",&nbsp;" . $oRow['partOfSpeech'];
      print "</span>";
      if( $oRow['externalLemmaId'] )
	print externalLemmaId2link($oRow['externalLemmaId']);
      print "</td><td width=100%>" . 
	"<span id=attestedSoFar " .
	// Old functionality - commented out
	// "onMouseOver='javascript: displayAttestedSoFar($iLemmaId);' " . 
	// "onMouseOut='javascript: hideAttestedSoFar();'>".
	">&nbsp;&nbsp;&nbsp;</span></td></tr></table>";

      // For OED: comment box
      $sDisplay = 'none';
      $sValue = '';
      if( $oRow['comment'] && strlen($oRow['comment']) ) {
	$sDisplay = 'block';
	$sValue = $oRow['comment'];
      }
      print "<div id=commentBox style='display: $sDisplay'>" .
	$oRow['comment'] .  "</div></td></tr>\n";
      
      $bFirst = false;

      // UNLOCK the tables again
      if( $bLockTables)
	mysql_query("UNLOCK TABLES", $GLOBALS['db']);
    } // End of if( $bFirst)

    // Maintain an array of citation id's to print in Javascript later on
    array_push($aQuotationIds, $oRow['quotationId']);
    // Split the words in the quote so we can make separate span tags of them
    // below
    //   NOTE that we first take out any occurence of the <COL .../> tag which
    //   messes up the layout
    
    $aQList = explode("\n", preg_replace('/<col [^>]+>/i', '',
					 $oRow['tokenizedQuotation']));
    
    // Also split the list of positions, so we know which words to highlight
    $aOnsets = (strlen($oRow['onsets']))? split(",", $oRow['onsets']): array();
    // Also retrieve the list of dubious, elliptical and erroneous onsets
    $aDubiousOnsets = (strlen($oRow['dubiousOnsets'])) ?
      explode(",", $oRow['dubiousOnsets']) : array();
    $aEllipticalOnsets = (strlen($oRow['ellipticalOnsets'])) ?
      explode(",", $oRow['ellipticalOnsets']) : array();
    $aErrorOnsets = (strlen($oRow['errorOnsets'])) ?
      explode(",", $oRow['errorOnsets']) : array();
    // The list of types, going with the offsets
    $aTypeIds = ($GLOBALS['aBackgroundColors'] && strlen($oRow['typeIds']))?
      split(",", $oRow['typeIds']): FALSE;
      ?>
      <p>
	 <div id=citaat_<?php echo $oRow['quotationId']; ?> class=citaat
	     style='background: #<?php print getBackgroundColor($oRow['reliability']); ?>'
	     onClick="javascript:
                 if( iCurSelQuote != indexInQuotationIds(<?php echo $oRow['quotationId'];?>) ) {
                    unselectCurrentQuotation();
                    iCurSelQuote= indexInQuotationIds(<?php echo $oRow['quotationId'];?>);
                    selectCurrentQuotation();
                 }">
 <table border=0 width=100%> <!-- Citation table -->
 <tr>
 <td width=46px>
 <!-- Bad citation button -->
 <span id=badCitation_<?php print $oRow['quotationId']; if( $oRow['specialAttention'] == 0) print " class=badCitation"; else print " class=goodCitation"; ?>
 title="Mark as good/bad citation [F9 or 'x']"
 onMouseOver="javascript: this.style.cursor='pointer';"
 onClick="javascript: toggleBadCitation(<?php print $iLemmaId; ?>, 
 <?php print $oRow['quotationId']; ?>,
 '<?php print $sDatabase; ?>');">
 <img src=<?php if( $oRow['specialAttention'] == 0) print " \"./images/badCitation.gif\""; else print "\"./images/goodCitation.gif\""; ?>></span>
 <!-- Unfortunate citation button -->
 <span id=unfortunate_<?php print $oRow['quotationId']; if( $oRow['unfortunate'] == 1) print " class=unfortunate"; else print " class=fortunate";?>
 title="Mark quote as unfortunate ['u']"
 onMouseOver="javascript: this.style.cursor='pointer';"
 onClick="javascript: toggleUnfortunateCitation(<?php print $iLemmaId; ?>, 
 <?php print $oRow['quotationId']; ?>,
 '<?php print $sDatabase; ?>');">
 <img src=<?php if( $oRow['unfortunate'] == 1) print " \"./images/unfortunate.gif\""; else print "\"./images/fortunate.gif\""; ?>></span>
 </td>
 <td align=left>
 <div class=innerCitaatBox>       
 <?php

 // Build the edit environment
 $iQListSize = sizeof ($aQList);
 // Maintain an array of nr of tokens per quotation. We prepend 'qid_' here in
 // order to make sure it is an associative array (with numbers as indexes this
 // is not overly guaranteed in Javascript).
 array_push($aNrOfTokens, "'qid_" . $oRow['quotationId'] . "': " . $iQListSize);

 $iGroupId = -1;
 $iGroupNr = -1;
 for ($i=0; $i < $iQListSize ; $i++) {
   // "12\n13\nhij\n Hij, " becomes [12,13,'hij', ' Hij, ']
   $aWordTuple = split("\t", $aQList[$i]);
   // NOTE that we encode utf8 here, so as to keep diacritics
   // ALSO NOTE that we replace double quotes by left double quotes
   // because otherwise the Javascript can not extract the title right
   $sSafeWordForm = str_replace('"', '&quot;', $aWordTuple[2]);
 
   // NOTE that the title attribute is used to specify all kinds of parameters
   // in a tab separated list.
   // iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset<TAB>sWordform
   // This is used by change/addAttestation() etc. functions.

   print "<span id=att_" . $oRow['quotationId'] . "_" . $i . " title='" .
     $oRow['quotationId'] . "\t$iLemmaId\t$i\t" . $aWordTuple[0] . "\t" .
     $aWordTuple[1] . "\t" .
     // Here we replace the single quote with an HTML entity
     str_replace ("'", '&#39', $sSafeWordForm) . "' class=";
   $iIndex = array_search($aWordTuple[0], $aOnsets);
   if( $iIndex !== FALSE ) {
     // If there are no background colors (because we are running in the simple
     // mode), aTypeIds is FALSE
     $sBackgroundColor = ($aTypeIds) ? 
       $GLOBALS['aBackgroundColors']["'" . $aTypeIds[$iIndex] . "'"] :
       '#FDD017'; // <- The default color
     print "highlighted style='background-color: $sBackgroundColor'";
   }
   else 
     print "lowlighted";
   
   $sPrintWord = str_replace('&ddd;', '&hellip;', $aWordTuple[3]);
   // NOTE that it differs whether you click or hold down the key that defines
   // group attestations (the backtick '`' by default) while clicking.
   print " onMouseDown=\"javascript: startSelection(this);\"" .
     " onMouseOver=\"javascript: addToSelection(this);\"";
   print " onClick=\"javaScript:if(bGroupKeyPressed) addGroupAttestation(" .
     $oRow['quotationId'] . ", $iLemmaId, $i, " . $aWordTuple[0] . ", " .
     $aWordTuple[1] .
     // Here we replace the single quote with an escaped quote
     ", '" . str_replace ("'", '\\\'', str_replace ("&", '&amp;', $sSafeWordForm)) . "');" .
     " else { if(bSelectKeyPressed) " .
     "focusAttestation(this, " . $oRow['quotationId'] . ", $i); " .
     "      else toggleAttestation(" . $oRow['quotationId'] .
     ", $iLemmaId, $i, " . $aWordTuple[0] . ", " . $aWordTuple[1] .
     // Here we replace the single quote with an escaped quote
     ", '" . str_replace ("'", '\\\'', str_replace ("&", '\\&', $sSafeWordForm)) .
     "');}\">";
   if( array_search($aWordTuple[0], $aDubiousOnsets) !== FALSE)
     print "<img src='images/dubious.gif'>";
   if( array_search($aWordTuple[0], $aEllipticalOnsets) !== FALSE)
     print "<img src='images/elliptical.gif'>";
   if( array_search($aWordTuple[0], $aErrorOnsets) !== FALSE)
     print "<img src='images/erroneous.gif'>";
   print $sPrintWord; // The actual word
   // Check whether or not this attestation is a member of a group
   if( preg_match("/\b(\d+)\|$i\b/", $oRow['groupAtts'], $aMatches) ) {
     // NOTE that we print our own group nr here, not the id (which can get
     // pretty long after a while)
     if( $iGroupId != $aMatches[1] ) {
       $iGroupId = $aMatches[1];
       if( $iGroupNr == -1)
	 $iGroupNr = 1;
       else
	 $iGroupNr++;
     }
     print "<sub>$iGroupNr</sub>";
   }
   print "</span>\n";
   if( $sPrintWord == "." ) // In the DBNL case this kind of helps.
     print "<br>";
 }
 ?>
 </div> <!-- End innerCitaatBox -->
 </td>
 <td>
 <td valign=top width=14>

<div class=quotationDateInfo>
<?php
 if( $oRow['dateFrom'] )
   print $oRow['dateFrom'];
 if( $oRow['dateTo'] ) {
   if( ($oRow['dateFrom'] != $oRow['dateTo']) ) {
     if( $oRow['dateFrom'] )
       print "&nbsp;-&nbsp;";
     print $oRow['dateTo'];
   }
 }
?>
</div>
 </td>
 </tr>
 </table> <!-- End of citation table -->
 </div> <!-- End of citation -->
 <?php
     } // End of while( $oRow = mysql_fetch_assoc...)
  mysql_free_result($oResult);

  if( $bFirst ) // If bFirst is still true, this means nothing was printed
    print "&nbsp;&nbsp;No items found.\n";
  else {
    // Print some variables in Javascript 
    // NOTE that this piece of code is evaluated (eval()) after this page is
    // loaded with AJAX
    // It is supposed to be all on one line (to make things easier code-wise)
    print "\naQuotationIds = [";
    if( count($aQuotationIds) )
      print join(", ", $aQuotationIds);
    print "]; " .
      "aNrOfTokens = {";
    if( count($aNrOfTokens) )
      print join(", ", $aNrOfTokens);
    print "}; " .
      "iLemmaId = $iLemmaId; " .
      "sRevDate = '$sRevDate'; " .
      "bPrevButton = $bPrevButton; " .
      "bNextButton = $bNextButton;";
  }
}

?>