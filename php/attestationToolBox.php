<?php

require_once('globals.php');



$db = mysql_connect($GLOBALS['sDbHost'], $GLOBALS['sDbUser'], $GLOBALS['sDbPassWord'])
  or trigger_error(mysql_error(), E_USER_ERROR);

function chooseDb($sDatabase) {
  mysql_select_db ($sDatabase, $GLOBALS['db']);
  mysql_query("SET NAMES 'utf8';");
}

function reviseLemma($iLemmaId, $iUserId) {
  $sQuery = "UPDATE lemmata ".
    "SET revisorId = $iUserId, revisionDate = NOW() " .
    "WHERE id = $iLemmaId";
 
  $result = mysql_query ($sQuery, $GLOBALS['db']);
}

// When somebody opens a page with a new lemma, this lemma is locked, so no
// one else can get it on their screen (and edit it) at the same time.
// To keep track of who has locked it we assign the NEGATIVE user id to all
// citation instances of this lemma, plus the date and time.
function lockLemma($iLemmaId, $iUserId) {
  $sQuery = "UPDATE lemmata SET revisorId = " . (0 - $iUserId) . 
    ", revisionDate = NOW() WHERE id = $iLemmaId";
  mysql_query($sQuery, $GLOBALS['db']);
}

// When the page unloads (e.g. someone closes it, or the hits the 'Previous'
// button) the lemma is unlocked.
function unlockLemma($iLemmaId) {
  $sQuery = "UPDATE lemmata SET revisorId = NULL, revisionDate = NULL " .
    "WHERE id = $iLemmaId";
    
  $oResult = mysql_query($sQuery, $GLOBALS['db']);

  echo mysql_error();
}

function markLemma($iLemmaId, $bNewMarked) {
  $sQuery = "UPDATE lemmata SET marked = $bNewMarked WHERE id = $iLemmaId";
  mysql_query($sQuery, $GLOBALS['db']);
  print mysql_error();
}

function saveComment($iLemmaId, $sComment) {
  # Delete leading and trailing white space
  $sComment = preg_replace("/^\s+/", '', $sComment);
  $sComment = preg_replace("/\s+$/", '', $sComment);
  $sQuery = "UPDATE lemmata SET comment = '" . addslashes($sComment) . "' " .
    "WHERE id = $iLemmaId";
  mysql_query($sQuery, $GLOBALS['db']);
  print mysql_error();

  # Then, in order to always show the current state of affairs in the database
  # we select the comment we just inserted, and return it.
  $sQuery = "SELECT comment FROM lemmata WHERE id = $iLemmaId";
  $oResult = mysql_query($sQuery);
  echo mysql_error();
  if( $oResult ) {
    if( $oRow = mysql_fetch_assoc($oResult) ) {
      print $oRow['comment'];
    }
    mysql_free_result($oResult);
  }
}

// Make a new attestation
// NOTE that we do typed as well as non-typed attestations here depending on
// the way this function was called.
function addAttestation($iLemmaId, $iQuotationId, $iNewOnset, $iNewOffset,
			$sNewWordForm, $iTypeId, $sAttestationComment) {
  $sQuery = "INSERT INTO attestations " .
    "(quotationId, onset, offset, reliability, wordForm, comment";
  if( $iTypeId ) {
    $sQuery .= ", typeId";
  }
  $sQuery .= ") VALUES ($iQuotationId, $iNewOnset, $iNewOffset, 0, '" .
    $sNewWordForm . "', '".$sAttestationComment."'";
  if( $iTypeId ) {
    $sQuery .= ", $iTypeId";
  }
  $sQuery .= ")";
  // In case it only changes type
  if( $iTypeId) {
    $sQuery .= " ON DUPLICATE KEY UPDATE typeId = $iTypeId, comment = '$sAttestationComment';";
  }
  else { # If we don't have types (single attestation mode)
    $sQuery .= " ON DUPLICATE KEY UPDATE id = id;";
  }

  $oResult = mysql_query ($sQuery, $GLOBALS['db']);

  echo mysql_error();
}

# Make a group attestation from iStartPos until endPos
# If the first one was attested already
function makeGroupAttestation($iLemmaId, $iQuotationId, $iStartPos, $iEndPos,
			      $sTokenTuples, $iFirstAttestationType,
			      $iDefaultType, $iFirstOnset, $iFirstOffset,
			      $sFirstWordForm, $bMultiType) {
  
  // Check that none but the first one is in a group already
  $iMin = ($iStartPos < $iEndPos) ? ($iStartPos + 1) : $iEndPos;
  $iMax = ($iStartPos < $iEndPos) ? $iEndPos : ($iStartPos - 1);
  $sQuery = "SELECT * FROM groupAttestations, attestations " .
    "WHERE attestations.quotationId = $iQuotationId" .
    " AND groupAttestations.attestationId = attestations.id" .
    " AND pos >= $iMin AND pos <= $iMax";
  
  $oResult = mysql_query($sQuery);
  echo mysql_error();
  if( $oResult ) {
    if( $oRow = mysql_fetch_assoc($oResult) ) {
      print "One of the attestations belongs to a group already.";
      return;
    }
  }

  // If the default type was not set and we are in multi-type mode
  // (i.e. the user didn't hold down a key while dragging), set it to the type
  // of the first attestation clicked on, or to 1 if the first one was
  // unattested
  if( ! $iDefaultType && $bMultiType )
    $iDefaultType = ($iFirstAttestationType) ? $iFirstAttestationType : 1;
  // If there was no type for the first attestation, it is the default type
  if( ! $iFirstAttestationType ) {
    if( $bMultiType )
      $iFirstAttestationType = ($iDefaultType) ? $iDefaultType : 1;
    else
      $iFirstAttestationType = 1;
  }

  // Put in the first attestation (if it was in already, nothing happens)
  addAttestation($iLemmaId, $iQuotationId, $iFirstOnset, $iFirstOffset,
		 $sFirstWordForm, $iFirstAttestationType, false);

  // Find out if there is a group already that the first alt-clicked one
  // belongs to. If not, create a group.
  $iAttestationGroupId =
    getAttestationGroupId($iFirstOnset, $iStartPos, $iQuotationId);

  // Put in all the attestations
  $aTokenTuples = explode('||', $sTokenTuples);
  $sValues = '';
  for($i = 0; $i < sizeof($aTokenTuples); $i++ ) {
    // Tuple: pos|onset|offset|wordForm|inDb
    $aTokenTuple = explode('|', $aTokenTuples[$i]);
    // We add an attestation of the default type if the attestation was not in
    // already (in which case it just keeps its own type)
    if( ! $aTokenTuple[4] ) 
      addAttestation($iLemmaId, $iQuotationId, $aTokenTuple[1],
		     $aTokenTuple[2], $aTokenTuple[3], $iFirstAttestationType, false);
    
    // Add it to the group
    insertGroupAttestation($iAttestationGroupId, $iQuotationId,
			   $aTokenTuple[1], $aTokenTuple[0]);
    
  }
}

