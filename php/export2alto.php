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
$fileCounter = 1;
$groupPosHash = array();
$aExportedPathsAndFiles = array();
$aExportedFileNames = array();


# in case the export freezes with some file
# we might restart it: if this variable is set to true,
# the export will carry on with files which were not exported yet
# (otherwise it will start all over again, reprocessing the same files from nummer 1)
$bSkipFilesAlreadyExported = true;



// MAIN

// first gather the groups, as we'll need to know which annotation 
// are part of a group or not
getGroupAttestations();

// now do the main job:
// process each lemma (=page) and corresponding quotations
getLemmata();

// at last, put the annotated lemma files into a zip
zipItAll();

// END MAIN




// get all lemmata and their quotations (loop)
function getLemmata()
{
   $qhQuery = mysql_query("SELECT * from lemmata;");
   
   echo mysql_error();

   while ($hrRow = mysql_fetch_assoc($qhQuery))
   {
     $name = $hrRow['lemma'];
	 $name2 = mb_str_replace(".xml_1", ".xml", $name); # remove _1 part
     $id = $hrRow['id'];
	 
	 $sCurrentPathAndFileName = $GLOBALS['sFilePathForExport'].$name2;
	 
	 // if some file was exported already, skip it
	 // (only if the 'bSkipFilesAlreadyExported' setting says we have to)
	 if ($GLOBALS['bSkipFilesAlreadyExported'] == TRUE && file_exists($sCurrentPathAndFileName))
		{
		print 'Skip '.$sCurrentPathAndFileName.' (it was exported earlier)<br>';
		 
		$GLOBALS['aExportedFileNames'][$GLOBALS['fileCounter']] = $name2;
		$GLOBALS['aExportedPathsAndFiles'][$GLOBALS['fileCounter']] = $sCurrentPathAndFileName;   
  
		$GLOBALS['fileCounter']++;  
		
		continue;
		}
	 
     getQuotations($name, $id);
  }
}

// get the quotation of a lemma, given its form and id
function getQuotations($name, $lemmaId)
{

   $qhQuery = mysql_query("SELECT * from quotations where lemmaId='".$lemmaId."';");
   echo mysql_error();

   while ($hrRow = mysql_fetch_assoc($qhQuery))   
   {
	 
	 $quotation = $hrRow['quotation'];
	 
	 // dealing with utf8 bom
	 $onsetCorrection = 0;
     if ($quotation{0} == '?')
		{
		$onsetCorrection = 1;
		$quotation = substr( $quotation, $onsetCorrection );
		}
	 
     $id = $hrRow['id'];
	 
	 // get the attestations of a lemma to be found in a quotation,
	 // given the id of that quotation
     getAttestations($name, $id, $quotation, $onsetCorrection);
  }
}


function zipItAll(){

	$zip = new ZipArchive();
	
	# try op open a new archive file, or fail
	if ($zip->open($GLOBALS['sFilePathForExport'].$GLOBALS['sZipFileName'], ZipArchive::CREATE) === TRUE) {
	
		# add all the export files to the archive
		$fileNr = 1;
		while( $fileNr <= count($GLOBALS['aExportedPathsAndFiles']) )
		{
			$filePath = $GLOBALS['aExportedPathsAndFiles'][$fileNr];
			$localName = $GLOBALS['aExportedFileNames'][$fileNr];
			
			if (!file_exists($filePath)) { die($filePath.' does not exist'); }
			if (!is_readable($filePath)) { die($filePath.' not readable'); }
		
			$zip->addFile($filePath, $localName);
			
			$fileNr++;
		}
		// finished!
		$res = $zip->close();
		if ($res == TRUE) 
			{
			print '<p>Export succeeded';
			}
		else
			{
			var_dump($res);
			}
		
		# eventually do a redirect to the zip location, to enable the user to download the zipfile.
		header('Location: '.$GLOBALS['sFilePathForExport'].$GLOBALS['sZipFileName']);

	} else {
		print 'Export failed. Please contact the system administrator.';
	}

}




