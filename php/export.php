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


// MAIN

getGroupAttestations();
getLemmata();
zipItAll();

// END MAIN





function getLemmata()
{
   $qhQuery = mysql_query("SELECT * from lemmata");
   echo mysql_error();

   while ($hrRow = mysql_fetch_assoc($qhQuery))
   {
     $name = $hrRow['lemma'];
     $id = $hrRow['id'];
     getQuotations($name, $id);
  }
}

function getQuotations($name, $lemmaId)
{
   $qhQuery = mysql_query("SELECT * from quotations where lemmaId='".$lemmaId."'");
   echo mysql_error();

   while ($hrRow = mysql_fetch_assoc($qhQuery))   
   {
     $quotation = $hrRow['quotation'];
     $quotation = utf8_decode($quotation);
     $id = $hrRow['id'];
     getAttestations($name, $id, $quotation);
  }
}


function zipItAll(){

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

}





function getAttestations($name, $quotationId, $quotation)
{
   $onset2string = buildIdHash($quotation);
 
   
   $qhQuery = mysql_query("SELECT attestations.id, attestations.onset, attestations.wordForm, types.name from attestations,types where quotationId='".$quotationId."' and types.id=attestations.typeId order by attestations.onset");
   echo mysql_error();
   
   $id2type = array();
   while ($hrRow = mysql_fetch_assoc($qhQuery))
   {
     
     $type = $hrRow['name'];
     $id = $hrRow['id'];
     $position = array_key_exists($id, $GLOBALS['groupPosHash']) ? $GLOBALS['groupPosHash'][$id] : false;
     $prefix = ($position==0)?"B-":"I-";
     $stringId  = $onset2string[$hrRow['onset']];
     $id2type[$stringId] = $prefix . $type;
  }
  
  
  $taggedQuotation="";
  foreach (preg_split("/\n/", $quotation) as $x)
  {
    # if we have some string tags
	if (strpos( strtolower($x),'<string') !== false)
	{
		preg_match_all("|(<string[^\>]+>)|i", $x, $matches);
		
		for ($i = 0; $i < count($matches); $i++){
			$oneMatch = $matches[$i][0];
			$x = str_replace($oneMatch, setNeType($oneMatch, $id2type), $x);
		};
	}	
    $taggedQuotation .= $x . "\n";
  }
  
  # ToDo: save quotation somewhere
  
  # write a file with UTF-8 encoding
  # (see: http://stackoverflow.com/questions/3532877/problem-writing-utf-8-encoded-file-in-php)
  
  # filename
  $name = str_replace(".xml_1", ".xml", $name); # remove _1 part (where does this come from?)
  $GLOBALS['aExportedFileNames'][$GLOBALS['fileCounter']] = $name;
  
  # file path
  $sCurrentPathAndFileName = $GLOBALS['sFilePathForExport'].$name; 
  $GLOBALS['aExportedPathsAndFiles'][$GLOBALS['fileCounter']] = $sCurrentPathAndFileName;
  
  # create file
  $fh = fopen($sCurrentPathAndFileName, 'wb');  
  $sCurrentPathAndFileName = "\xEF\xBB\xBF".$taggedQuotation; // utf8 bom
  fputs($fh, $taggedQuotation);
  fclose($fh);  
  
  $GLOBALS['fileCounter']++;  
}

function setNeType($string, $hash) {
	  $id = getAttribute($string,"ID");    
	  
	  if ( !array_key_exists($id, $hash))
		return $string;
	
	  $type = $hash[$id];
	  
	  $string = preg_replace("/\s+alternative=[\"'](.*?)[\"']/i", "", $string);

	  if ($type)
	  {
		$string = preg_replace("/(\<string)/i", "$1 ALTERNATIVE='".$type."'", $string);    
	  } 

	  return $string;
}
  

function buildIdHash($text)
{
  $idHash = array();
  preg_match_all("|<string[^\>]+>|i", $text, $matches, PREG_OFFSET_CAPTURE);
  $matches = $matches[0];
 
  if ( count($matches) > 0 )
  {
  $i = 0;
  
  while ($i < count($matches) )
	{	
	
	$tag = $matches[$i][0];
	
    $sWordForm = getAttribute($tag,"CONTENT");
    $sCurrentTag = getAttribute($tag,"ALTERNATIVE"); 
    $sTokenId =  getAttribute($tag,"ID");
    
    $iOnset = $matches[$i][1]; 
    $idHash[$iOnset] = $sTokenId;  
	
	$i++;
	}
  }
  
  return $idHash;
}


function getAttribute($tag,$attname)
{
  $attname = strtolower($attname);
  preg_match("/".$attname."=('([^']*)'|\"([^\"]*)\")/i", $tag, $matches);
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