function addGroupAttestation($iGroupOnset, $iGroupPos, $iGroupQuotationId,
			     $iOnset, $iPos, $bWasAttestedAlready) {
  printLog("addGroupAttestation($iGroupOnset, $iGroupPos, $iGroupQuotationId,".
	   "$iOnset, $iPos, $bWasAttestedAlready)\n");

  // First check if the new attestation belongs to a group already
  // This is only needed when it was attested already
  if( $bWasAttestedAlready ) {
    $sQuery = "SELECT groupAttestations.id " .
      "FROM attestations, groupAttestations " .
      "WHERE groupAttestations.attestationId = attestations.id" .
      "  AND attestations.onset = $iOnset" .
      "  AND attestations.quotationId = $iGroupQuotationId";
    $oResult = mysql_query($sQuery);
    echo mysql_error();
    if( $oResult ) {
      $iReturn = 0;
      if( $oRow = mysql_fetch_assoc($oResult) ) {
	print "That attestation already belongs to this or another group.";
	$iReturn = 1;
      }
      // First free the result
      mysql_free_result($oResult);
      if( $iReturn )
	return; // Then return
    }
  }

  // Find out if there is a group already that the first alt-clicked one
  // belongs to. If not, create a group.
  $iAttestationGroupId =
    getAttestationGroupId($iGroupOnset, $iGroupPos, $iGroupQuotationId);

  // Now add the current attestation to the group the other alt-clicked one
  // belongs to as well (or the one just created).
  if( $iAttestationGroupId ) { // This should always be the case, but in case
    // of errors, let's not generate more...
    insertGroupAttestation($iAttestationGroupId, $iGroupQuotationId, $iOnset,
			   $iPos);
  }
}

// This lets you add an attestation to group without knowing its id
function insertGroupAttestation($iAttestationGroupId, $iGroupQuotationId,
				$iOnset, $iPos) {
  // The ON DUPLICATE KEY bit is to enable people to `-click on an
  // attestation that is already part of the group, without MySQL
  // complaining about it (and without anything happening at all really...)
  $sQuery = "INSERT INTO groupAttestations (id, attestationId, pos) " .
    "SELECT $iAttestationGroupId, attestations.id, $iPos FROM attestations".
    " WHERE attestations.onset = $iOnset".
    "   AND quotationId = $iGroupQuotationId " .
    "ON DUPLICATE KEY UPDATE groupAttestations.id = groupAttestations.id";
  
  mysql_query($sQuery);
  echo mysql_error();
}

function getAttestationGroupId($iGroupOnset, $iGroupPos, $iGroupQuotationId) {
  $sQuery = "SELECT groupAttestations.id " .
    " FROM groupAttestations, attestations " .
    "WHERE groupAttestations.attestationId = attestations.id" .
    "  AND attestations.onset = $iGroupOnset".
    "  AND attestations.quotationId = $iGroupQuotationId";
  

  $oResult = mysql_query ($sQuery, $GLOBALS['db']);
  echo mysql_error();

  $iId = 0;
  $iNrOfGroups = 0;
  if( $oResult ) {
    if( $oRow = mysql_fetch_assoc ($oResult) )
      $iId = $oRow['id']; // Existing group
    else { // No group yet, so we make one
      // The IF() part is because the very first time the MAX(id) is NULL.
      // The ON DUPLICATE KEY bit is to enable people to alt-click on an
      // attestation that is already part of the group, without MySQL
      // complaining about it
      $sQuery = "INSERT " .
	"INTO groupAttestations (groupAttestations.id, attestationId, pos) " .
	"SELECT tmp.maxGroupAttId, attestations.id, $iGroupPos" .
	"  FROM (SELECT IF(MAX(groupAttestations.id) IS NULL, 1," .
	"                  MAX(groupAttestations.id)+1) AS maxGroupAttId" .
	"        FROM groupAttestations) tmp," .
	"       attestations" .
	" WHERE attestations.onset = $iGroupOnset " .
	"   AND attestations.quotationId = $iGroupQuotationId " .
	"ON DUPLICATE KEY UPDATE groupAttestations.id = groupAttestations.id";
      
      mysql_query($sQuery);
      echo mysql_error();
      
      // Retrieve the id just inserted.
      $sQuery = "SELECT groupAttestations.id ".
	"FROM groupAttestations, attestations " .
	"WHERE groupAttestations.attestationId = attestations.id" .
	"  AND attestations.onset = $iGroupOnset" .
	"  AND attestations.quotationId = $iGroupQuotationId";
      
      $oResult2 = mysql_query ($sQuery, $GLOBALS['db']);
      echo mysql_error();

      if( $oResult2 ) {
	if( $oRow = mysql_fetch_assoc($oResult2) )
	  $iId = $oRow['id'];
	mysql_free_result($oResult2);
      }
    }
    mysql_free_result($oResult);
  }
  
  return $iId;
}

