<?php


require_once('./attestationToolBox.php');
chooseDb($_REQUEST['sDatabase']);



// make sure we have the right path format for file export
if ( !endsWith($GLOBALS['sFilePathForExport'], "/") ) 
	$GLOBALS['sFilePathForExport'] = $GLOBALS['sFilePathForExport']."/";
if ( !startsWith($GLOBALS['sFilePathForExport'], "./") )
    $GLOBALS['sFilePathForExport'] = "./".$GLOBALS['sFilePathForExport'];
	
# make the export dir if it doesn't exist yet
if (!file_exists($GLOBALS['sFilePathForExport'])) {
    mkdir($GLOBALS['sFilePathForExport']);
}

# global variables
$sAttestationTag = "oVar";  // will result in <oVar>...</oVar>
// hashes to store document lines and indexes in
$hLineIndex2Content = array();
$hQuotationSectionId2LineIndex = array();
// file counter
$fileCounter = 0;
// comma separated string of unique group id 
$sUniqueGroupIds = '';
// paths and names of files
$aTempPathsAndFiles = array();    // paths+names of temp files chosen for export
$aExportedPathsAndFiles = array();// paths+names of export files
$aExportedFileNames = array();    // names of export files


// MAIN

// if some file has been selected already, run the export script
if( isset($_REQUEST['bFileSelected']) )
	{
	
	print '<br>Start exporting...<br>';
	
	//getIdsOfUniqueGroups();
	getFileToBaseExportUpon();
	putFileInHash( $aTempPathsAndFiles[0] );
	processAllQuotations();
	writeExportFile();
	//zipItAll();
	}
	
// otherwise, ask the user to select a file
else
	{
	print 'Select the MNW-file to export the annotations to:<br>';
	print '<form enctype="multipart/form-data" action="export2mnw.php?bFileSelected=true&sDatabase='.$_REQUEST['sDatabase'].'" method="POST">'.
			
			'<input type="file" name="file[]" multiple />'.
			'<input type="submit" value="Submit">'.
			'</form>';
	}

// END MAIN




// read the file name and its path
function getFileToBaseExportUpon(){

	print '<br>Get file to base export upon...<br>';
	flush();

	$sOriginalFileName = $_FILES['file']['name'][0];
	$sTempFilePath = $_FILES['file']['tmp_name'][0];
	
	$GLOBALS['aExportedFileNames'][$GLOBALS['fileCounter']] = $sOriginalFileName;
	$GLOBALS['aTempPathsAndFiles'][$GLOBALS['fileCounter']] = $sTempFilePath;
	
	$GLOBALS['fileCounter']++;
	
	print 'Done.<br>';
	flush();
}



function convert($size)
 {
    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
 }
 