// get the attestations of a lemma (name) in a quotation, given the quotation_id
function getAttestations($name, $quotationId, $quotation, $onsetCorrection)
{

   // get a hash mapping offsets of 'string' tags in the XML to their ID attributes
   $onset2string = buildIdHash($quotation, $onsetCorrection);
 
   // get the attestions
   $qhQuery = mysql_query("SELECT attestations.id, attestations.onset, attestations.wordForm, types.name FROM attestations, types WHERE quotationId='".$quotationId."' AND types.id=attestations.typeId ORDER BY attestations.onset");
   echo mysql_error();
   
   $id2type = array();
   while ($hrRow = mysql_fetch_assoc($qhQuery))
   {
     
     $type = $hrRow['name'];
	 
	 // compute the prefix of the annotation ('B-' for begin, or 'I-' for later parts of the annotated entity)
     $id = $hrRow['id'];
	 $position = isset($GLOBALS['groupPosHash'][$id]) ? $GLOBALS['groupPosHash'][$id] : false;
	 
     $prefix = ($position==0)?"B-":"I-";
	 
	 // store the annotations in a hash
	 // mapping onsets of attestations to their annotations
     $stringId  = $onset2string[$hrRow['onset']];
     $id2type[$stringId] = $prefix . $type;
	 
	 
  }
 
  
  $taggedQuotation="";
  foreach (preg_split("/\n/", $quotation) as $x)
  {
  
    # if we have some string tags
	if (mb_strrpos( mb_strtolower($x),'<string') !== false)
	{
		// gather the 'string' tags and add the annotations to them as ALTERNATIVE attributes (setNeType)
		// 'u' modifier needed for utf8, see :
		// http://stackoverflow.com/questions/7675627/multi-byte-function-to-replace-preg-match-all
		preg_match_all("|(<string[^\>]+>)|iu", $x, $matches, PREG_SET_ORDER);
		
		for ($i = 0; $i < count($matches); $i++){
		
			$oneMatch = $matches[$i][0];
			$x = mb_str_replace($oneMatch, setNeType($oneMatch, $id2type), $x);
			
		};
	}
	
    $taggedQuotation .= $x . "\n";
  }
  
  
  
  # write a file with UTF-8 encoding
  # (see: http://stackoverflow.com/questions/3532877/problem-writing-utf-8-encoded-file-in-php)
  
  # filename
  $name = mb_str_replace(".xml_1", ".xml", $name); # remove _1 part 
  $GLOBALS['aExportedFileNames'][$GLOBALS['fileCounter']] = $name;
  
  # file path
  $sCurrentPathAndFileName = $GLOBALS['sFilePathForExport'].$name; 
  $GLOBALS['aExportedPathsAndFiles'][$GLOBALS['fileCounter']] = $sCurrentPathAndFileName;
 
  
  # create file
  $fh = fopen($sCurrentPathAndFileName, 'wb');  
  
  // bom attached after utf8 conversion (see :
  // http://stackoverflow.com/questions/5601904/encoding-a-string-as-utf-8-with-bom-in-php)
  fwrite($fh, chr(239) . chr(187) . chr(191) . $taggedQuotation);
  
  fclose($fh);  
  
  $GLOBALS['fileCounter']++;  
}



// see http://stackoverflow.com/questions/3489495/mb-str-replace-is-slow-any-alternatives
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



// If we have a string containing a 'string' tag   ( <STRING ...> )
// add an attribute to this tag setting the annotation of the named entity it contains
// (the attribute is called ALTERNATIVE)
function setNeType($string, $hash) {

	  // Get the ID attribute value of a given 'string' tag
	  // Note we need to decode the string here, as we also do it elsewhere in this script
	  // when getting the id attribute
	  $id = getAttribute( convertFromMultibyteToSinglebyte($string), $GLOBALS['AltoUniqueIdentifier']);
	  	  
	  // if this ID is unknown, we'll do nothing: return back the unmodified string
	  if ( !array_key_exists($id, $hash))
		return $string;
	
	  // if this ID is known indeed, add the 'ALTERNATIVE=...' attribute to the 'string' tag
	  // (this attribute contains the named entity annotation)
	  $type = $hash[$id];	  
	  $string = preg_replace("/\s+alternative.?=.?[\"'](.*?)[\"']/iu", "", $string);

	  if ($type)
	  {
		// most of the time, the proper quote is " (not ')
		$string = preg_replace("/(\<string)/iu", "$1 ALTERNATIVE=\"".$type."\"", $string);
	  } 
	  
	  return $string;
}