// Delete an attestation
function deleteAttestation($iLemmaId, $iQuotationId, $iOnset) {
  // First delete the attestation from any group it might occur in
  $sQuery= "DELETE groupAttestations.* FROM groupAttestations, attestations ".
    "WHERE groupAttestations.attestationId = attestations.id AND " .
    "attestations.quotationId = $iQuotationId AND attestations.onset=$iOnset";
  mysql_query ($sQuery, $GLOBALS['db']);
  echo mysql_error();

  // It might be that there is just one attestation left in the group, in
  // which case we have to delete the group altogether.
  // NOTE that we actually throw away any leftover 1-member groups, but since
  // we always do that, there aren't any others.
  // (or there shouldn't be anyway...)
  $sQuery = "DELETE groupAttestations.* " .
    "FROM groupAttestations, (SELECT id AS groupAttId, COUNT(*) AS nrOfAtts" .
    "                       FROM groupAttestations GROUP BY groupAttId) tmp ".
    "WHERE groupAttestations.id = tmp.groupAttId AND tmp.nrOfAtts = 1";
  mysql_query ($sQuery, $GLOBALS['db']);
  echo mysql_error();

  // Now throw away the attestation itself
  $sQuery = "DELETE FROM attestations " .
    "WHERE quotationId = $iQuotationId AND onset = $iOnset";

  mysql_query ($sQuery, $GLOBALS['db']);
  echo mysql_error();
}

function getAttestationGroupPositions($iQuotationId, $iPos) {
  $sQuery = "SELECT GROUP_CONCAT(pos) AS positions FROM groupAttestations " .
   "WHERE groupAttestations.id =" .
   "  (SELECT groupAttestations.id FROM groupAttestations, attestations" .
   "   WHERE attestations.id = groupAttestations.attestationId" .
   "     AND attestations.quotationId = $iQuotationId" .
   "     AND groupAttestations.pos = $iPos)";

  $oResult = mysql_query($sQuery, $GLOBALS['db']);
  $sMySqlError = mysql_error();
  if( $sMySqlError )
    // NOTE that we deliberately prepend the string "ERROR" here, so we can
    // detect this in the xmlHttp.responseText
    print "ERROR: $sMySqlError";

  if( $oResult ) {
    if( $oRow = mysql_fetch_assoc ($oResult) )
      print $oRow['positions'];
    mysql_free_result($oResult);
  }
}

// Auto attestation
// This function is called via AJAX, so everything we print is caught via the
// xmlHttp.responseText
// We do an INSERT query for every 100 to avoid the query getting too long.
function autoAttest($iLemmaId, $iUserId, $sNewWord, $iTypeId, $bDubious,
		    $bElliptical, $bErroneous, $sIdOfLatestAttestedWord) {
			
  // Get all the quotations
  $sQuery = "SELECT id AS quotationId, tokenizedQuotation " .
    "FROM quotations " .
    "WHERE quotations.lemmaId = $iLemmaId ";

  $oResult = mysql_query($sQuery, $GLOBALS['db']);
  echo mysql_error();
  
  $sValues = '';
  
  $sWordForm;
  $sPrintResult = '';
  $sPrintGroupMembersSeparator = ',';
  $sPrintGroupSeparator = '';

  
  // if we have more than one word, get the first word 
  // and also the number of expected words
  $sFirstNewWord = $sNewWord;
  $aCollectionOfNewWord = split("@@@", $sNewWord);
  $sNewWord = str_replace("@@@", " ", $sNewWord);
  $iNumberOfNewWords = count($aCollectionOfNewWord);
  if ( $iNumberOfNewWords > 1 )
	$sFirstNewWord = $aCollectionOfNewWord[0];
	
  
  while( $oRow = mysql_fetch_assoc ($oResult) ) {
    
    // Split the words in the quotation.
    // The words come in tuples:
    // onset<TAB>offset<TAB>canonical word form<TAB>printable word form
    $aWordTuples = split("\n", $oRow['tokenizedQuotation']);
	
	$sQuotationId = $oRow['quotationId'];

    // If the word was marked as a new word, add it
	
	$i = 0;
	
	while ( $i<count($aWordTuples) ) {
	
	  $iCountStep = 1;
	
	  // get current tuple
	  $sWordTuple = $aWordTuples[$i];
	
      $aWordTuple = split("\t", $sWordTuple);
      // We take $aWordTuple[2] which is the canonical word form
      // We utf8_encode the forms to look them up in the array
      // The unencoded form is used to put in the database, which will encode
      // it right itself.
	  
      if( $aWordTuple[2] == $sFirstNewWord) {
		
		// at this point, we have a match for the first word of the group searched for
		// now try to match the whole group
		if (  ($i + $iNumberOfNewWords) < count($aWordTuples) )
			{
			
			// first build the whole string to have to match with, from the content of the database
			$strToMatch = $sFirstNewWord;
			for ($j = $i+1; $j < ($i + $iNumberOfNewWords); $j++)
				{
				$sWordTuple = $aWordTuples[$j];	
				$aWordTuple = split("\t", $sWordTuple);
				$strToMatch .= " ". $aWordTuple[2];
				}
				
			// make double check: is the last MANUALLY attested word
			// part of the string we will be trying to match with now?
			// If so, cancel this match attempt, to prevent DOUBLED attestation
			$bAlreadyAttested = FALSE;
			for ($j = $i; $j < ($i + $iNumberOfNewWords); $j++)
			{
				if ("att_" . $oRow['quotationId'] . '_' . $j == $sIdOfLatestAttestedWord)
					{
					$bAlreadyAttested = TRUE;
					}
			}
			
			// do both strings match thoroughly?
			if ($sNewWord == $strToMatch && $bAlreadyAttested == FALSE)
				{
				$sWordTuple = $aWordTuples[$i];
				$aWordTuple = split("\t", $sWordTuple);
					
				$sValues = "(". $oRow['quotationId'] . ", " .
					  $aWordTuple[0] . ", " . $aWordTuple[1] . ", 0, '" .
					  addslashes($aWordTuple[2]) . "'";

				if( $iTypeId )
					  $sValues .= ", $iTypeId";
				$sValues .= ", $bDubious, $bElliptical, $bErroneous)";

				$sPrintResult .= $sPrintGroupSeparator . "att_" . $oRow['quotationId'] . '_' . $i;
				$sPrintGroupSeparator = "|";
				
				// insert the first word the usual way
				insertAutoAttestations($sValues, $iTypeId);
				
				
				// then, make group by adding the following member:
				// later on, the javascript will process this response to build true groups
				// (js-function buildGroupsInAutoAttest)
				for ($j = ($i + 1); $j < ($i + $iNumberOfNewWords); $j++)
					{
					$sPrintResult .= $sPrintGroupMembersSeparator . "att_" . $oRow['quotationId'] . '_' . $j;
					}
					
					
				$iCountStep = $iNumberOfNewWords;
				
				} // end of thorough match
				
			} // end of length check
			
      } // end of match of first word only
      
	  
		$i += $iCountStep; // the count step depends on the length of what has (not) been matched at this round
	 
	  
    } // end of loop through results
	
  }
  mysql_free_result($oResult);

  // This goes back to the javascript (response to the ajax request)
  // NOTE any MySQL errors will be printed first...
  print $sPrintResult;
}