// process all quotations:
//
//	gather DB-quotations data
//	loop through DB-quotationIds
//		get DB-quotationSectionId and DB-quotation (remove &ldquo; / &rdquo; and all spaces from quotation)
//      get the <cite> having the same quotationSectionId
//			remove all spaces from <cite>-quotation string
//			check if this equals the DB-quotation
//				if it does:
//					add the tags to the <cite>-quotation string
function processAllQuotations(){

	print '<br>Process all quotations...<br>';
	flush();

	$countQuery = mysql_query("SELECT COUNT(*) AS nrOfRows FROM quotations;");
	echo mysql_error();
	while ($hrRow = mysql_fetch_assoc($countQuery))   
   {
	$numberOfRows = $hrRow['nrOfRows'];
	break;
   }
   // free memory
	mysql_free_result($countQuery);
	

	$qhQuery = mysql_query("SELECT id FROM quotations;");
   echo mysql_error();
   
   $flushCounter = 0;
   
   while ($hrRow = mysql_fetch_assoc($qhQuery))   
   {   
		$sQuotationId = $hrRow['id'];
		
		$qhSubQuery = mysql_query("SELECT id, quotation, quotationSectionId FROM quotations WHERE id = $sQuotationId;");
		echo mysql_error();
		
		$hrSubRow = mysql_fetch_assoc($qhSubQuery);
		
		$sDatabaseQuotation = $hrSubRow['quotation'];
	   $sQuotationSectionId = $hrSubRow['quotationSectionId'];
	   
	   // free memory
	   mysql_free_result($qhSubQuery);
	   
	   
   
		// show progress in title bar
		$flushCounter++;
		
		$iProgress = intval(100*($flushCounter / $numberOfRows));
		print "<script type=\"text/javascript\">".
			"document.title = '".$iProgress."% (".convert(memory_get_usage(true)).")';".
			"</script>";		
		flush();
	   
	   
	   // strip the 'database quotation'  (to be able to match it with the 'xml file quotation')
	   $sStrippedDatabaseQuotation = preg_replace("/(&.\w+?;|\s|[^a-zA-Z])/", "", $sDatabaseQuotation);
	   
	   
	   // now get the corresponding quotation from the xml file
	   
	   // if quotationSectionId is unknown, skip this one
	   if ( !array_key_exists($sQuotationSectionId, $GLOBALS['hQuotationSectionId2LineIndex']) )
		continue;
	   
	   // else do the job!
	   $sLinesWhereQuotationsCanBeFound = $GLOBALS['hQuotationSectionId2LineIndex'][$sQuotationSectionId];
	   $aLinesWhereQuotationsCanBeFound = explode("|", $sLinesWhereQuotationsCanBeFound);
	   
	   
	   foreach ($aLinesWhereQuotationsCanBeFound as $sOneLineIndex)
		   {
		   $iOneLineIndex = intval($sOneLineIndex);
		   
		   // get the xml file content corresponding to this line number
		   $sFileQuotation = $GLOBALS['hLineIndex2Content'][$iOneLineIndex];
		   
		   // extract the quotation 
		   $sIsolatedFileQuotation = preg_replace("|^.*<cite.*?>(.+?)</cite>.*$|", "$1", $sFileQuotation);
		   
		   // remove the tags inside it and strip it 
		   // (we need to do that to be able to compare it with database version, which we also stripped)
		   $sStrippedFileQuotation = strip_tags($sIsolatedFileQuotation);
		   $sStrippedFileQuotation = preg_replace("/(&\w+?;|\s|[^a-zA-Z])/", "", $sStrippedFileQuotation);
		  
		   
		   // if the database quotation is the same as the file quotation, we are at the right place
		   // so put the attestation tags as required.
		   // we'll do that in the file quotation, which still contains some tags which are gone in the database version
		   
		   
		   if ($sStrippedDatabaseQuotation == $sStrippedFileQuotation)
				{			
				$sQuotationWithTags = putTagsIntoQuotation($sQuotationId, $sIsolatedFileQuotation);
				
				// beware, putting this into one single preg_replace makes this not reliable, 
				// this is why the replacement is split in two steps here 
				// see: 
				// http://www.sitepoint.com/forums/showthread.php?766451-preg_replace-remove-my-first-digit-for-no-reason-%28
				$sPrefix = preg_replace("|^(.*)(<cite.*?>)(.+?)(</cite>)(.*)$|", "$1$2", $sFileQuotation);
				$sSuffix = preg_replace("|^(.*)(<cite.*?>)(.+?)(</cite>)(.*)$|", "$4$5", $sFileQuotation);
				$GLOBALS['hLineIndex2Content'][$iOneLineIndex] = $sPrefix.$sQuotationWithTags.$sSuffix;
				}
			
		   }
   }

   print 'Done.<br>';
	flush();
}




// put each line of the xml file into a hash mapping line index tot line content
// and put found quotationSectionIds in a hash mapping quotationSectionId to line index
// we will use those hashes to be able to find the lines corresponding to the
// quotations stored in the database
function putFileInHash($sFileName){

	print '<br>Put files in hash...<br>';
	flush();

	$sFileContent = readMnwFile($sFileName);
	
	$iCounter = 0;
	
	// Make sure we won't have more <cite>-tags per line
	// This is necessary since some quotationSectionIds are double, and also in the same line
	// We fix that by differentiating those by line number!
	$sFileContent = preg_replace("|(</cite>)|", "$1\n", $sFileContent);
	
	// now split the file by \n and store everything in hashes
	
	foreach (preg_split("/\n/", $sFileContent) as $sOneLine)
	   {
	   // put lines content in hash (from linenr tot content)
	   $GLOBALS['hLineIndex2Content'][$iCounter] = $sOneLine;
	   	   
	   // parse quotationSectionId out of line and put it into hash
	   // from quotationSectionId to linenr
	   
	   preg_match('/<cite.*? id="(.+?)"/', $sOneLine, $matches);
	   $sQuotationSectionId = '';
	   if ( count($matches) > 0 )
			{			
			$sQuotationSectionId = $matches[1];
			
			$GLOBALS['hQuotationSectionId2LineIndex'][$sQuotationSectionId] = 
			(array_key_exists($sQuotationSectionId, $GLOBALS['hQuotationSectionId2LineIndex']) ? 
			 $GLOBALS['hQuotationSectionId2LineIndex'][$sQuotationSectionId]."|" : "").
			 $iCounter;
			}
	   
	   $iCounter++;
	   
	   }
	   
	print 'Done.<br>';
	flush();
}

