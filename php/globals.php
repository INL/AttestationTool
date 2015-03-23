<?php

// Database login

$sDbHost = "your_host.inl.nl";
$sDbUser = "your_username";
$sDbPassWord = "your_password";


// Projects list

$asProject['database_name1'] = 'Project description number 1';
$asProject['database_name2'] = 'Project description number 2';
$asProject['yet_another_database_name'] = 'Yet another project description';


// Set here the project that must be selected by default upon startup
// (set to NULL if none should be selected)

$sChecked = 'database_name1';


// External directionary locations for the different projects:
// Declare the different dictionary locations in the $sExternalDictionary hash
// with the database name as a key, and the location as a value
// In the value, please put '<ID>' where the specific page id should be filled in,
// eg. $sExternalDictionary['someproject'] = "http://someurl.nl?pageid=<ID>"

$sExternalDictionary['database_name1'] = "your_host.inl.nl/Entry/<ID>";



// CTRL key must be pressed to get the types menu
$bUseCtrlKey = true;



// ************************************************************
// Data export 

// Location to export the data to (relative path)
$sFilePathForExport = "../export_dir/";

// Name the export zip file should be given
$sZipFileName = "AttestationToolExport.zip";



// ************************************************************
// This part deals with the ALTO export only!
//                          ===========
// We need to be able to distinguish the different STRING-tags from one another,
// so SET THE PROPER STRING IDENTIFIER HERE!
//
// An identifier can be declared in two ways:
// - if the document strings do have an identifier attribute, declare it as a string like: 
//   eg. $AltoUniqueIdentifier = "ID"
// - but if the documents lacks identifiers, we can create those by concatenating different attributes:
//   eg. $AltoUniqueIdentifier = array("content", "height", "width", "vpos", "hpos");


$AltoUniqueIdentifier = array("wc", "height", "width", "vpos", "hpos");  



// ************************************************************





// If we want some things the application issues to be logged for a while,
// give this variable a value (an absolute path name).
// Otherwise, make it FALSE.
//
//          \\\\============================================////
//           |||       DON'T FORGET TO TURN THIS OFF        |||
//           ||| The log file VERY quickly becomes VERY big |||
//          ////============================================\\\\
//

$sLogFile = FALSE; // <-- Use this one to turn off logging


?>