// NOTE that, in the multi-type case, any word form that was attested
// already will get the same type as the last one, which is used for
// auto-attesting.
// Usually this is useful, but, particularly in group attestations this could
// cause 'surprising' behaviour, I think...
function insertAutoAttestations($sValues, $iTypeId) {
  // NOTE that the ON DUPLICATE KEY bits don't actually do something
  // Word forms that were attested already remain so, unaltered.
  // the ON DUPLICATE KEY bit just keeps MySQL from complaining when we try to
  // insert something that is already there.
  $sQuery = "INSERT INTO attestations " .
    "(quotationId, onset, offset, reliability, wordForm";
  if( $iTypeId ) // Multiple type case
    $sQuery .=
      ", typeId, dubious, elliptical, error) VALUES $sValues ".
      "ON DUPLICATE KEY UPDATE quotationId = quotationId";
  else // normal case, with useless ON DUPLICATE KEY
    $sQuery .=
      ", dubious, elliptical, error) VALUES $sValues " .
      "ON DUPLICATE KEY UPDATE quotationId = quotationId";

  mysql_query ($sQuery, $GLOBALS['db']);
  print mysql_error();
}

// Auto de-attestation
// This function is called via AJAX, so everything we print is caught via the
// xmlHttp.responseText
function autoDeAttest($iLemmaId, $sWordToBeDeAttested) {

// Get all the quotations
  $sQuery = "SELECT id AS quotationId, tokenizedQuotation " .
    "FROM quotations " .
    "WHERE quotations.lemmaId = $iLemmaId";
  $oResult = mysql_query($sQuery, $GLOBALS['db']);
  echo mysql_error();
  
  
  // how it works:
  // We go through the tokenizedQuotation, in which alle the tokens are to be found as \n-separated tuples
  // like this: onset<TAB>offset<TAB>canonical word form<TAB>printable word form
  // As we want to find a given string, we try to match the first word of the string searched for
  // with the tuples, and as soon as we have a match, we try to match the second word of the string searched for
  // with the tuple that immediately follows. And so on.
  // But doing this, we run the risk that the machted tuple is already part of some other group 
  // so to make sure that we are matching things we are entitled to de-attest, we query the database
  // for the indexes (onsets/offsets) of tokens/groups exactly matching the string searched for
  // if the indexes of the match found previously are not to be found in the results of this query, 
  // that means we are trying to de-attest a wrong token (which must be part of a group which as a 
  // whole is consisting of a different string)
  // so it won't be de-attested.
  
  
  // get the strings and onsets/offsets matching the string to de-attest
  $sQuery2 = "SELECT GROUP_CONCAT(DISTINCT onsetsoffsets ORDER BY onsetsoffsets SEPARATOR '@@@') AS onsetsoffsets FROM ".
	"(SELECT  ".
	"a.id, a.quotationId,  ".
	"GROUP_CONCAT(a.wordForm ORDER BY a.onset ASC SEPARATOR ' ') AS words,  ".
	"GROUP_CONCAT( CONCAT(a.quotationId,':',a.onset, '-', a.`offset`) ORDER BY a.onset ASC SEPARATOR ',') AS onsetsoffsets ".
	"FROM ".
	"attestations a, groupAttestations ga, quotations q ".
	"WHERE ga.attestationId = a.id ".
	"AND q.id = a.quotationId ".
	"AND q.lemmaId = $iLemmaId ".
	"group by ga.id ".
	"union  ".
	"SELECT  ".
	"a.id, a.quotationId, a.wordForm AS words, CONCAT(a.quotationId,':',a.onset, '-', a.`offset`) AS onsetsoffsets ".
	"FROM  ".
	"attestations a, quotations q ".
	"WHERE a.id not in (SELECT attestationId FROM groupAttestations) ".
	"AND q.id = a.quotationId ".
	"AND q.lemmaId = $iLemmaId ".
	") tmp ".
	"WHERE words = '".addslashes(str_replace("@@@", " ", $sWordToBeDeAttested))."'"; 
	
	
	// Put the list of onsets/offsets into an array
	// Further on, when we find a string matching the string we want to de-attest
	// we will look up its onsets/offsets in this array: if it's not there
	// (meaning that the found string is actually part of a bigger or smaller group)
	// we won't include it the list of token to carry on the de-attestion with
	$oResult2 = mysql_query($sQuery2, $GLOBALS['db']);
	echo mysql_error();
	$oRow2 = mysql_fetch_assoc ($oResult2);
	$aListOfOnsetOffsets = split("@@@", $oRow2['onsetsoffsets']);
  
  
  $sPrintResult = '';
  $sPrintGroupSeparator = '';
  
  // if we have more than one word, get the first word 
  // and also the number of expected words
  $sFirstToBeDeAttested = $sWordToBeDeAttested;
  $aCollectionOfNewWord = split("@@@", $sWordToBeDeAttested);
  $sWordToBeDeAttested = str_replace("@@@", " ", $sWordToBeDeAttested);
  $iNumberOfNewWords = count($aCollectionOfNewWord);
  if ( $iNumberOfNewWords > 1 )
	$sFirstToBeDeAttested = $aCollectionOfNewWord[0];
	
  
  while( $oRow = mysql_fetch_assoc ($oResult) ) {
    
    // Split the words in the quotation.
    // The words come in tuples:
    // onset<TAB>offset<TAB>canonical word form<TAB>printable word form
    $aWordTuples = split("\n", $oRow['tokenizedQuotation']);
	
	$sQuotationId = $oRow['quotationId'];

    $i = 0;
	
	while ( $i<count($aWordTuples) ) {
	
	  $iCountStep = 1;
	
	  // get current tuple
	  $sWordTuple = $aWordTuples[$i];
	
      $aWordTuple = split("\t", $sWordTuple);
      // We take $aWordTuple[2] which is the canonical word form
      // We utf8_encode the forms to look them up in the array
      // The unencoded form is used to put in the database, which will encode
      // it right itself.
	  
      if( $aWordTuple[2] == $sFirstToBeDeAttested) {
		
		// at this point, we have a match for the first word of the group searched for
		// now try to match the whole group
		if (  ($i + $iNumberOfNewWords) < count($aWordTuples) )
			{
			
			// first build the whole string to have to match with, from the content of the database
			// (we do that for words, but also for onsets
			$strToMatch = $sFirstToBeDeAttested;
			$onsetsStrToMatch = $sQuotationId.":".$aWordTuple[0]."-".$aWordTuple[1];
			for ($j = $i+1; $j < ($i + $iNumberOfNewWords); $j++)
				{
				$sWordTuple = $aWordTuples[$j];	
				$aWordTuple = split("\t", $sWordTuple);
				$strToMatch .= " ". $aWordTuple[2];
				$onsetsStrToMatch .= ",". $sQuotationId.":".$aWordTuple[0]."-".$aWordTuple[1];
				}
			
			// do both strings match thoroughly?
			if ($sWordToBeDeAttested == $strToMatch && in_array($onsetsStrToMatch, $aListOfOnsetOffsets) )
				{
				
				// gather the group members:
				// later on, the javascript will process this response to remove those groups
				// (js-function buildGroupsInAutoAttest)
				for ($j = $i; $j < ($i + $iNumberOfNewWords); $j++)
					{
					$sPrintResult .= $sPrintGroupSeparator . "att_" . $oRow['quotationId'] . '_' . $j;
					$sPrintGroupSeparator = ",";
					}
					
					
				$iCountStep = $iNumberOfNewWords;
				
				} // end of thorough match
				
			} // end of length check
			
      } // end of match of first word only
      
	  
		$i += $iCountStep; // the count step depends on the length of what has (not) been matched at this round
	 
	  
    } // end of loop through results
	
  }
  mysql_free_result($oResult);
  
  // This goes back to the javascript (response to the ajax request)
  // NOTE any MySQL errors will be printed first.
  print $sPrintResult;

}