// subroutine of putFileInHash()
function readMnwFile($sFileName){

	$fh = fopen($sFileName, "r");
	
	if( $fh === FALSE ) 
		{
		print "COULD NOT OPEN ".$sFileName."\n";
		return false;
		}
		
	$sFileContent = fread($fh, filesize($sFileName));
	fclose($fh);
	
	return $sFileContent;
}



// put attestation tags into the quotations;
// when doing that, we need to pay attention to entities, as the xml file has those, while the database does not.
function putTagsIntoQuotation($sQuotationId, $sQuotation){


	$sBeginningAttTag = "<".$GLOBALS['sAttestationTag'];
	
	if (strpos($sQuotation, $sBeginningAttTag)>-1)
		return $sQuotation;

	$qhQuery = mysql_query("SELECT DISTINCT q.quotation, att.onset, att.offset, grpatt.id AS group_id, att.comment AS lemmatization ".
			"FROM quotations AS q, attestations AS att ".
			"LEFT JOIN groupAttestations AS grpatt ".
			"ON att.id = grpatt.attestationId ".
			"WHERE att.quotationId = ".$sQuotationId." ".
			"AND q.id = att.quotationId ".
			"ORDER BY onset DESC;");
   echo mysql_error();

   
   
   $aAnnotationsOnsets = array();
   $aAnnotationsOffsets = array();
   $aAnnotationsGroupIds = array();
   $aLemmatizations = array();
   
   // loop through the onsets/offsets pairs etc.
   // store them in an array
   while ($hrRow = mysql_fetch_assoc($qhQuery)) 
   {   
	   $iCounter = count($aAnnotationsOnsets); // grows as we add new rows each cycle
	   
	   $aAnnotationsOnsets[$iCounter] = intval($hrRow['onset']);
	   $aAnnotationsOffsets[$iCounter] = intval($hrRow['offset']);
	   $aAnnotationsGroupIds[$iCounter] = $hrRow['group_id'];
	   $aLemmatizations[$iCounter] = $hrRow['lemmatization'];
   }
   
   // free memory
   mysql_free_result($qhQuery);
   
   // if there are no annotation, leave right away
   if (count($aAnnotationsOnsets) == 0)
	{
	return $sQuotation;
	}
   
	
   
   // Now, since the xml contains html entities while the database does not,
   // we need to first convert the html entities into single dots
   // to be able to put the annotation tags at the right positions (onset en offset)
   // which were computed in string without html entities
   
   // strategy:
   // find all entities, put them in een array mapping from [position in string] to [entity code]
   // then replace the entities by single dots, 
   // convert the string into an array (so we can insert thing into strings without having to deal with indexes changing because of insertions of string parts),
   // put the entities back into place in the array,
   // put the annotations at the right positions in the array,
   // and finally implode the whole back into a string
   
   preg_match_all("|(&[^;]+;)|iu", $sQuotation, $matches, PREG_OFFSET_CAPTURE);
   $entitiesMap = array();
   
   // put entities and their positions into array
   for ($i = 0; $i < count($matches); $i++){
		$matchedEntity = $matches[$i][0][0]; 
		if ($matchedEntity == '') {
			continue;
		}
		$positionInString = intval($matches[$i][0][1]);
		$entitiesMap[ $positionInString ] = $matchedEntity;
   }
   // replace all entities in string by a single dot
   $sQuotation = preg_replace("|(&[^;]+;)|iu", ".", $sQuotation);   
   
   // now convert the string into an array, for easy manipulation
   // (we use the single byte function, as there should be no multi-byte characters in the string;
   //  and the multi-byte implementation is too expensive)
   $sQuotationArr = str_split( $sQuotation );   

   
   // put the entities at their right positions in the string array
   foreach ($entitiesMap as $key => $val){
	   $onset = intval($key);
	   $sQuotationArr[$onset] = $entitiesMap[$key];
   }
   

	// put the annotations at the right positions
   for ($i = 0; $i < count($aAnnotationsOnsets); $i++)
   {
	   $onset = $aAnnotationsOnsets[$i];
	   $offset = $aAnnotationsOffsets[$i];
	   $groupId = $aAnnotationsGroupIds[$i];
	   $lemmatization = $aLemmatizations[$i];
	   
		$sQuotationArr[$onset] = 
		
			"<".$GLOBALS['sAttestationTag'].
			( isset($groupId) ? " group_id=\"".$groupId."\"" : "").
			( isset($lemmatization) && $lemmatization != "" ? " norm=\"".$lemmatization."\"" : "").
			">". $sQuotationArr[$onset];
			
		$sQuotationArr[$offset] =
		
			"</".$GLOBALS['sAttestationTag'].">".$sQuotationArr[$offset];
			
   }
   
   
   // Now the quotation has attestations tags and also its entities back into it   
   // we can convert it back into a string
   $sQuotation = implode($sQuotationArr);
   return $sQuotation;
}