function convertFromMultibyteToSinglebyte($str){

	// get singlebyte version of multibyte
	$newStr = mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
	
	// then look for all NON-ASCII printable characters, and replace those by ?
	// (otherwise some regexp-replace actions on those string will fail)
	// see: http://stackoverflow.com/questions/8781911/remove-non-ascii-characters-from-string-in-php
	$newStr = preg_replace('/[[:^print:]]/', '?', $newStr);
	
	return $newStr;
}


  
// build a hash mapping offsets of 'string' tags in the XML to their ID attributes
function buildIdHash($quotationText, $onsetCorrection)
{
  $idHash = array();
  
  // Here we decode the string, because later on we will need true 'singlebyte' indexes,
  // to be able to match with the indexes stored in the database, which are also singlebyte.
  // Of course, replacing text (=adding annotations) will happen with multibyte string functions instead,
  // as we are dealing with utf8.
  $text = convertFromMultibyteToSinglebyte($quotationText);
  
  preg_match_all("|<string[^\>]+>|i", $text, $matches, PREG_OFFSET_CAPTURE);
  
  $matches = $matches[0];
 
  if ( count($matches) > 0 )
  {
  $i = 0;
  
  while ($i < count($matches) )
	{	
	
	// a match consists of a matched string (index 0) and an offset (index 1)
	$tag = $matches[$i][0];
    $sTokenId =  getAttribute($tag, $GLOBALS['AltoUniqueIdentifier']);
	
	// now, map an offset of a 'string' tag in the XML to its ID attribute
    $iOnset = $matches[$i][1] + $onsetCorrection;
	// (onsetCorrection is needed in cases where some question mark in front of the file was removed, 
	// resulting in wrong onset calculation)
    $idHash[$iOnset] = $sTokenId;  
	
	$i++;
	}
  }
  
  return $idHash;
}


// get the value of an attribute, given the attribute name 
// and some string containing a whole tag with all its attributes ( <TAG ATTR1="..." ATTR2="..."> )
function getAttribute($tag, $attname)
{
  // special case:
  // in some rare cases, we want to be able to get the values of more attributes at once,
  // and concatenate those in a single string, so as to use that as a unique identifier
  // (this is a trick, in case one forgot to put identifiers in the document strings)
  if (is_array($attname))
  {
  $concatValue = '';
  foreach ($attname as &$oneAtt)
	{	
	$concatValue = $concatValue . getAttribute($tag, $oneAtt);
	}
	
  return $concatValue;
  }

  // normale case:
  // get the attribute and its value ( ATTR="..." )
  $attname = mb_strtolower($attname);
  preg_match("/".$attname."=('([^']*)'|\"([^\"]*)\")/iu", $tag, $matches);
  
  // if some value was found, give it back
  if ( count($matches) > 0 )
  {
    $v1 = $matches[2];
    
    if ($v1 != '') { return $v1; };
	$v2 = $matches[3];
    return $v2;
  }
}

function getGroupAttestations()
{
   $oResult= mysql_query("SELECT * from groupAttestations order by id, pos");
   echo mysql_error(); 
   $prevGroup=-1;
   $pos=0;
   
   while ($hrRow = mysql_fetch_assoc($oResult))
   {
     $gid = $hrRow['id'];
     if ($prevGroup != $gid)
     {
       $pos=0;
     }
     $aid = $hrRow['attestationId'];
     $GLOBALS['groupPosHash'][$aid] = $pos;
	
	 
     $pos++;
     $prevGroup = $gid;
  }
  
}
?>