function deleteAutoAttesations($sDeleteCondition) {
  $sDeleteQuery = "DELETE FROM attestations WHERE $sDeleteCondition";
  mysql_query($sDeleteQuery, $GLOBALS['db']);
  print mysql_error();
}

function setCurrentAttEllipsis($iQuotationId, $iOnset, $bNewElliptical) {
  $sQuery = "UPDATE attestations SET elliptical = $bNewElliptical " .
    "WHERE quotationId = $iQuotationId AND onset = $iOnset";
  mysql_query($sQuery, $GLOBALS['db']);
  print mysql_error();
}

function setCurrentAttError($iQuotationId, $iOnset, $bNewError) {
  $sQuery = "UPDATE attestations SET error = $bNewError " .
    "WHERE quotationId = $iQuotationId AND onset = $iOnset";
  mysql_query($sQuery, $GLOBALS['db']);
  print mysql_error();
}

function setCurrentAttDubious($iQuotationId, $iOnset, $bNewDubious) {
  $sQuery = "UPDATE attestations SET dubious = $bNewDubious " .
    "WHERE quotationId = $iQuotationId AND onset = $iOnset";
  mysql_query($sQuery, $GLOBALS['db']);
  print mysql_error();
}

// Print the word forms that have been attested so far
//
function attestedSoFar($iLemmaId) {
  // First, the reload button
  
  $hWordForms = array();

  // Get the wordform groups
  $sQuery =
    "SELECT GROUP_CONCAT(attestations.id) attestationIds," .
    "       CONCAT(GROUP_CONCAT(attestations.wordForm ORDER BY pos SEPARATOR ' '), ' (', if(name='comment', '', name), GROUP_CONCAT(comment SEPARATOR ''),')')" .
    "         wordFormGroups" .
    "  FROM groupAttestations, attestations, quotations, types" .
    " WHERE quotations.lemmaId = $iLemmaId" .
    "   AND attestations.quotationId = quotations.id" .
    "   AND groupAttestations.attestationId = attestations.id" .
	"   AND types.id = typeId" .
    " GROUP BY groupAttestations.id";
  $sAttestationIdsInGroups = '';
  $sComma = '';
  $oResult = mysql_query($sQuery, $GLOBALS['db']);
  if( $oResult ) {
    while( $oRow = mysql_fetch_assoc($oResult) ) {
      $hWordForms[$oRow['wordFormGroups']] = 1; # Keep them unique
      $sAttestationIdsInGroups .= $sComma . $oRow['attestationIds'];
      $sComma = ', ';
    }
    mysql_free_result($oResult);
  }
 
  // 2011-09-09 New query, we go through them one by one because we sort them
  // here in PHP (together with the word form groups of the previous step).
  $sQuery =
    "SELECT CONCAT(wordForm,  if(name='default', '', CONCAT(' (', if(name='comment', '', name), comment,')') ) ) wordForm" .
    "  FROM quotations, attestations, types" .
    " WHERE quotations.lemmaId = $iLemmaId" .
    "   AND attestations.quotationId = quotations.id" .
	"   AND types.id = typeId";
  if( strlen($sAttestationIdsInGroups) )
    $sQuery .= "   AND attestations.id NOT IN ($sAttestationIdsInGroups)";
  $sQuery .= " GROUP BY wordForm";
  

  $oResult = mysql_query($sQuery, $GLOBALS['db']);
  if( $oResult ) {
    while( $oRow = mysql_fetch_assoc($oResult) )
  
      $hWordForms[$oRow['wordForm']] = 1; // Add to hash
    mysql_free_result($oResult);
  }

  // Now that we have them all, sort the hash and return
  // We have to store this in a separate array first as sort sorts the array
  // itself.
  $aWordForms = array_keys($hWordForms); 
  // We use our own sorting function as PHP string sort sorts case dependant and
  // you will end up with: 'Mies, 'Noot', 'aap'
  // (i.e. lowercase comeas after uppercase...).
  // NOTE that the function is actually not user defined but another PHP
  // built in one.
  usort($aWordForms, 'strcasecmp');

  return implode(', ', $aWordForms);
}