function mb_str_split( $string ) {
    mb_internal_encoding("UTF-8"); // Important
   $chars = array();
   for ($i = 0; $i < mb_strlen($string); $i++ ) {
	$chars[] = mb_substr($string, $i, 1);
   }
   return $chars;
} 


function mb_str_replace($needle, $replacement, $haystack)
{
    $needle_len = mb_strlen($needle);
    $replacement_len = mb_strlen($replacement);
    $pos = mb_strpos($haystack, $needle);
    while ($pos !== false)
    {
        $haystack = mb_substr($haystack, 0, $pos) . $replacement
                . mb_substr($haystack, $pos + $needle_len);
        $pos = mb_strpos($haystack, $needle, $pos + $replacement_len);
    }
    return $haystack;
}

function encode_2_utf8($str)
{
	return mb_convert_encoding($str, "UTF-8");
}





// get a list of id of unique groups
// this is needed since the attestation database sometimes contains duplicate groups
// causing malfunction in tagging later on as tags a put twice or more at the same place.
function getIdsOfUniqueGroups(){

	$qhQuery = mysql_query("SELECT group_concat(id) AS ids ".
		"FROM ".
		"(SELECT id ".
		" FROM ".
		" (SELECT id, group_concat(attestationId ORDER BY attestationId) a, group_concat(pos ORDER BY pos) b ".
		"  FROM groupAttestations ".
		"  GROUP BY id) x ".
		" GROUP BY x.a, x.b) y;");
   echo mysql_error();
   
   while ($hrRow = mysql_fetch_assoc($qhQuery))   
   {
	   $GLOBALS['sUniqueGroupIds'] = $hrRow['ids'];
   }
}


function writeExportFile(){

	print '<br>Write to files...<br>';
	flush();

	for ($i=0; $i<$GLOBALS['fileCounter']; $i++)
		{
		# file name and path
		$sFileName = $GLOBALS['aExportedFileNames'][$i];
		$sCurrentPathAndFileName = $GLOBALS['sFilePathForExport'].$sFileName;     
		$GLOBALS['aExportedPathsAndFiles'][$i] = $sCurrentPathAndFileName;
		
		$taggedQuotation = implode("\n", $GLOBALS['hLineIndex2Content']);
		
		// make sure we won't have empty lines (never more than one \n at once)
		$taggedQuotation = preg_replace("|\n+|", "\n", $taggedQuotation);
		
				
		# write file
		$fh = fopen($sCurrentPathAndFileName, 'wb');  
		$taggedQuotation = "\xEF\xBB\xBF".$taggedQuotation; // utf8 bom
		fputs($fh, $taggedQuotation);
		fclose($fh);
		}
		
	print 'Done.<br>';
	flush();
}



function zipItAll(){

	print '<br>Zip...<br>';
	flush();

	$zip = new ZipArchive();
	
	if ($zip->open($GLOBALS['sFilePathForExport'].$GLOBALS['sZipFileName'], ZipArchive::CREATE) === TRUE) {
	
		$fileNr = 0;
		while( $fileNr < count($GLOBALS['aExportedPathsAndFiles']) )
		{
			$zip->addFile($GLOBALS['aExportedPathsAndFiles'][$fileNr], $GLOBALS['aExportedFileNames'][$fileNr]);
			$fileNr++;
		}
		
		$zip->close();
		
		# eventually do a redirect to the zip location, to enable the user to download the zipfile.
		header('Location: '.$GLOBALS['sFilePathForExport'].$GLOBALS['sZipFileName']);

	} else {
		print 'Export failed. Please contact the system administrator.';
	}
	print 'Done.<br>';
	flush();
}



?>