// Very simple help function for the background color
// The reliability in our case ranges from 0 to 1000. 0.5 is the lowest
// score above zero and completely absurd quotes get a score of 1000.
// Usually though, it is something between 0.5 and 10.
function getBackgroundColor($iReliability) {
  if( $iReliability <= 0) return 'E0EFE0'; // <- Default, for 100% match
  if( $iReliability <= 2) return 'EFE8E8';
  if( $iReliability <= 4) return 'EFE0E0';
  if( $iReliability <= 6) return 'EFD8D8';
  if( $iReliability <= 8) return 'EFD0D0';
  return 'EFC8C8';
}

// Get background colors for different types.
// Give back some Javascript to print.
function getBackgroundColorInfo($sDatabase) {
  // First check if the types table is there at all.
  // If it's not we are running in the 'one type = no type' attestation mode
  // so to say.
  $sQuery = "SHOW TABLES WHERE Tables_in_$sDatabase = 'types'";
  $oResult = mysql_query($sQuery, $GLOBALS['db']);
  print mysql_error();

  // The table didn't exist
  if( $oResult && ! mysql_fetch_assoc($oResult))
    return FALSE;

  // NOTE that we make all colors upper case. This makes looking up later on
  // in the aBackgroundColors2TypeId array more straightforward.
  $sQuery = "SELECT id, name, UPPER(color) as color, IFNULL(shortcut, '') shortcut FROM types ORDER BY id";

  $oResult = mysql_query($sQuery, $GLOBALS['db']);

  print mysql_error();

  $sJavascriptArrayDeclaration1 = 
    " var aBackgroundColors = new Array(); var hKeyToSelectedrowindex = new Array(); \n";
  $sJavascriptArrayDeclaration2 =
    " var aBackgroundColors2TypeId = new Array();\n";
  
  if( $oResult ) {
    // NOTE that it is an associative array, even while the indexes are digits.
    $iArrayLength = 0;
    while( $oRow = mysql_fetch_assoc($oResult) ) {
      $sJavascriptArrayDeclaration1 .=
	" aBackgroundColors['" . $oRow['id'] . "'] = new Array('" .
	$oRow['name'] . "', '" . $oRow['color'] . "', '" . strtolower($oRow['shortcut']) . "');\n";
      $sJavascriptArrayDeclaration1 .=
	  " hKeyToSelectedrowindex['" . strtolower($oRow['shortcut']) . "'] = " . $iArrayLength . ";\n";
	
      $sJavascriptArrayDeclaration2 .= " aBackgroundColors2TypeId['" .
	$oRow['color'] . "'] = " . $oRow['id'] . ";\n";
      $iArrayLength++;
    }
    // The following line is a pretty lame idiocy. Javascript doesn't really
    // support array length with associative arrays. However, you can set any
    // objects attribute yourself.
    // NOTE that we double the length, because since there are no associative
    // arrays, the array actually counts keys and values alike.
    $sJavascriptArrayDeclaration1 .=
      "aBackgroundColors.length = " . ($iArrayLength * 2) . ";\n";
    mysql_free_result($oResult);
  }

  return "$sJavascriptArrayDeclaration1\n$sJavascriptArrayDeclaration2";
}

// Get background colors for different types.
// More or less the same as the one above, but called by getAttestations.php
function getBackgroundColors($sDatabase) {
  // First check if the types table is there at all.
  // If it's not we are running in the 'one type = no type' attestation mode
  // so to say.
  $sQuery = "SHOW TABLES WHERE Tables_in_$sDatabase = 'types'";
  $oResult = mysql_query($sQuery, $GLOBALS['db']);
  print mysql_error();

  // The table didn't exist
  if( $oResult && ! mysql_fetch_assoc($oResult))
    return FALSE;

  // It did exist
  mysql_free_result($oResult);
  $sQuery = "SELECT id, color FROM types ORDER BY id";

  $oResult = mysql_query($sQuery, $GLOBALS['db']);

  print mysql_error();

  $aBackgroundColors = array();
  if( $oResult ) {
    // NOTE that it is an associative array, even if the indexes are digits.
    while( $oRow = mysql_fetch_assoc($oResult) ) {
      $aBackgroundColors["'" . $oRow['id'] . "'"] = $oRow['color'];
    }
    mysql_free_result($oResult);
  }

  return $aBackgroundColors;
}

function splitToken($sSplitToken, $iQuotationId, $iOnset, $bDubiosity,
		    $bElliptical, $bError, $iTypeId) {
  printLog("Start splitToken('$sSplitToken', $iQuotationId, $iOnset, " .
	   "$bDubiosity, $bElliptical, $bError, $iTypeId)");
  $sQuery = "SELECT tokenizedQuotation FROM quotations " .
    "WHERE id = $iQuotationId";
  $oSelectResult = mysql_query($sQuery, $GLOBALS['db']);
  printLog("Select: $sQuery");
  print mysql_error();
  if( $oSelectResult ) {
    if( $oRow = mysql_fetch_assoc($oSelectResult) ) {
      // NOTE that the tokenized quotation is like this:
      // onset<TAB>offset<TAB>canonical form<TAB>word form
      if( preg_match("/^(.+\n)?$iOnset\t\d+\t[^\t]+\t[^\n]+(\n.+)?$/s",
		     $oRow['tokenizedQuotation'], $aMatches)) {	
	# Prefix is the entire file from start till this token
	$sPrefix = (isset($aMatches[1])) ? addslashes($aMatches[1]) : '';
	# Postfix is the entire file after the token
	$sPostfix = (isset($aMatches[2])) ? addslashes($aMatches[2]) : '';
	$aNewTokens = explode(' ', $sSplitToken);
	$aNewTokenized = array();
	$aNewAttestations = array();
	$aNewAttestationOnsets = array();
	$iNewOnset = $iOnset;
	$sNewAttestation = '';
	for($i = 0; $i < sizeof($aNewTokens); $i++ ) {
	  $iLength = strlen($aNewTokens[$i]);
	  $iNewOffset = $iNewOnset + $iLength;
	  $sUtf8DecodedToken = $aNewTokens[$i];
	  $sCanonicalForm =
	    toCanonicalForm($sUtf8DecodedToken, $iNewOnset, $iNewOffset);
	  array_push($aNewTokenized,
		     "$iNewOnset\t$iNewOffset\t" . addslashes($sCanonicalForm).
		     "\t" . addslashes($sUtf8DecodedToken));
	  // First one is the reliabiliy, which is zero, because the user does this
	  $sNewAttestation = "(0, $iQuotationId, $iNewOnset, $iNewOffset, ".
	    "$bDubiosity, $bElliptical, $bError, '" .
	    addslashes($sUtf8DecodedToken). "'";
	  if( $iTypeId ) // If we are in multi-types mode
	    $sNewAttestation .= ", $iTypeId";
	  array_push($aNewAttestations, "$sNewAttestation)");
	  array_push($aNewAttestationOnsets, $iNewOnset);
	  $iNewOnset += $iLength;
	}
	// Update the quotations table
	$sUpdateQuery = "UPDATE quotations SET tokenizedQuotation = '$sPrefix"
	  . join("\n", $aNewTokenized) . "$sPostfix' " .
	  "WHERE id = $iQuotationId";
	printLog("First update query: $sUpdateQuery");
	mysql_query($sUpdateQuery, $GLOBALS['db']);
	print mysql_error();
	
	// First see if this attestation is in any grouped attestations, and if
	// so, delete it, and remember its id and position
	$sSelectQuery = "SELECT attestations.id AS attestationId, " .
	  "groupAttestations.id as groupAttestationId, pos " .
	  "FROM groupAttestations, attestations ".
	  "WHERE groupAttestations.attestationId = attestations.id" .
	  "  AND attestations.quotationId = $iQuotationId" .
	  "  AND attestations.onset = $iOnset";
	$iGroupAttestationPos = -1;
	$iGroupAttestationId = 0;
	$oResult = mysql_query($sSelectQuery, $GLOBALS['db']);
	print mysql_error();
	if( $oResult ) {
	  if( $oRow = mysql_fetch_assoc($oResult) ) {
	    $iGroupAttestationId = $oRow['groupAttestationId'];
	    $iGroupAttestationPos = $oRow['pos'];

	    // Throw any group attestation out this attestation features in
	    $sDeleteQuery = "DELETE FROM groupAttestations " .
	      "WHERE attestationId = " . $oRow['attestationId'];
	    printLog("Delete: $sDeleteQuery");
	    mysql_query($sDeleteQuery, $GLOBALS['db']);
	    print mysql_error();
	  }
	  mysql_free_result($oResult);
	}
	else
	  return;

	// Throw the old attestation out
	$sDeleteQuery = "DELETE FROM attestations " .
	  "WHERE quotationId = $iQuotationId AND onset = $iOnset";
	printLog("Delete: $sDeleteQuery");
	mysql_query($sDeleteQuery, $GLOBALS['db']);
	print mysql_error();

	// Put in the new values
	$sInsertQuery = "INSERT INTO attestations " .
	  "(reliability, quotationId, onset, offset, dubious, elliptical, " .
	  "error, wordForm";
	if( $iTypeId ) // In case we're in multi-types mode
	  $sInsertQuery .= ", typeId";
	$sInsertQuery .= ") VALUES " .
	  join(",", $aNewAttestations);
	printLog("Insert: $sInsertQuery");
	mysql_query($sInsertQuery, $GLOBALS['db']);
	print mysql_error();

	// Update all positions of group attestations after this one, as
	// they are all one too low, now that we have added a token.
	$sUpdateQuery = "UPDATE groupAttestations, attestations " .
	  "SET pos = pos + " . (count($aNewAttestations)-1) . " " .
	  "WHERE groupAttestations.attestationId = attestations.id" .
	  "  AND attestations.quotationId = $iQuotationId" .
	  "  AND onset > $iOnset";

	printLog("Update: $sUpdateQuery");
	mysql_query($sUpdateQuery, $GLOBALS['db']);
	print mysql_error();

	// Insert the group attestations if the attestation was in one
	if( $iGroupAttestationId ) {
	  // Get the id's of the new attestations (bit of a pity we have to do
	  // this in yet another query again)
	  $sSelectQuery = "SELECT id FROM attestations " .
	    "WHERE quotationId = $iQuotationId AND onset IN (" .
	    join(',', $aNewAttestationOnsets) . ") ORDER BY id";
	  printLog("Select: $sSelectQuery");
	  $oResult = mysql_query($sSelectQuery, $GLOBALS['db']);
	  print mysql_error();
	  $aNewValues = array();
	  if( $oResult ) {
	    while( $oRow = mysql_fetch_assoc($oResult) ) {
	      array_push($aNewValues,
			 "($iGroupAttestationId, " . $oRow['id'] .
			 ", $iGroupAttestationPos)");
	      // NOTE that we increment the position here (which is used for
	      // the update query later on as well)
	      $iGroupAttestationPos++;
	    }
	    $sInsertQuery = "INSERT INTO groupAttestations " .
	      "(id, attestationId, pos) VALUES " . join(',', $aNewValues);
	    printLog("Insert: $sInsertQuery");
	    mysql_query($sInsertQuery, $GLOBALS['db']);
	    print mysql_error();

	    mysql_free_result($oResult);
	  }
	}
      }
    }
    mysql_free_result($oSelectResult);
  }
  printLog("End splitToken.");
}

function externalLemmaId2link($sExternalLemmaId) {
  
  $keyExists = array_key_exists($GLOBALS['sDatabase'], $GLOBALS['sExternalDictionary']);
  
  // display error message if the current database wasn't assigned a dictionary location
  if ( !$keyExists) 
  {
	print '<B>ERROR: the current database name wasn\'t set in the sExternalDictionary hash (check globals.php)</B>';
	return;
  }

  $sLink = "<a href='" . $GLOBALS['sExternalDictionary'][$GLOBALS['sDatabase']];
  if( preg_match("/^(\d+)\.(\d+)$/", $sExternalLemmaId, $aMatches) ) {
    $sLink =  str_replace("<ID>", $aMatches[1] . "#eid" . $aMatches[2], $sLink);
  }
  else {
    $sLink =  str_replace("<ID>", $sExternalLemmaId, $sLink);
  }
  return "$sLink' target='_blank' " .
    "onMouseOver='javascript: changeOEDIcon(\"_\");' " .
    "onMouseOut='javascript: changeOEDIcon(\"\");' " .
    "><img name=oedIcon src='./images/OED_icon.png' border=0></a>";
}


// Canonical form is:
// - lowercase
// - no <TAGS>
// - no comma's (,), dots (.) or semi-colons (;)
function toCanonicalForm($sString, &$iOnset, &$iOffset) {
  $sCanonicalForm = strtolower($sString);
  
  if( preg_match("/^((<[^>]+>|[\.\,;])*)(.+?)((<[^>]+>|[\.\,;])*)$/",
		 $sCanonicalForm, $aMatches) ) {
    $sCanonicalForm = $aMatches[3];
    if( isset($aMatches[1]) )
      $iOnset += strlen($aMatches[1]);
    if( isset($aMatches[4]) )
      $iOffset -= strlen($aMatches[4]);
	// Quick and dirty trick:
    // We add the period again if the the word was an initial
    if((strlen($sCanonicalForm) == 1) && (substr($aMatches[4], 0, 1) == '.')) {
      $sCanonicalForm .= '.';
      $iOffset++;
    }
  }

  return $sCanonicalForm;

}



// see: http://stackoverflow.com/questions/834303/php-startswith-and-endswith-functions
function startsWith($haystack, $needle)
{
    return !strncmp($haystack, $needle, strlen($needle));
}
function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function printLog($sString) {

if( $GLOBALS['sLogFile'] ) {  
    $fh = fopen($GLOBALS['sLogFile'], 'a');
    // Next line is there because PHP version 5.3 and up require it.
    // You are supposed to also be able to set it in php.ini, but it
    // doesn't work.
    date_default_timezone_set("Europe/Amsterdam");
    fwrite($fh, date("\n-----\n\nY-m-d H:i:s\n") . "\t" . $sString);
    fclose($fh);
   }
}

?>