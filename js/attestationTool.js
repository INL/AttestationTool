

// NOTE that all the globally defined variables are in globals.js
// NOTE that some color functions are in color.js
// NOTE that some other functions are in keyFunctions.js
// NOTE that some basic AJAX functions are in ajaxFunctions.js

function init(iNewLemmaId) {
  // Give default values
  iCurSelQuote = 0;
  iCurSelAtt = -1;
  iLemmaId = iNewLemmaId;

  // Find the first quotation, and put focus on it
  // This function works with a global variable, and a global array that is
  // defined at the bottom of attestationTool.php
  selectCurrentQuotation();

  // Fill the statistics
  updateStatisticsOnScreen();
}

// NOTE that in the next two functions, hNewWord is used as a hash, so no
// duplicates can occur
// This hash is only used for the automatic attestations.
// Any other changes to attestations are done directly in the database.
function addToNewWords(oObj, sWordForm, sBackgroundColor) {
  oLatestAttestation = oObj;
  sLatestAttestation = sWordForm;
  sLatestBackgroundColor = sBackgroundColor;
}

function indexInQuotationIds( sString ) {
  for(var i = 0; i < aQuotationIds.length; i++ ) {
    if( aQuotationIds[i] == sString)
      return i;
  }
  return false;
}

function markLemma(oLemmaMark) {
  var xmlHttp = getXMLHtppRequestObject();

  var sNewStyle = 'lemmaMarked';
  var bNewMarked = 1;
  if(oLemmaMark.className == 'lemmaMarked') {
    sNewStyle = 'lemmaUnmarked';
    bNewMarked = 0;
  }

  // Get the onset of the attestation
  // iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset<TAB>sWordform
  var sFile = "./php/markLemma.php?sDatabase=" + sDatabase +
    "&iUserId=" + iUserId + "&iLemmaId=" + iLemmaId +
    "&bNewMarked=" + bNewMarked + uniqueString();

  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 4) {
      var sXmlHttpResponseText = xmlHttp.responseText;
      if( sXmlHttpResponseText.length ) {
	oLemmaMark.innerHTML = sXmlHttpResponseText;
      }
      else {
	sRevDate = 'now'; // It worked
	oLemmaMark.className = sNewStyle;
      }
    }
  }
  
  xmlHttp.open("GET", sFile, true);
  
  xmlHttp.send(null); 
}

function makeCommentBoxEditable() {
  // First check if it isn't editable already
  var oCommentInput = document.getElementById('commentInput');
  if( oCommentInput) {
    oCommentInput.focus();
    return;
  }
  var oCommentBox = document.getElementById('commentBox');
  var sValue = '';
  var iSize = 40;
  // If it actually was there already, take the current value
  if( oCommentBox ) {
    sValue = oCommentBox.innerHTML;
    iSize = sValue.length;
    sValue = sValue.replace('"', '&quot;');
  }

  /// KAN dit niet gelijk commentKeydown
  document.onkeydown = dummyKey; // Switch off the other key behaviour
  oCommentBox.innerHTML = '<input id=commentInput type=text value="' +
    sValue + '" size=' + iSize + " maxlength=255>";
  // Make the row visible
  oCommentBox.style.display = 'block';
  // Give focus, so you can start typing right away
  oCommentInput = document.getElementById('commentInput');
  oCommentInput.focus();
  oCommentInput.onkeydown = commentKeydown;
}

function saveComment(sComment) {
  
  var oCommentBox = document.getElementById('commentBox');

  var xmlHttp = getXMLHtppRequestObject();

  var sFile = "./php/saveComment.php?sDatabase=" + sDatabase +
    "&iUserId=" + iUserId + "&iLemmaId=" + iLemmaId +
    "&sComment=" + sComment + uniqueString();

  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 4) {
      sRevDate = 'now'; // It worked
      // The response text is either an error or the comment in question. We
      // do it like this so it is always the actual comment in the database
      // which is shown.
      oCommentBox.innerHTML = xmlHttp.responseText;

      if( ! xmlHttp.responseText.length ) // If there was nothing/it was deleted
	oCommentBox.style.display = 'none'; // Hide it again
      document.onkeydown = keyDown;
    }
  }
  
  xmlHttp.open("GET", sFile, true);
  
  xmlHttp.send(null); 
}

function addGroupAttestation(iQuotationId, iLemmaId, iPos, iOnset, iOffset,
			     sWordForm) {
  var obj = document.getElementById('att_' + iQuotationId + '_' + iPos);
  var oTypeMenu = document.getElementById('typeMenu');

  // If it is not the first group member
  if( ! (aAttestationGroup['iQuotationId'] &&
	 aAttestationGroup['iQuotationId'] == iQuotationId) ) {
    // First, select current quotation if needed
    // We don't do this with selectCurrentQuotation because it always
    // selects the first quotation
    // By setting iCurSelQuote here we avoid it being called as a result of
    // the onClick event on the quotation div
    if( iCurSelQuote != indexInQuotationIds(iQuotationId) ) {
      unselectCurrentQuotation();
      iCurSelQuote = indexInQuotationIds(iQuotationId);
      document.getElementById('citaat_' + iQuotationId).className =
	'citaat_selected';
    }

    if( obj.className == 'lowlighted') { // It is not an attestation yet
      if( oTypeMenu ) {
	iCurSelQuote = indexInQuotationIds(iQuotationId);
	iCurSelAtt = parseInt(iPos); // Convert to int

	// iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset
	// <TAB>sWordform
	var aParams = obj.title.split("\t");
	fillTypeMenu(obj, oTypeMenu, aParams[2], aParams[0], aParams[1],
		     aParams[3], aParams[4], aParams[5], 'hidden');
	// See if the user held down a key or selected an already attested
	// token first
	if( iPreSelectedTypeRow != -1 ) {
	  speedInsertTypedAttestation(oTypeMenu, iPreSelectedTypeRow);
	  // Remember the type id for the next attestations
	  aAttestationGroup['iTypeId'] =
	    document.getElementById('typeMenuItem_' +
				    iPreSelectedTypeRow).title.split("\t")[6];
	  // Make sure we start afresh next time
	  iPreSelectedTypeRow = -1;
	}
	else { // The user didn't hold down a key. Assume it is a person name.
	  speedInsertTypedAttestation(oTypeMenu, 0);
	  // Get the typeId of the first type in the menu (which is NOT necessarily 1)
	  aAttestationGroup['iTypeId'] =
	    document.getElementById('typeMenuItem_0').title.split("\t")[6];
	}
      }
      else // In the 'normal' case, just insert it
	insertAttestation(obj, iPos, iQuotationId, iLemmaId, iOnset, iOffset,
			  sWordForm);
      // It was lowlighted, so it can't have had a sub part
      aAttestationGroup['sSubPart'] = '';
    }
    else { // It wasn't lowlighted, then select it
      // First, deselect all others
      deselectAllAttestations(iQuotationId);
      // Select this one, including its possible group members
      selectAttestation(obj, iPos, false);
      // At it could be part of a group already, remember the group number
      var aResults = obj.innerHTML.match(/(<sub>\d+<\/sub>)/i);
      aAttestationGroup['sSubPart'] = ( aResults ) ? 
	aResults[0].toLowerCase() : '';
      if( oTypeMenu )
	aAttestationGroup['iTypeId'] =
	  aBackgroundColors2TypeId[hexColor(obj.style.backgroundColor)];
    }

    // We have a global array to keep track of some relevant features of the
    // first attestation someone `-clicked on.
    aAttestationGroup['iQuotationId'] = iQuotationId;
    aAttestationGroup['iOnset'] = iOnset;
    aAttestationGroup['iPos'] = iPos;
    // aAttestationGroup['sSubPart'] is set above...

    // Since it is the first one, nothing actually happens.
    // Tell the user something was done.
    document.getElementById('ajaxDiv').innerHTML =
      sEncapsulatingMessageDivOpen + "Starting group attestation with '" +
      sWordForm + "'";
  }
  else { // The user has `-clicked in this quotation before
    // Check if it is not the original one (in case someone `-clicked it twice
    // or just didn't drag any further than one attestation)
    if( iPos != aAttestationGroup['iPos'] ) {
      // NOTE that if the word was not attested yet, we let this new
      // attestation have the same type as the first attestation the user
      // `-clicked in this quote.
      var iTypeId = (oTypeMenu) ? aAttestationGroup['iTypeId'] : iDefaultTypeValue;
      
      ajaxCall_addGroupAttestation(obj, iQuotationId, iLemmaId, iPos,
				   iOnset, iOffset, sWordForm, iTypeId);
    }
  }
}

// turn word into attestation (e.g. upon click) 
// or, on the contrary, turn an attestation into a neutral word

function toggleAttestation(iQuotationId, iLemmaId, iPos, iOnset, iOffset,
			   sWordForm) {
			   
			
  // If we were in the process of making a grouped attestation, we are not
  // anymore
  aAttestationGroup = [];

  // Make this citation the selected citation if it wasn't already
  if( iQuotationId != aQuotationIds[iCurSelQuote] ) {
    unselectCurrentQuotation();
    iCurSelQuote = indexInQuotationIds(iQuotationId);
    // The next bit quite closely follows the code of selectCurrentQuotation()
    // However, because of a-synchronicity the selecting of the first
    // attestation messed things up in the code hereafter. Also, it isn't
    // necessary in this case so we leave it out
    var iCurSelQuoteId = aQuotationIds[iCurSelQuote];

    // First, select this quotation
    var obj = document.getElementById('citaat_'+ aQuotationIds[iCurSelQuote]);
    if( obj )
      obj.className = 'citaat_selected';
  }

  var obj = document.getElementById('att_' + iQuotationId + '_' + iPos);

  // If it is highlighted (and possibly selected as well)
  if( (obj.className == 'highlighted') ||
      (obj.className == 'highlighted_group') ||
      (obj.className == 'highlighted_selected') ) { // Delete the attestation
    // Throw the attestation out
    deleteAttestation(obj, aQuotationIds[iCurSelQuote], iLemmaId, iPos);
  }
  else { // If it was lowlighted
    // The multi type attestation case
    var oTypeMenu = document.getElementById('typeMenu');
    if( oTypeMenu ) {
	// get the menu, if the user held down the CTRL key (or if that is not required, so clicking was enough to get the menu)
	  if( bCtrlKeyDown || !bUseCtrlKey ) { 
		  fillTypeMenu(obj, oTypeMenu, iPos, iQuotationId, iLemmaId, iOnset,
				 iOffset, sWordForm, 'visible');
      }
	  // If the user held down a button, specifying an attestation type
      else if( iPreSelectedTypeRow != -1 ) { 
		iCurSelAtt = parseInt(iPos); // Convert to int
		var aParams = obj.title.split("\t");
		fillTypeMenu(obj, oTypeMenu, aParams[2], aParams[0], aParams[1],
				 aParams[3], aParams[4], aParams[5], 'hidden');
		speedInsertTypedAttestation(oTypeMenu, iPreSelectedTypeRow);
      }
      else // if the user just clicked on a word, that means select by default the first menu option 
	       //(so don't show the menu, but attest straight away)
	  {
		  iCurSelAtt = parseInt(iPos); // Convert to int
		var aParams = obj.title.split("\t");
		fillTypeMenu(obj, oTypeMenu, aParams[2], aParams[0], aParams[1],
				 aParams[3], aParams[4], aParams[5], 'hidden');
		iPreSelectedTypeRow = 0; // 1st option will be chosen (...TypeRow is 0-based, ...Type is 1-based)
		
		speedInsertTypedAttestation(oTypeMenu, iPreSelectedTypeRow);
	  }
	
    }
    else { // In the 'normal' case (= no types defined), just add it as an attestation
      insertAttestation(obj, iPos, iQuotationId, iLemmaId, iOnset, iOffset,
			sWordForm);
    }
  }
}

// In case somebody hit the 'w' key
function toggleCurrentAttDoubt() {
  if( iCurSelAtt == -1)
    return;

  var oCurSelAtt = document.getElementById('att_' +
					   aQuotationIds[iCurSelQuote] + '_' +
					   iCurSelAtt);

  var bNewDubious = 1;
  // If it was already marked as dubious
  if( oCurSelAtt.innerHTML.match(reDubious, '') )
    bNewDubious = 0;

  // Put it right in the database
  // The little image in the div is only removed after the change
  // in the database took place
  ajaxCall_setCurrentAttDubious(oCurSelAtt, bNewDubious);
}

// In case somebody hit the 'e' key
function toggleCurrentAttElliptical() {
  if( iCurSelAtt == -1)
    return;

  var oCurSelAtt = document.getElementById('att_' +
					   aQuotationIds[iCurSelQuote] + '_' +
					   iCurSelAtt);

  var bNewElliptical = 1;
  // If it was already marked as elliptical
  if( oCurSelAtt.innerHTML.match(reElliptical, '') )
    bNewElliptical = 0;

  // Put it right in the database
  // The little image in the div is only removed after the change
  // in the database took place
  ajaxCall_setCurrentAttElliptical(oCurSelAtt, bNewElliptical);
}

// In case somebody hit the 's' key
function toggleCurrentAttError() {
  if( iCurSelAtt == -1)
    return;

  var oCurSelAtt = document.getElementById('att_' +
					   aQuotationIds[iCurSelQuote] + '_' +
					   iCurSelAtt);

  var bNewError = 1;
  // If it was already marked as erroneous
  if( oCurSelAtt.innerHTML.match(reErroneous, '') )
    bNewError = 0;

  // Put it right in the database
  // The little image in the div is only removed after the change
  // in the database took place
  ajaxCall_setCurrentAttError(oCurSelAtt, bNewError);
}

function fillTypeMenu(oAttestation, oTypeMenu, iPos, iQuotationId, iLemmaId,
		      iOnset, iOffset, sWordForm, sVisibility) {
  // Replace double quotes again for the title
  sWordForm = sWordForm.replace('"', '&quot;');

  // Reset the selected row
  iSelectedTypeRow = -1;
  // NOTE that we make use of aBackgroundColors which is generated in php by the getBackgroundColorInfo() funtion,
  // based on the database table 'types'.
  var sMenu = '';
  var iRowNr = 0;
  var bCommentFieldIsPartOfMenu = false;
  
  for(var iTypeId in aBackgroundColors ) {
  
	var sTypeName = aBackgroundColors[iTypeId][0];
	if (sTypeName=='comment')
	{
		bCommentFieldIsPartOfMenu = true;
		sMenu += '<div class=typeMenuItem id=typeMenuComment' +
	  // Remember all parameters so we can access them
	  " title=\"" +iPos+ "\t" + iQuotationId + "\t" + iLemmaId + "\t" + iOnset +
		  "\t" + iOffset + "\t" + sWordForm + "\t" + iTypeId + "\" " + 
		  "onclick=\"document.getElementById('usercomment').focus()\"" +
		  ">" + 
		  "<input id=\"usercomment\" type=\"text\" style=\"width:300px\" />" +
		  "</div>";
	}
	else
	{
		sMenu += "<div class=typeMenuItem id=typeMenuItem_" + iRowNr +
		  // Remember all parameters so we can access them from e.g. the keydown
		  // function
		  " title=\"" +iPos+ "\t" + iQuotationId + "\t" + iLemmaId + "\t" + iOnset +
		  "\t" + iOffset + "\t" + sWordForm + "\t" + iTypeId + "\" " +
		  "onMouseOver=\"javascript: iSelectedTypeRow = " + iRowNr + ";" +
		  " this.className = 'typeMenuItem_';" +
		  " this.style.cursor='pointer';\" " +
		  "onMouseOut=\"javascript: iSelectedTypeRow = -1;" +
		  " this.className = 'typeMenuItem';\" " +
		  "onClick=\"javascript: iSelectedTypeRow = " + iRowNr + "; " +
		  " insertTypedAttestation();\">" + 
		  "<span class=colorSample style='background-color: " +
		  aBackgroundColors[iTypeId][1] + "'>&nbsp &nbsp;</span> " +
		  aBackgroundColors[iTypeId][0] + 
		  ( aBackgroundColors[iTypeId][2].toLowerCase() != '' ?
			" (key: " + aBackgroundColors[iTypeId][2].toLowerCase() + ")" : "") +
		  "</div>";
		  
	}
    
    iRowNr++;
  }
  // Building html for menu is finished, so
  // now assign it to the DOM tree
  oTypeMenu.innerHTML = sMenu;

  // Show it
  var aCoordinates = findPos(oAttestation);
  oTypeMenu.style.left = aCoordinates[0]  + 'px';
  oTypeMenu.style.top = (aCoordinates[1] + oAttestation.offsetHeight) + 'px';
  oTypeMenu.style.visibility = sVisibility;
  bClickedInTypeMenu = 1; // = true

  if( sVisibility == 'visible')
  {
    document.onkeydown = fileMenuKeyDown;
	
	// If we have max two options, one of which is the comment field, it is most probable the menu
	// will be called when the user wants to type some comment: in this particular case it should be set to focus.
	// When more options are available, it's not sure why the user called the menu so we set nothing to focus.
	if (bCommentFieldIsPartOfMenu && iRowNr<=2)
		{document.getElementById("usercomment").focus()}
  }
}

// Select row number iSelectedTypeRow
function selectTypeMenuRow() {
  var oTypeMenuItem;
  for(var i = 0; i < (aBackgroundColors.length/2); i++ ) {
    oTypeMenuItem = document.getElementById('typeMenu').children[i];
    if( i == iSelectedTypeRow)
      oTypeMenuItem.className = 'typeMenuItem_';
    else // If it was selected
      oTypeMenuItem.className = 'typeMenuItem';
  }
}


// Click event listener
// this takes care of removing the type menu is the user clicks somewhere
function addClickEventListener(){
	document.addEventListener('click', function(e) {
	
		e = e || window.event;
		var target = e.target || e.srcElement;
		
		// don't hide the type menu we the last element that was clicked upon is the comment field of that menu
		if (target.id == 'usercomment')
			return;
		else
			hideTypeMenu();
	}, false); // false means the event handler is executed in the bubbling ( =/= capturing ) phase
};

function hideTypeMenu() {
  var oTypeMenu = document.getElementById('typeMenu');
  
  // If an attestation was clicked upon, bClickedInTypeMenu was set to 1;
  // but if the type menu was called by pressing  't', bClickedInTypeMenu was set to 0, 
  // so !bClickedInTypeMenu = !false = true
  if( oTypeMenu && ! bClickedInTypeMenu ) {
    oTypeMenu.style.visibility = 'hidden';
    var oSplitTokenBox = document.getElementById('splitTokenBox');
    if( ! oSplitTokenBox )
      document.onkeydown = keyDown;
  }
  bClickedInTypeMenu = 0; // = false
}

function changeAttestation(iQuotationId, iLemmaId, iPos, iOnset, iOffset,
			   sWordForm) {
  // Maak this quotation the selected one if it wasn't already
  if( iQuotationId != aQuotationIds[iCurSelQuote] ) {
    unselectCurrentQuotation();
    iCurSelQuote = indexInQuotationIds(iQuotationId);
    selectCurrentQuotation();
  }

  // If the word already was the selected word, nothing happens
  if( iPos != iCurSelAtt ) {
    var obj = document.getElementById('att_' + iQuotationId + '_' + iPos);
    // If the word was highlighted already, it will be selected as well
    // and that's just it
    if( obj.className == 'highlighted' ||
	obj.className == 'highlighted_group') {
      // Deselect the currently selected attestation
      document.getElementById('att_' + aQuotationIds[iCurSelQuote] +
			      '_' + iCurSelAtt).className = 'highlighted';
      selectAttestation(obj, iPos, false);
    }
    else { // It was lowlighted, i.e. it's a new attestation
      // Get the one that is currently selected for the old onset/offset
      var oOldAttestation =
	document.getElementById('att_' + aQuotationIds[iCurSelQuote] + '_'
				+ iCurSelAtt);

      //iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset<TAB>sWordform
      aParams = oOldAttestation.title.split("\t");
      moveAttestation(document.getElementById('att_'+ iQuotationId+ '_'+ iPos),
		      iPos, iQuotationId, iLemmaId, aParams[3],
		      aParams[5], iOnset, iOffset, sWordForm);
    }
  }
}

// Select the first highlighted attestation in this quotation
// Because of the possibilty of grouped attestatiosn we first put all
// attestations on highlighted (including the grouped ones) and then select
// the first one anew (possibly with grouped ones).
function selectFirstAttestation (iQuotationId) {
  var oReturnObject = false;
  var iReturnObjectPos;
  var i = 0;

  // Get the first one
  var obj= document.getElementById('att_' +  iQuotationId + '_' + i);
  while( obj ) {
    if( obj.className == 'highlighted' ||
	obj.className == 'highlighted_group' ||
	// It could have been selected already
	obj.className == 'highlighted_selected' ) {
      if( ! oReturnObject ) {
	oReturnObject = obj;
	iReturnObjectPos = i;
      }
      obj.className = 'highlighted'; // Un-selected any marked one
    }
    // Next one
    i++;
    obj = document.getElementById('att_' +  iQuotationId + '_' + i);
  }
  if( oReturnObject )
    selectAttestation(oReturnObject, iReturnObjectPos, false);
  else
    iCurSelAtt = -1;

  return oReturnObject;
}

// Select the next attestation
// Due do grouped attestations we first un-select every one on the line, and
// then select the new one, including its possible group members
//
// NOTE that iDirection can be negative, in which case we walk backwards (not
// forwards)
function selectNextAttestation(iDirection) {
  var oReturnObject = false;
  var iReturnObjectPos;

  if( iCurSelAtt != -1) {
    var obj;
    for( var i = (iCurSelAtt+iDirection); i != iCurSelAtt; i += iDirection ) {
      obj = document.getElementById('att_' +  aQuotationIds[iCurSelQuote] +
				    '_' + i);
      if( ! obj ) { // If we ran out of objects
	if( iDirection > 0)
	  i = -1; // Start at the beginning again
	else // Go to the last one
	  i = aNrOfTokens['qid_' + aQuotationIds[iCurSelQuote]];
      }
      else {
	if( obj.className == 'highlighted' ||
	    // Unselect all grouped ones too
	    obj.className == 'highlighted_group' ) {
	  obj.className = 'highlighted';
	  if( ! oReturnObject ) { // First one we encounter
	    document.getElementById('att_' + aQuotationIds[iCurSelQuote] + '_'
				    + iCurSelAtt).className = 'highlighted';
	    oReturnObject = obj;
	    iReturnObjectPos = i;
	  }
	}
      }
    }
  }

  if( oReturnObject )
    selectAttestation(oReturnObject, iReturnObjectPos, false);
  // NOTE, if no other attestation was encountered we stick to the current one
}

// Select the first highlighted attestation in this quotation
// This routine is only called when an attestation has just been deleted.
function selectCloseAttestation (iQuotationId, iAttPos, reSubPart) {
  // Because we are going to do some calculation with it we have to make
  // sure that iAttPos is an numerical value (otherwise iAttPos + i ends up
  // being a string concatenation...).
  iAttPos = parseInt(iAttPos);
  var bSelectedOne = false;
  var oSelectedOne = false;
  var iSelectedPos = -1;
  var oOnlyGroupAttestationLeft;
  var iNrOfGroupAttestationsLeft = 0;

  // The loop stops if getElementById can't find any more attestations
  var obj;
  for( var i = (iAttPos + 1); i > 0; i++ ) {
    obj = document.getElementById('att_' +  iQuotationId + '_' + i);
    // Quit this loop if there are no attestations left, or we found one
    // and we don't have to look for other ones in the same group
    if( ! obj || ( ! reSubPart && bSelectedOne ) )
      i = -1; 
    else {
      // Check if it is a group member of the deleted attestation
      // (Note that we try to avoid regular expression matching for speed...)
      if( reSubPart && (iNrOfGroupAttestationsLeft < 2) &&
	  obj.innerHTML.match(reSubPart) ) {
	oOnlyGroupAttestationLeft = obj;
	iNrOfGroupAttestationsLeft++;
      }

      if( obj.className == 'highlighted' ||
	  obj.className == 'highlighted_group' ||
	  // It could have been selected already
	  obj.className == 'highlighted_selected' ) {
	if( bSelectedOne ) // Keep it highlighted (if it was selected)
	  obj.className = 'highlighted';
	else {
	  oSelectedOne = obj;
	  iSelectedPos = i;
	  bSelectedOne = true;
	}
      }
    }
  }
  // Go the other way, but only if we have to because there was a group
  // involved or we dind't find one yet
  if( reSubPart || ! bSelectedOne ) {
    for( var i = (iAttPos - 1); i >= 0; i-- ) {
      obj = document.getElementById('att_' +  iQuotationId + '_' + i);
      // Check if it is a group member of the deleted attestation
      // (Note that we try to avoid regular expression matching...)
      if( reSubPart && 
	  (iNrOfGroupAttestationsLeft < 2) && obj.innerHTML.match(reSubPart)) {
	oOnlyGroupAttestationLeft = obj;
	iNrOfGroupAttestationsLeft++;
      }
      if( obj.className == 'highlighted' ||
	  obj.className == 'highlighted_group' ||
	  // It could have been selected already
	  obj.className == 'highlighted_selected' ) {
	if( bSelectedOne ) // Keep it highlighted (if it was selected)
	  obj.className = 'highlighted';
	else {
	  oSelectedOne = obj;
	  iSelectedPos = i;
	  bSelectedOne = true;
	}
      }
    }
  }

  if( bSelectedOne ) {
    if( iNrOfGroupAttestationsLeft == 1)
      oOnlyGroupAttestationLeft.innerHTML =
	oOnlyGroupAttestationLeft.innerHTML.replace(reSubPart, '');
    selectAttestation(oSelectedOne, iSelectedPos, false);
  }
  else
    // If we come here with no selected ones, there are no attestations left
    iCurSelAtt = -1;
}

function focusAttestation(obj, iQuotationId, iPos) {
  // Check if it is a highlighted attestation at all.
  // If not, we don't do anything.
  if( obj.style.backgroundColor && obj.style.backgroundColor.length ) {
    deselectAllAttestations(iQuotationId);
    selectAttestation(obj, iPos, false);
  }
}

function selectAttestation(obj, iPos, sBackgroundColor) {
  obj.className = 'highlighted_selected';
  iCurSelAtt = parseInt(iPos);

  if( sBackgroundColor)
    obj.style.backgroundColor = sBackgroundColor;
  else {
    // There was no background color specified
    // This means the new attestation to be highlighted is an existing one
    // and it might be part of a group
    var aResults = obj.innerHTML.match(/(<sub>\d+<\/sub>)/i);
    if( aResults )
      selectGroupAttestation(obj, iPos, sBackgroundColor, aResults[1]);
  }

  // Scroll automatically to the right height
  // Was proved useful in the past, but commented out as some users complained
  //var aPositions = findPos(obj);
  //window.scrollTo(0, Math.max(aPositions[1]-(windowHeight()/2)), 0);
}

function selectGroupAttestation(oAttestation,iPos,sBackgroundColor, sSubPart){
  // iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset
  // <TAB>sWordform
  var aParams = oAttestation.title.split("\t");

  var i = 0;
  var obj = document.getElementById('att_' + aParams[0] + '_' + i);
  var reSubPart = new RegExp(sSubPart, "i")
  while( obj ) {
    // No need to match the already selected one
    if( i != iPos && obj.innerHTML.match(reSubPart) )
      obj.className = 'highlighted_group';
    i++;
    obj = document.getElementById('att_' + aParams[0] + '_' + i);
  }
}

function deselectAllAttestations(iQuotationId) {
  var i = 0;
  
  var obj = document.getElementById('att_' + iQuotationId + '_' + i);
  while( obj ) {
    if(obj.className == 'highlighted_group' ||
       obj.className == 'highlighted_selected')
      obj.className = 'highlighted';
    i++;
    obj = document.getElementById('att_' + iQuotationId + '_' + i);
  }
}

function selectCurrentQuotation () {
  // First, select this quotation
  var obj = document.getElementById('citaat_' + aQuotationIds[iCurSelQuote]);
  if( obj ) {
    obj.className = 'citaat_selected';
    // Select the first attestation
    selectFirstAttestation(aQuotationIds[iCurSelQuote]);
  }
}

function unselectCurrentQuotation () {
  // Empty the group array
  aAttestationGroup = [];

  var obj = document.getElementById('citaat_' + aQuotationIds[iCurSelQuote]);
  obj.className = 'citaat'; // un-select

  iCurSelAtt = -1;

  deselectAllAttestations(aQuotationIds[iCurSelQuote]);
}

// We add a new attestation, which is by default the first non-attested
// (i.e. non-highlighted) word. The user can put the attestation on the right
// position his/herself
function addAttestation(iQuotationId, iLemmaId) {
  var obj;
  for(var i = 0; i >= 0; i++ ) {
    obj = document.getElementById('att_' + iQuotationId + '_' + i);
    // If nothing was found, nothing happens
    if( ! obj )
      return;
    if( obj.className == 'lowlighted' ) {
      // Select the new attestation
      obj = document.getElementById('att_' + iQuotationId + '_' + i);
      // Run the update query
      //iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset<TAB>sWordform
      var aParams = obj.title.split("\t");
      insertAttestation(obj, i, iQuotationId, iLemmaId, aParams[3],
			aParams[4], aParams[5]);
      // Step out of the loop/function
      return;
    }
  }
}

// We make a text box of the current token that you can type in
function makeCurTokenEditable() {
  var oCurSelAtt = document.getElementById('att_' +
					   aQuotationIds[iCurSelQuote] +
					   '_' + iCurSelAtt);
  // Remember the dubiosity and error of the current attestation
  // The split tokens will get the same
  // NOTE that the boolean values are set to 1 or 0 explicitely as these values
  // are used to update the respective columns in the database later on
  // NOTE that the innerHTML is copied and altered to extract the wordform from
  var sInnerHTML = oCurSelAtt.innerHTML;
  var iLength = sInnerHTML.length;
  sInnerHTML = sInnerHTML.replace(reDubious, '');
  var bDubiosity = (sInnerHTML.length < iLength) ? 1 : 0;
  iLength = sInnerHTML.length;
  sInnerHTML = sInnerHTML.replace(reElliptical, '');
  var bElliptical = (sInnerHTML.length < iLength) ? 1 : 0;
  iLength = sInnerHTML.length;
  sInnerHTML = sInnerHTML.replace(reErroneous, '');
  var bError = (sInnerHTML.length < iLength)  ? 1 : 0;

  // iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset<TAB>sWordform
  var aParams = oCurSelAtt.title.split("\t");
  // Check if we are in multi-type mode
  var iTypeId = (document.getElementById('typeMenu')) ?
    aBackgroundColors2TypeId[hexColor(oCurSelAtt.style.backgroundColor)] : iDefaultTypeValue;
  var sBackgroundColor = oCurSelAtt.style.backgroundColor;
  // Wordforms can be prepended by a space
  var sWordform = sInnerHTML.replace(/^(<[^>]+>)?\s/, "$1");
  sWordform = sWordform.replace(/\"/g, '&quot;');
  // Delete the <sub> group part. After the user hits the enter the attestation
  // div will be filled anew, so the <sub> part will be calculated again.
  // The /i modifier is there because some browsers (e.g. IE) uppercase
  // the tags for you  
  sWordform = sWordform.replace(/<sub>\d+<\/sub>/i, '');
  oCurSelAtt.onclick = function() {}; // Switch off the onClick attribute

  document.onkeydown = dummyKey;
  oCurSelAtt.innerHTML = '<input id=splitTokenBox type=text value="' +
    sWordform + '" ' +
    "onkeydown=\"javascript: " +
    "return tokenSplitKeydown(event, " + aParams[0] + ", " + aParams[3] +
    ", " + bDubiosity + "," + bElliptical + ", " + bError + ", " + iTypeId +
    ");\">";
  document.getElementById('splitTokenBox').focus();
}

// NOTE that only a year, or just year-month, etc. is fine as well
function isValidDate (sDate) {
  if( sDate.match( /^\d{4}(\-\d{1,2}(\-\d{1,2}( \d{1,2}(\:\d{2}(\:\d{2})?)?)?)?)?$/) )
    return true;
  return false;
}

function higlightNewWords(sNewWords, sBackgroundColor, bDubious, bElliptical,
			  bErroneous, fnCallback) {
			  
  var aDivsToSplit = sNewWords.split(',');
  var sDiv;
  var sNewBackgroundColor = (sBackgroundColor.length) ? sBackgroundColor :
    sDefaultBackgroundColor;

  var obj;
  for(var i = 0; i < aDivsToSplit.length; i++ ) {
    obj = document.getElementById(aDivsToSplit[i]);
    // We only need to highlight the un-highlighted ones
    // In which case we also give new values for dubiousness, etc.
    if( obj.className == 'lowlighted') {
      obj.className = 'highlighted';
      var sDubEllErr = '';
      if( bDubious )
	sDubEllErr += sDubiousImg;
      if( bElliptical )
	sDubEllErr += sEllipticalImg;
      if( bErroneous)
	sDubEllErr += sErroneuosImg;
      if( sDubEllErr.length ) // Prepend any dubiosity, etc.
	obj.innerHTML = sDubEllErr + obj.innerHTML;
      // Set background color
      obj.style.backgroundColor = sNewBackgroundColor;
    }
  }
  
  fnCallback();
  
}

function deleteInAutoDeAttest(respText) {
	
	var aSpansToToggle = respText.split(',');
	
	for (var i=0; i<aSpansToToggle.length; i++)
	{
	sTuples = document.getElementById(aSpansToToggle[i]).title;
	bHighlight = document.getElementById(aSpansToToggle[i]).className != 'lowlighted' ? 1 : 0;
	aTuples = sTuples.split("\t");
	
	// if some token is highlighted, it can be de-attested
	if (bHighlight)
		toggleAttestation(aTuples[0], aTuples[1], aTuples[2], aTuples[3], aTuples[4], aTuples[5]);
	}
}


function unhighlightWords(sDivsToUnhighlight) {
  var aDivsToUnhighlight = sDivsToUnhighlight.split(',');
  var sDiv;

  var oDivToUnhighlight;
  for(var i = 0; i < aDivsToUnhighlight.length; i++ ) {
    oDivToUnhighlight = document.getElementById(aDivsToUnhighlight[i]);

    // Deselect the current selection
    oDivToUnhighlight.className = 'lowlighted';
    // No background color
    oDivToUnhighlight.style.backgroundColor = '';

    var sInnerHTML = oDivToUnhighlight.innerHTML;
    // No marks for dubiosity/elliptical/error anymore
    sInnerHTML = sInnerHTML.replace(reDubious, "");
    sInnerHTML = sInnerHTML.replace(reElliptical, "");
    sInnerHTML = sInnerHTML.replace(reErroneous, "");

    // We ignore groups as grouped attestations can not be auto de-attested
    // (for the moment)
  
    // Set the innerHTML again
    oDivToUnhighlight.innerHTML = sInnerHTML;
  }
  return 1;
}

// This function gets you a 'unique' string in the form of the number
// of milliseconds since 1970 January 1st. It can be appended to a URL
// which will then always be unique to the webserver/caching mechanism.
// This pretty stupid trick has to be performed in order to prevent IE
// from never actually carrying out AJAX calls more than once.
// I got the suggestion from http://www.howtoadvice.com/StopCaching
function uniqueString() {
  return "&sUnique=" + new Date().getTime();
}

function fillTotalStats() {
  // Don't do this on the log out page
  if( document.getElementById('attestationsDiv').innerHTML.substr(0,5) !=
      "Thank" )
    ajaxCall("./php/totalStats.php?sDatabase=" + sDatabase + uniqueString(),
	     "totalStats", "Couldn't get totals.", true);
}

function fillUserStats() {
  ajaxCall("./php/userStats.php?sDatabase=" + sDatabase + "&iUserId=" +
	   iUserId + "&sUser=" + sUser + uniqueString(),
	   "userStats", "Couldn't get user figures.", true);
}

function fillLastEdited() {
  ajaxCall("./php/lastEdited.php?sDatabase=" + sDatabase +
	   "&iLemmaId=" + iLemmaId + uniqueString(),
	   "lastEdited", "Couldn't get revision date.", true);
}

function updateStatisticsOnScreen() {
  // Update the user figures
  fillUserStats();
  
  // Update the total figures
  fillTotalStats();

  // Show when the lemma was editted
  fillLastEdited();

  // Refresh the displayed lemma id's
  // (iLemmaId is a global variable)
  displayAttestedSoFar(iLemmaId); 
}

// Clear all the informative div's
function emptyScreen() {
  document.getElementById('ajaxDiv').innerHTML = '';
  document.getElementById('totalStats').innerHTML = '';
  document.getElementById('userStats').innerHTML = '';
  document.getElementById('lastEdited').innerHTML = '';
  hideTypeMenu();
  iCurSelAtt = -1;
}

// Very useful function that I found on QuirksMode:
// http://www.quirksmode.org/js/findpos.html
function findPos(obj) {
  var curleft = curtop = 0;
  if (obj.offsetParent) {
    do {
      curleft += obj.offsetLeft;
      curtop += obj.offsetTop;
    } while (obj = obj.offsetParent);
    return [curleft,curtop];
  }
}

// Functions for group selection //

function startSelection(oObj) {
  bMousePressed = 1;
  var aMatches = oObj.id.match(/^att_(\d+)_(\d+)$/);
  var bAlreadyAttested = (oObj+"").match(/highlighed/) ? 1 : 0;
  
  if( aMatches && !bAlreadyAttested ) {
  
    // get the value of the first \d+ group, which is the quote id
    iSelectionQuoteId = parseInt(aMatches[1]); 
	
    // If the user clicked in another quotation
    if( iCurSelQuote != indexInQuotationIds(iSelectionQuoteId) ) {
      unselectCurrentQuotation();
      iCurSelQuote = indexInQuotationIds(iSelectionQuoteId);
      // Select the quotation
      // We don't do this with selectCurrentQuotation as that will cause the
      // first attestation to be selected as well
      // First, select this quotation
      var obj = document.getElementById('citaat_' + iSelectionQuoteId);
      if( obj )
	obj.className = 'citaat_selected';
    }
    // get the id of the first selected word (second \d+ group in aMatches)
    iFirstSelected = parseInt(aMatches[2]);
  }
}

function addToSelection(oObj) {
  if( bMousePressed || bAutoAttest ) {
    var aMatches = oObj.id.match(/^att_(\d+)_(\d+)$/);
	var bAlreadyAttested = (oObj+"").match(/highlighed/) ? 1 : 0;
  
    if(aMatches && !bAlreadyAttested) // NOTE that we only consider tokens of the same quotation
      if( iSelectionQuoteId == parseInt(aMatches[1]) )
		iLastSelected = parseInt(aMatches[2]);
  }
}

function endSelection() {
  bMousePressed = 0;

  if( (iFirstSelected != iLastSelected) && (iLastSelected != -1) ) {
    var i = iFirstSelected;
    // Make sure we start a new group
    aAttestationGroup = [];
    
    var oFirstAttestation = document.getElementById('att_' +
						    iSelectionQuoteId + 
						    '_' + iFirstSelected);
	var oLastAttestation = document.getElementById('att_' +
						    iSelectionQuoteId + 
						    '_' + iLastSelected);
    // iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset
    // <TAB>sWordform
    var aParams = oFirstAttestation.title.split("\t");
    
    // See if we are in multi-types mode
    var oTypeMenu = document.getElementById('typeMenu');
    var bMultiType = (oTypeMenu) ? 1 : 0; // if the database contains a types table, oTypeMenu is always true

    var iFirstAttestationType = (oFirstAttestation.className != 'lowlighted' && bMultiType) || bAutoAttest ?
    aBackgroundColors2TypeId[hexColor(oFirstAttestation.style.backgroundColor)]
      : 
	( (oLastAttestation.className != 'lowlighted' && bMultiType) ?
	aBackgroundColors2TypeId[hexColor(oLastAttestation.style.backgroundColor)]
	: iDefaultTypeValue); 

    // As it could be part of a group already, remember the group number
    var aResults = oFirstAttestation.innerHTML.match(/(<sub>\d+<\/sub>)/i);
    aAttestationGroup['sSubPart']= (aResults) ? aResults[0].toLowerCase() : '';
      
    var iDefaultType;
	
//    if( iPreSelectedTypeRow != -1 ) {
//      fillTypeMenu(oFirstAttestation, oTypeMenu, aParams[2], aParams[0],
//		   aParams[1], aParams[3], aParams[4], aParams[5], 'hidden');
		   
//      iDefaultType =
//	document.getElementById('typeMenuItem_' +
//				iPreSelectedTypeRow).title.split("\t")[6];
//    }
    //else
	
    iDefaultType = iFirstAttestationType;

    ajaxCall_makeGroupAttestation(aParams[1], bMultiType,
				  iFirstAttestationType, aParams[3],
				  aParams[4], aParams[5], iDefaultType,
				  iFirstSelected, iLastSelected);
    iPreSelectedTypeRow = -1;
  }
  iFirstSelected = -1;
  iLastSelected = -1;
}

// Functions using AJAX

function ajaxCall_setCurrentAttDubious(oCurSelAtt, bNewDubious) {
  var oAjaxDiv = document.getElementById('ajaxDiv');
  var xmlHttp = getXMLHtppRequestObject();

  // Get the onset of the attestation
  // iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset<TAB>sWordform
  var sFile = "./php/setCurrentAttDubious.php?sDatabase=" + sDatabase +
    "&iUserId=" + iUserId + "&iLemmaId=" + iLemmaId + "&iQuotationId=" +
    aQuotationIds[iCurSelQuote] + "&iOnset=" + oCurSelAtt.title.split("\t")[3]+
    "&bNewDubious=" + bNewDubious + uniqueString();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 4) {
      var sXmlHttpResponseText = xmlHttp.responseText;
      if( sXmlHttpResponseText != "" ) {
	oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	  sXmlHttpResponseText + sEncapsulatingDivClose;
      }
      else {
	sRevDate = 'now';

	// Build the new attestation (somehow it works better if we build
	// it first and then assign it to the innerHTML...)
	var sNewAtt = '';
	if( bNewDubious ) // We should add it
	  sNewAtt = sDubiousImg + oCurSelAtt.innerHTML;
	else // We should remove it
	  sNewAtt = oCurSelAtt.innerHTML.replace(reDubious, '');
	oCurSelAtt.innerHTML = sNewAtt;

	var sMessageStart = (bNewDubious) ? 'Marked' : 'Unmarked';
	// Keep the user informed
	oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	sMessageStart + " attestation as dubious" +
	sEncapsulatingDivClose;

	// Something might have changed
	updateStatisticsOnScreen();
      }
    }
  }
  
  xmlHttp.open("GET", sFile, true);
	
  xmlHttp.send(null);
}

function ajaxCall_setCurrentAttElliptical(oCurSelAtt, bNewElliptical) {
  var oAjaxDiv = document.getElementById('ajaxDiv');
  var xmlHttp = getXMLHtppRequestObject();

  // Get the onset of the attestation
  // iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset<TAB>sWordform
  var sFile = "./php/setCurrentAttEllipsis.php?sDatabase=" + sDatabase +
    "&iUserId=" + iUserId + "&iLemmaId=" + iLemmaId + "&iQuotationId=" +
    aQuotationIds[iCurSelQuote] + "&iOnset=" + oCurSelAtt.title.split("\t")[3]+
    "&bNewElliptical=" + bNewElliptical + uniqueString();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 4) {
      var sXmlHttpResponseText = xmlHttp.responseText;
      if( sXmlHttpResponseText != "" ) {
	oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	  sXmlHttpResponseText + sEncapsulatingDivClose;
      }
      else {
	sRevDate = 'now';

	// Build the new attestation (somehow it works better if we build
	// it first and then assign it to the innerHTML...)
	var sNewAtt = '';
	if( bNewElliptical ) // We should add it
	  sNewAtt = sEllipticalImg + oCurSelAtt.innerHTML;
	else // We should remove it
	  sNewAtt = oCurSelAtt.innerHTML.replace(reElliptical, '');
	oCurSelAtt.innerHTML = sNewAtt;

	var sMessageStart = (bNewElliptical) ? 'Marked' : 'Unmarked';
	// Keep the user informed
	oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	sMessageStart + " attestation as part of an elliptical expression" +
	sEncapsulatingDivClose;

	// Something might have changed
	updateStatisticsOnScreen();
      }
    }
  }
  
  xmlHttp.open("GET", sFile, true);
	
  xmlHttp.send(null);
}

function ajaxCall_setCurrentAttError(oCurSelAtt, bNewError) {
  var oAjaxDiv = document.getElementById('ajaxDiv');
  var xmlHttp = getXMLHtppRequestObject();

  // Get the onset of the attestation
  // iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset<TAB>sWordform
  var sFile = "./php/setCurrentAttError.php?sDatabase=" + sDatabase +
    "&iUserId=" + iUserId + "&iLemmaId=" + iLemmaId + "&iQuotationId=" +
    aQuotationIds[iCurSelQuote] + "&iOnset=" + oCurSelAtt.title.split("\t")[3]+
    "&bNewError=" + bNewError + uniqueString();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 4) {
      var sXmlHttpResponseText = xmlHttp.responseText;
      if( sXmlHttpResponseText != "" ) {
	oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	  sXmlHttpResponseText + sEncapsulatingDivClose;
      }
      else {
	sRevDate = 'now';

	// Build the new attestation (somehow it works better if we build
	// it first and then assign it to the innerHTML...)
	var sNewAtt = '';
	if( bNewError ) // We should add it
	  sNewAtt = sErroneuosImg + oCurSelAtt.innerHTML;
	else // We should remove it
	  sNewAtt = oCurSelAtt.innerHTML.replace(reErroneous, '');
	oCurSelAtt.innerHTML = sNewAtt;

	var sMessageStart = (bNewError) ? 'Marked' : 'Unmarked';
	// Keep the user informed
	oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	sMessageStart + " attestation as erroneous" +
	sEncapsulatingDivClose;

	// Something might have changed
	updateStatisticsOnScreen();
      }
    }
  }
  
  xmlHttp.open("GET", sFile, true);
	
  xmlHttp.send(null);
}


function getTokenTuples() {
  var iDiff = (iFirstSelected < iLastSelected) ? 1 : -1;
  var sTokenTuples = '';
  var cSeparator = '';
  var aParams;
  
  // NOTE that we don't take the first one (it's done separately)  
  for(var i = (iFirstSelected + iDiff);
      i != (iLastSelected + iDiff); i += iDiff) {
    var oToken = document.getElementById('att_' + iSelectionQuoteId + '_' + i);
    // iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset<TAB>sWordform
    aParams = oToken.title.split("\t");
	
	// in autoattest, we consider nothing is attested yet (=0)
	// otherwise: if the token already has a background color, it is attested (=1), otherwise it is not (=0)
    var bInDb = bAutoAttest ? 0 : ((oToken.style.backgroundColor) ? 1 : 0); 
    // pos|onset|offset|word form|inDb
    sTokenTuples += cSeparator + aParams[2] + "|" + aParams[3] + "|" +
      aParams[4] + "|" + encodeURIComponent(aParams[5]) + "|" + bInDb;
    cSeparator = '||';
  }
  return sTokenTuples;
}

function ajaxCall_makeGroupAttestation(iLemmaId, bMultiType,
				       iFirstAttestationType, iFirstOnset,
				       iFirstOffset, sFirstWordForm,
				       iDefaultType, iStartPos, iEndPos) {
  var oAjaxDiv = document.getElementById('ajaxDiv');

  var xmlHttp = getXMLHtppRequestObject();

  var sFile = "./php/makeGroupAttestation.php?sDatabase=" + sDatabase +
    "&iUserId=" + iUserId +
    "&iQuotationId=" + iSelectionQuoteId +
    "&iLemmaId=" + iLemmaId + "&iStartPos=" + iStartPos +
    "&iEndPos=" + iEndPos +
    "&sTokenTuples=" + getTokenTuples() +
    "&iFirstAttestationType=" + iFirstAttestationType +
    "&iDefaultType=" + iDefaultType +
    "&iFirstOnset=" + iFirstOnset +
    "&iFirstOffset=" + iFirstOffset +
    "&sFirstWordForm=" + encodeURIComponent(sFirstWordForm) +
    // NOTE that the type id is used as an indicator for multi typed
    // attestation mode here. That is, if it is defined, where in multi-mode.
    "&bMultiType=" + bMultiType + uniqueString();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 4) {
      var sXmlHttpResponseText = xmlHttp.responseText;
      if( sXmlHttpResponseText != "" ) {
	oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	  sXmlHttpResponseText + sEncapsulatingDivClose;
      }
      else {
	sRevDate = 'now';

	oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	  "Added group." + sEncapsulatingDivClose;

	// Look up a default background color for the group
	var sDefaultGroupBgColor = (iDefaultType)
	? aBackgroundColors[iDefaultType][1] : sDefaultBackgroundColor;

	// We are dealing with an attestation that didn't belong to the group
	// yet, so we give it the groups <sub> part
	// First find out if it is an existing group we're adding to
	if( typeof aAttestationGroup['sSubPart'] == 'undefined' || ! aAttestationGroup['sSubPart'].length ) { // If not
	  // Make a new sub part
	  aAttestationGroup['sSubPart'] =
	    "<sub>" + findNewGroupIndex(iSelectionQuoteId) + "</sub>";
	  // Give the original one the sub part as well
	  var oFirstOne = document.getElementById('att_' + iSelectionQuoteId +
						  '_' + iStartPos);
	  oFirstOne.innerHTML += aAttestationGroup['sSubPart'];
	  // Give it the right backgroundColor if it had none
	  if( ! oFirstOne.style.backgroundColor )
	    oFirstOne.style.backgroundColor = sDefaultGroupBgColor;
	}

	var iDiff = (iStartPos < iEndPos) ? 1 : -1;
	// NOTE that we don't take the first one (it's done above)
	var oToken;
	for(var i = (iStartPos + iDiff); i != (iEndPos + iDiff); i += iDiff) {
	  oToken = document.getElementById('att_' + iSelectionQuoteId +
					       '_' + i);
	  if( ! oToken.style.backgroundColor )
	    oToken.style.backgroundColor = sDefaultGroupBgColor;

	  // Add an index, unless it was a group member already
	  if( ! oToken.innerHTML.match(/(<sub>\d+<\/sub>)/i) )
	    oToken.innerHTML += aAttestationGroup['sSubPart'];
	}

	// add for auto attestation (oToken is the last object from previous loop)
	// iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset
	// <TAB>sWordform
	addToNewWords(oToken, oToken.title.split("\t")[5],
		      oToken.style.backgroundColor);

	// Deselect any selected (group)attestations
	// (iCurSelQuote must have been set to this quotation before)
	deselectAllAttestations(aQuotationIds[iCurSelQuote]);

	// Select this attestation and put the other group members to
	// 'highlighted_group'
	// oToken is still the last one
	selectAttestation(oToken, iEndPos, false);

	// We are not in an attestation group anymore
	aAttestationGroup = [];

	// Something might have changed
	updateStatisticsOnScreen();
      }
    }
  }
  
  xmlHttp.open("GET", sFile, true);
  
  // The null is essential to Firefox, which will otherwise never return...
  xmlHttp.send(null);
}

function ajaxCall_addGroupAttestation(oAttestation, iQuotationId, iLemmaId,
				      iPos, iOnset, iOffset, sWordForm,
				      iTypeId) {
  var oAjaxDiv = document.getElementById('ajaxDiv');

  var xmlHttp = getXMLHtppRequestObject();

  var sFile = "./php/addGroupAttestation.php?sDatabase=" + sDatabase +
    "&iUserId=" + iUserId + "&iGroupOnset=" + aAttestationGroup['iOnset'] +
    "&iGroupPos=" + aAttestationGroup['iPos'] +
    "&iGroupQuotationId=" + aAttestationGroup['iQuotationId'] +
    "&sClassName=" + oAttestation.className + "&iQuotationId=" + iQuotationId +
    "&iLemmaId=" + iLemmaId + "&iPos=" + iPos + "&iOnset=" + iOnset +
    "&iOffset=" + iOffset + "&sWordForm=" + sWordForm +
    // NOTE that the type id is used as an indicator for multi typed
    // attestation mode here. That is, if it is defined, where in multi-mode.
    "&iTypeId=" + iTypeId + uniqueString();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 4) {
      var sXmlHttpResponseText = xmlHttp.responseText;
      if( sXmlHttpResponseText != "" ) {
	oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	  sXmlHttpResponseText + sEncapsulatingDivClose;
      }
      else {
	sRevDate = 'now';

	oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	  "Added '" + sWordForm + "' to group." + sEncapsulatingDivClose;

	// If the current one was not attested yet (i.e. it has no background
	// color yet) then set the background color. Either to the default one,
	// in the 'normal' case or the same background color as the first
	// alt-clicked one had in multi-type case
	if( ! oAttestation.style.backgroundColor )
	  oAttestation.style.backgroundColor = (iTypeId)
	    ? aBackgroundColors[iTypeId][1] : sDefaultBackgroundColor;
	// We are dealing with an attestation that didn't belong to the group
	// yet, so we give it the groups <sub> part
	// First find out if it is an existing group we're adding to
	if( ! aAttestationGroup['sSubPart'].length ) { // If not
	  // Make a new sub part
	  aAttestationGroup['sSubPart'] = "<sub>" +
	    findNewGroupIndex(aAttestationGroup['iQuotationId']) + "</sub>";
	  // Give the original one the sub part as well
	  document.getElementById('att_' + aAttestationGroup['iQuotationId'] +
				  '_' + aAttestationGroup['iPos']).innerHTML +=
	    aAttestationGroup['sSubPart'];
	}

	oAttestation.innerHTML += aAttestationGroup['sSubPart'];

	addToNewWords(oAttestation,
		      sWordForm, oAttestation.style.backgroundColor)

	// Deselect the currently selected one, in case that one didn't belong
	// to the group (which can be...)
	if( iCurSelAtt != iPos)
	  document.getElementById('att_' + iQuotationId + '_' +
				  iCurSelAtt).className = 'highlighted';

	// Select this attestation and put the other group members to
	// 'highlighted_group'
	selectAttestation(oAttestation, iPos, false);

	// Something might have changed
	updateStatisticsOnScreen();
      }
    }
  }
  
  xmlHttp.open("GET", sFile, true);
	
  // The null is essential to Firefox, which will otherwise never return...
  xmlHttp.send(null);
}

// This function finds the first available index to put in the <sub> part
// of attestations belonging to a new group.
function findNewGroupIndex(iQuotationId) {
  var i = 0;
  var obj = document.getElementById('att_' + iQuotationId + '_' + i);
  var aMatches;
  var aExistingGroupIndices = new Array();
  while(obj) {
    /// NOT necessarily at the end, as some browser may shuffle tags for you
    /// (in case there happen to be more tags...).
    ///aMatches = obj.innerHTML.match(/<sub>(\d+)<\/sub>$/i);
    aMatches = obj.innerHTML.match(/<sub>(\d+)<\/sub>/i);
    if( aMatches && aMatches.length)
      aExistingGroupIndices[parseInt(aMatches[1])] = 1;
    // Next attestation
    i++;
    obj = document.getElementById('att_' + iQuotationId + '_' + i);
  }
  // return findFirstAvailable(aExistingGroupIndices);
  
  if( aExistingGroupIndices.length) {
    // NOTE that the loop will not stop on its own condition
    for(i = 1; i > 0; i++ ) {
      if(aExistingGroupIndices[parseInt(i)] === undefined)
	return i;
    }
  } // No existing groups yet
  return 1;
}

function fillAttestationsDiv(iLemmaId, sPrevNext, sLastDate,
			     iSelectedQuotationId) {
  var iOldScrollTop = document.body.scrollTop;
  var iPreviouslySelectedAtt = iCurSelAtt;
  var iPreviouslySelectedQuote = iCurSelQuote;

  // Always empty the ajaxDiv, which might contain previous messages
  // Reserve some room so the page won't alter too much when a message
  // displayed
  document.getElementById('ajaxDiv').innerHTML =
      "<div class=noMessage>&nbsp;</div>";
  // Empty the group array
  aAttestationGroup = [];
  // Set the interval for updating the statistics
  // If we already have an interval, clear that one
  if( iIntervalId )
    clearInterval(iIntervalId);
  // Set a (new) interval
  iIntervalId = setInterval( 'fillTotalStats()', 10000 );

  // Get the attestations div
  var oAttestationsDiv = document.getElementById('attestationsDiv');

  var xmlHttp = getXMLHtppRequestObject();

  // Build the right url
  var sFile = "./php/getAttestations.php?sDatabase=" + sDatabase +
    "&sUser=" + sUser + "&iUserId=" + iUserId + uniqueString();
  if( iLemmaId )
    sFile += "&iLemmaId=" + iLemmaId;
  if( sPrevNext )
    sFile += "&sPrevNext=" + sPrevNext;
  if( sLastDate )
    sFile += "&sLastDate=" + sLastDate;

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 0 ) {
      oAttestationsDiv.innerHTML = 'There was an error in loading';
    }
    if(xmlHttp.readyState == 1) {
      oAttestationsDiv.innerHTML ="Loading...";
    }
    if( xmlHttp.readyState == 2) {
      oAttestationsDiv.innerHTML ="The request has been sent";
    }
    if( xmlHttp.readyState == 3) {
      oAttestationsDiv.innerHTML = "The request is in process";
    }
    if(xmlHttp.readyState == 4) {
      var sXmlHttpResponseText = xmlHttp.responseText;
      var iLastIndex = sXmlHttpResponseText.lastIndexOf('\n');
      // NOTE that we eval() the last line of the response here.
      oAttestationsDiv.innerHTML =
                                 sXmlHttpResponseText.substring(0, iLastIndex);
      eval(sXmlHttpResponseText.substring(iLastIndex) );
      // When that is finished, initialize the rest
      // Somehow the global iLemmaId will not hold,
      // so we set it explicitely again.
      init(iLemmaId);
      // Put the page back at the top
      if( iSelectedQuotationId === false )
	window.scrollTo(0, 0);
      else {
	unselectCurrentQuotation();
	deselectAllAttestations(aQuotationIds[iCurSelQuote]);
	// Select this quotation
	document.getElementById('citaat_' +
				iSelectedQuotationId).className =
	  'citaat_selected';
	iCurSelQuote = iPreviouslySelectedQuote;
	selectAttestation(document.getElementById('att_' +
						  iSelectedQuotationId + '_' +
						  iPreviouslySelectedAtt),
			  iPreviouslySelectedAtt,
			  false);
      }
      
      // always show the attestedSoFar
      displayAttestedSoFar(iLemmaId);
    }
  }
  
  xmlHttp.open("GET", sFile, true);
	
  // The null is essential to Firefox, which will otherwise never return...
  xmlHttp.send(null);
}

// Add a normal attestation
function insertAttestation(oAttestation, iPos, iQuotationId, iLemmaId,
			   iNewOnset, iNewOffset, sNewWordForm) {
  ajaxCall_insertAttestation(oAttestation, iPos,
    "./php/addAttestation.php?iQuotationId=" + iQuotationId +
    "&iLemmaId=" + iLemmaId + "&iNewOnset=" + iNewOnset +
    "&iNewOffset=" + iNewOffset +
    "&sNewWordForm=" + encodeURIComponent(sNewWordForm) +
    "&sDatabase=" + sDatabase +
    "&iUserId=" + iUserId + uniqueString(), sNewWordForm, 0);
}

function speedInsertTypedAttestation(oTypeMenu, iRowNr) {
  var oCurSelAtt = document.getElementById('att_' +
					   aQuotationIds[iCurSelQuote] +
					   '_' + iCurSelAtt);
  if( oCurSelAtt ) {
    // iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset
    // <TAB>sWordform
    var aParams = oCurSelAtt.title.split("\t");
    fillTypeMenu(oCurSelAtt, oTypeMenu, aParams[2], aParams[0], aParams[1],
		 aParams[3], aParams[4], aParams[5], 'hidden');

    iSelectedTypeRow = iRowNr;
    insertTypedAttestation();
  }
}

// Add a typed attestation
function insertTypedAttestation() {

   // Normally, the index of the selected row was already computed at mouse over,
   // when the mouse was pointing a type row.

   // Special case:
   // if some comment was typed in within the types menu (the comment field is the last row of the menu),
   // we have to look up the index of this row in the types menu 
   if (sAttestationComment != "")
   {
	   // go through the rows and get the index of the one which is the user comment
	   var eTypeMenu = document.getElementById('typeMenu');
	   //var aDivjes = eTypeMenu.getElementsByTag("div");
		for (var i = 0; i < eTypeMenu.children.length; i++)
		{
			if (eTypeMenu.children[i].id = 'typeMenuComment')
				{ iSelectedTypeRow = i; }
		}
   }		 

  // If e.g. somebody hits enter when nothing was selected, we don't do
  // anything...
  if( iSelectedTypeRow == -1) {
    hideTypeMenu();
    return 0;
  }

  // The title attribute of the selected row in the menu holds all parameters
  // in a tab separated string:
  // iPos<TAB>iQuotationId<TAB>iLemmaId<TAB>iOnset<TAB>iOffset
  // <TAB>sWordForm<TAB>iTypeId
  var aParams =
	document.getElementById('typeMenu').children[iSelectedTypeRow].title.split("\t");

  ajaxCall_insertAttestation(document.getElementById('att_' + aParams[1] +
						     '_' + aParams[0]), 
			     aParams[0],
			     "./php/addAttestation.php?"+
				 "iQuotationId=" +
			     aParams[1] +
			     "&iLemmaId=" + aParams[2] + "&iNewOnset=" +
			     aParams[3] + "&iNewOffset=" + aParams[4] +
			     "&sNewWordForm=" + aParams[5] + "&iTypeId=" +
			     aParams[6] + "&sDatabase=" +
			     sDatabase + "&iUserId=" + iUserId +
				 ( sAttestationComment != "" ? "&sAttestationComment=" + sAttestationComment : "")+
			     uniqueString(), aParams[5], aParams[6]);
}



function ajaxCall_insertAttestation(oAttestation, iPos, sFile, sNewWordForm, iTypeId) {
  var oAjaxDiv = document.getElementById('ajaxDiv');

  
  // reset the value of the attestation user comment (because it has been sent to the server now)
  sAttestationComment = "";
  
  // This function is called everytime something changes
  // hence the revision date can not be 'unknown' anymore
  sRevDate = 'now';

  var xmlHttp = getXMLHtppRequestObject();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 0 ) {
      oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
       "The request is not initialized" + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 1) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + "Loading..." +
	sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 2) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + 
	"The request has been sent" + sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 3) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	"The request is in process" + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 4) {
      var sXmlHttpResponseText = xmlHttp.responseText;
      if( sXmlHttpResponseText == '') {
        oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + 
          "Attestation added to quotation " + sEncapsulatingDivClose;

	// Deselect any selected (group)attestations
	// (iCurSelQuote must have been set to this quotation before)
	deselectAllAttestations(aQuotationIds[iCurSelQuote]);

	// If we are doing a typed attestation, the background depends
	var sBackgroundColor = (iTypeId) ?
	  aBackgroundColors[iTypeId][1] : sDefaultBackgroundColor;

	addToNewWords(oAttestation, sNewWordForm, sBackgroundColor);

	selectAttestation(oAttestation, iPos, sBackgroundColor);

	if( iTypeId ) // Is 0 in the 'normal'/not multi type case
	  hideTypeMenu();

	// Something might have changed
	updateStatisticsOnScreen();
      }
      else
        oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
          sXmlHttpResponseText + sEncapsulatingDivClose;
    }
  }
  
  xmlHttp.open("GET", sFile, true);
	
  // The null is essential to Firefox, which will otherwise never return.
  xmlHttp.send(null);
}

// Remove an attestation
function deleteAttestation(oAttestationsDiv, iQuotationId, iLemmaId, iAttPos) {
  // In case we were editing a group, we just stopped doing so.
  aAttestationGroup = [];

  // iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset<TAB>sWordform
  var aParams = document.getElementById('att_' + iQuotationId + '_' +
					   iAttPos).title.split("\t");
  ajaxCall_deleteAttestation(
	   "./php/deleteAttestation.php?iQuotationId=" + iQuotationId +
	   "&iLemmaId=" + iLemmaId +
	   "&iOnset=" + aParams[3] + "&sDatabase=" + sDatabase +
	   "&iUserId=" + iUserId + uniqueString(), oAttestationsDiv,
	   aParams[5], iAttPos);
}

function ajaxCall_deleteAttestation(sFile, oAttestationsDiv, sWordForm,
				    iAttPos) {
  var oAjaxDiv = document.getElementById('ajaxDiv');

  // This function is called everytime something changes
  // hence the revision date can not be 'unknown' anymore
  sRevDate = 'now';

  var xmlHttp = getXMLHtppRequestObject();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 0 ) {
      oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	"The request is not initialized" + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 1) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + "Loading..." +
	sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 2) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + 
	"The request has been sent" + sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 3) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	"The request is in process" + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 4) {
      var xmlHttpResponseText = xmlHttp.responseText;
      if(xmlHttpResponseText == '') {
	// Deselect the current selection
	oAttestationsDiv.className = 'lowlighted';
	// No background color
	oAttestationsDiv.style.backgroundColor = '';

	var sInnerHTML = oAttestationsDiv.innerHTML;
	// No marks for dubiosity/elliptical/error anymore
	sInnerHTML = sInnerHTML.replace(reDubious, "");
	sInnerHTML = sInnerHTML.replace(reElliptical, "");
	sInnerHTML = sInnerHTML.replace(reErroneous, "");
	// If it was in a group, it had a <sub> part, which has now become
	// void.
	// The /i modifier is there because some browsers (e.g. IE) uppercase
	// the tags for you...
	// We remember the group number to be able to delete the sub part of
	// left over group members later on
	var aMatches = sInnerHTML.match(/(<sub>\d+<\/sub>)/i);
	var reSubPart = false;
	if( aMatches && aMatches.length) {
	  reSubPart = new RegExp(aMatches[1], 'i');

	  // Remove the matched part
	  sInnerHTML = sInnerHTML.replace(/<sub>\d+<\/sub>/i, '');
	}
	// Set the innerHTML again
	oAttestationsDiv.innerHTML = sInnerHTML;

	// Inform the user
	oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + 
	  "Attestation deleted" + sEncapsulatingDivClose;

	// Select the first remaining attestation
	// While also deleting the sub part from the remaining group member
	// if there is only one left
	selectCloseAttestation(aQuotationIds[iCurSelQuote],iAttPos,reSubPart);

	// Something might have changed
	updateStatisticsOnScreen();
      }
      else
	oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	  xmlHttpResponseText + sEncapsulatingDivClose;
    }
  }
  
  xmlHttp.open("GET", sFile, true);
	
  // The null is essential to Firefox, which will otherwise never return...
  xmlHttp.send(null);
}

// Update an attestation
// The arguments might not be ordered in the most intuitive way, but iPos is
// the 'new' position
function moveAttestation(oAttestation, iPos, iQuotationId, iLemmaId, iOldOnset,
			 sOldWordForm, iNewOnset, iNewOffset, sNewWordForm) {
  ajaxCall_moveAttestation(oAttestation, iPos,
			   "./php/moveAttestation.php?iQuotationId=" +
			   iQuotationId + "&iLemmaId=" + iLemmaId +
			   "&iOldOnset=" + iOldOnset + "&iNewOnset=" +
			   iNewOnset + "&iNewOffset=" + iNewOffset +
			   "&sNewWordForm=" + encodeURIComponent(sNewWordForm)
			   + "&iNewPos=" + iPos
			   + "&sDatabase=" + sDatabase + "&iUserId=" + iUserId
			   + uniqueString(),
			   sNewWordForm, sOldWordForm);
}

function ajaxCall_moveAttestation(oAttestation, iPos, sFile, sNewWordForm,
				  sOldWordForm) {
  var oAjaxDiv = document.getElementById('ajaxDiv');

  // This function is called everytime something changes
  // hence the revision date can not be 'unknown' anymore
  sRevDate = 'now';

  var xmlHttp = getXMLHtppRequestObject();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 0 ) {
      oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	"The request is not initialized" + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 1) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + "Loading..." +
	sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 2) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + 
	"The request has been sent" + sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 3) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	"The request is in process" + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 4) {
      var sXmlHttpResponseText = xmlHttp.responseText;
      if( sXmlHttpResponseText == '') {
	oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
          "Changes have been saved" + sEncapsulatingDivClose;
	// Deselect the attestation which is currently selected
	var oCur = document.getElementById('att_' +
					   aQuotationIds[iCurSelQuote] + '_' +
					   iCurSelAtt);
	oCur.className = 'lowlighted';
	// No dubious/elliptical/erroneus anymore
	// (already done in the database)
	var sInnerHTML = oCur.innerHTML;
	// No marks for dubiosity/elliptical/error anymore
	sInnerHTML = sInnerHTML.replace(reDubious, "");
	sInnerHTML = sInnerHTML.replace(reElliptical, "");
	sInnerHTML = sInnerHTML.replace(reErroneous, "");
	// If it was in a group, it had a <sub> part, which has now become
	// void.
	// The /i modifier is there because some browsers (e.g. IE) uppercase
	// the tags for you...
	// We remember the group number to be able to add it to the new
	// attestation
	var aMatches = sInnerHTML.match(/(<sub>\d+<\/sub>)/i);
	var sSubPart = '';
	if(aMatches && aMatches.length) {
	  sSubPart = aMatches[1];

	  // Chop the matched part off
	  sInnerHTML = sInnerHTML.replace(/(<sub>\d+<\/sub>)/i, '');
	}

	// Set the innerHTML again
	oCur.innerHTML = sInnerHTML;

	// Remember the background color
	var sBackgroundColor = oCur.style.backgroundColor;

	addToNewWords(oAttestation, sNewWordForm, sBackgroundColor);

	// Remove the background color from this one
	oCur.style.backgroundColor = '';
      
	// Make it belong to the same group (already done in database)
	oAttestation.innerHTML += sSubPart;
	// Select the new attestation
	selectAttestation(oAttestation, iPos, sBackgroundColor);

	// Something might have changed
	updateStatisticsOnScreen();
      }
      else
        oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	  sXmlHttpResponseText + sEncapsulatingDivClose;
    }
  }
  
  xmlHttp.open("GET", sFile, true);
	
  // The null is essential to Firefox, which will otherwise never return.
  xmlHttp.send(null);
}

// Make sure the lemma is saved as 'revised by' this user
// Is necessary in case nothing was altered
function reviseLemma(iLemmaId) {
  sRevDate = 'now';

  ajaxCall_reviseLemma("./php/reviseLemma.php?iLemmaId=" + iLemmaId +
		       "&sDatabase=" + sDatabase + "&iUserId=" + iUserId +
		       uniqueString());
}

function ajaxCall_reviseLemma(sFile) {
  var oAjaxDiv = document.getElementById('ajaxDiv');

  var xmlHttp = getXMLHtppRequestObject();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 0 ) {
      oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
       "The request is not initialized" + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 1) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + "Loading..." +
	sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 2) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + 
	"The request has been sent" + sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 3) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	"Revising lemma..." + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 4) {
      var sXmlHttpResponseText = xmlHttp.responseText;
      if( sXmlHttpResponseText.length ) {
	oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen + sXmlHttpResponseText
	  + sEncapsulatingDivClose;
      }
      else {
	// Empty the message box
	oAjaxDiv.innerHTML = "<div class=noMessage>&nbsp;</div>";
	// Now the numbers in the database might have changed
	updateStatisticsOnScreen();
      }
    }
  }
  
  xmlHttp.open("GET", sFile, true);
	
  // The null is essential to Firefox, which will otherwise never return.
  xmlHttp.send(null);
}

// Make good look bad (or the other way around)
function toggleBadCitation(iLemmaId, iQuotationId) {
  // Get the little cross div
  var obj = document.getElementById('badCitation_' + iQuotationId);

  if( obj.className == 'goodCitation' ) {
    // Make it bad
    ajaxCall_badCitation("./php/reviseCitation.php?iSpecialAttention=0" +
			 "&iUserId=" + iUserId + "&iLemmaId=" + iLemmaId +
			 "&iQuotationId=" + iQuotationId + "&sDatabase=" +
			 sDatabase + uniqueString(),
			 "Marked as bad quotation", obj,
			 '<img src="./images/badCitation.gif">',
			 'badCitation');
  }
  else { // It was already marked 'bad'
    // Make it good
    ajaxCall_badCitation("./php/reviseCitation.php?iSpecialAttention=1" +
			 "&iUserId=" + iUserId + "&iLemmaId=" + iLemmaId +
			 "&iQuotationId=" + iQuotationId + "&sDatabase=" +
			 sDatabase + uniqueString(),
			 "Marked as good quotation", obj,
			 '<img src="./images/goodCitation.gif">',
			 'goodCitation');
  }
}

function ajaxCall_badCitation(sFile, sMessage, obj, sInnerHTML, sClassName) {
  var oAjaxDiv = document.getElementById('ajaxDiv');

  // This function is called everytime something changes
  // hence the revision date can not be 'unknown' anymore
  sRevDate = 'now';

  var xmlHttp = getXMLHtppRequestObject();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 0 ) {
      oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	"The request is not initialized" + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 1) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + "Loading..." +
	sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 2) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + 
	"The request has been sent" + sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 3) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	"The request is in process" + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 4) {
      var sXmlHttpResponseText = xmlHttp.responseText;
      if( sXmlHttpResponseText == '') {
	// Mark the little cross
	obj.innerHTML = sInnerHTML;
	obj.className = sClassName;
	// Tell the user
        oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + sMessage +
	  sEncapsulatingDivClose;
	// Something might have changed
	updateStatisticsOnScreen();
      }
      else
	oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	  sXmlHttpResponseText + sEncapsulatingDivClose;
    }
  }
  
  xmlHttp.open("GET", sFile, true);
	
  // The null is essential to Firefox, which will otherwise never return.
  xmlHttp.send(null);
}

// Make good look bad (or the other way around)
function toggleUnfortunateCitation(iLemmaId, iQuotationId){
  // Get the little cross div
  var obj = document.getElementById('unfortunate_' + iQuotationId);

  if( obj.className == 'fortunate' ) { // It was marked 'fortunate'
    // Make it unfortunate
    ajaxCall_unfortunate("./php/unfortunateCitation.php?iUnfortunate=1&" +
			 "iUserId=" + iUserId + "&iLemmaId=" + iLemmaId +
			 "&iQuotationId=" + iQuotationId + "&sDatabase=" +
			 sDatabase + uniqueString(), "Marked as unfortunate",
			 obj, '<img src="./images/unfortunate.gif">',
			 'unfortunate');
  }
  else { // It was already marked 'unfortunate'
    // Make it fortunate
    ajaxCall_unfortunate("./php/unfortunateCitation.php?iUnfortunate=0&" +
			 "iUserId=" + iUserId + "&iLemmaId=" + iLemmaId +
			 "&iQuotationId=" + iQuotationId + "&sDatabase=" +
			 sDatabase + uniqueString(), "Marked as fortunate",
			 obj, '<img src="./images/fortunate.gif">',
			 'fortunate');
  }
}

function ajaxCall_unfortunate(sFile, sMessage, obj, sInnerHTML,
			      sClassName) {
  var oAjaxDiv = document.getElementById('ajaxDiv');

  // This function is called everytime something changes
  // hence the revision date can not be 'unknown' anymore
  sRevDate = 'now';

  var xmlHttp = getXMLHtppRequestObject();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 0 ) {
      oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	"The request is not initialized" + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 1) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + "Loading..." +
	sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 2) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + 
	"The request has been sent" + sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 3) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	"The request is in process" + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 4) {
      var sXmlHttpResponseText = xmlHttp.responseText;
      if( sXmlHttpResponseText == '') {
	oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + sMessage +
	  sEncapsulatingDivClose;

	// Change the icon
	obj.innerHTML = sInnerHTML;
	obj.className = sClassName;

	// Something might have changed
	updateStatisticsOnScreen();
      }
      else
	oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	  sXmlHttpResponseText + sEncapsulatingDivClose;
    }
  }
  
  xmlHttp.open("GET", sFile, true);
	
  // The null is essential to Firefox, which will otherwise never return.
  xmlHttp.send(null);
}

// The subscript is scored in a html string as 'SomeWord<sub>1</sub>'
function getGroupSubscript(oToken) {

	var innerHtmlStr = (oToken.innerHTML).trim().replace(new RegExp("^[^\<]+"), ""); // remove part before '<'
	return innerHtmlStr;
}

// Automatically update all the quotes with new words the user attested.
function autoAttest(iLemmaId) {
  
  if( oLatestAttestation ) {
    // If we are doing typed attestations we have to find out the type of the
    // last attestation. We do this by using its background color to look up
    // its type.
    sTypeIdPart = (document.getElementById('typeMenu')) ?
      "&iTypeId=" + aBackgroundColors2TypeId[hexColor(sLatestBackgroundColor)]
      : '';

    var sInnerHTML = oLatestAttestation.innerHTML;
    var bDubious = ( sInnerHTML.match(reDubious) ) ? 1 : 0;
    var bElliptical = ( sInnerHTML.match(reElliptical) ) ? 1 : 0;
    var bErroneous = ( sInnerHTML.match(reErroneous) ) ? 1 : 0;
	
	
	
	// get the whole attestation (as we might have a group of word instead of a single word)
	var iQuoteIdOfLatestAttestedWord = oLatestAttestation.id.split("_")[1];
	var iIndexOfLatestAttestedWord   = parseInt(oLatestAttestation.id.split("_")[2]);
	var i = iIndexOfLatestAttestedWord - 1;
	var bIsAGroup = oLatestAttestation.innerHTML.match(/(<sub>\d+<\/sub>)/i) ? 1 : 0;;
	var sGroupIndex = bIsAGroup ? getGroupSubscript(oLatestAttestation) : "";	
	
	
	// add the words in front
	while (i>0) 
	{
		oToken = document.getElementById('att_' + iQuoteIdOfLatestAttestedWord + '_' + i);
		var sGroupIndexFront = getGroupSubscript(oToken);
		// if we find a front part of the group, add it in front
		// (that is: highlighted, and sharing same group subscribt if we have a group)
		
		if (oToken.className != 'lowlighted' && ( !bIsAGroup || ( bIsAGroup && sGroupIndexFront == sGroupIndex ) ) )
			{
			sLatestAttestation = oToken.title.split("\t")[5] + "@@@" + sLatestAttestation;
			}
		// otherwise stop right away
		else
			{
			i=0; 
			}
		i--;
	}	
	// add the words in back
	i = iIndexOfLatestAttestedWord + 1;
	var max = oToken.parentNode.getElementsByTagName("span").length;	
	while (i<max)
	{
		oToken = document.getElementById('att_' + iQuoteIdOfLatestAttestedWord + '_' + i);
		var sGroupIndexBack = getGroupSubscript(oToken);		
		// if we find a back part of the group, add it in the back
		// (that is: highlighted, and sharing same group subscribt if we have a group)
		
		if (oToken.className != 'lowlighted' && ( !bIsAGroup || ( bIsAGroup && sGroupIndexBack == sGroupIndex ) ) )
			{
			sLatestAttestation += "@@@" + oToken.title.split("\t")[5];
			}
		// otherwise stop right away
		else
			{
			i=max; 
			}
		i++;
	}
	

    // We pass the latest background color as an argument rather than taking
    // the global one, because it is reset afterwards, while the
    // (a-synchronous) call has not finished yet
    ajaxCall_autoAttest("./php/autoAttest.php?sDatabase=" + sDatabase + 
			"&iUserId=" + iUserId + "&iLemmaId=" + iLemmaId +
			"&sIdOfLatestAttestedWord=" + oLatestAttestation.id +
			"&sNewWord=" + encodeURIComponent(sLatestAttestation) +
			sTypeIdPart + "&bDubious=" + bDubious +
			"&bElliptical=" + bElliptical + "&bErroneous=" +
			bErroneous + uniqueString(), sLatestBackgroundColor,
			bDubious, bElliptical, bErroneous);
    sLatestAttestation = '';
    sLatestBackgroundColor = '';
    oLatestAttestation = 0;
  }
  else
    alert("Nothing to auto attest");
}

function ajaxCall_autoAttest(sFile, sBackgroundColor, bDubious, bElliptical,
			     bErroneous) {
  var oAjaxDiv = document.getElementById("ajaxDiv");

  var xmlHttp = getXMLHtppRequestObject();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 0 ) {
      oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	"The request is not initialized" + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 1) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + "Auto attesting" +
	sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 2) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + 
	"The auto attestation request has been sent" + sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 3) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	"Auto attesting..." + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 4) {
      if( xmlHttp.responseText.match(/^att_\d+_\d+/) ) {
	  
		var respText = xmlHttp.responseText;
		
		higlightNewWords( respText.replace(/\|/g, ","), sBackgroundColor, bDubious,
			 bElliptical, bErroneous,
			 function(){
			 
				oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
				"Done auto attesting" + sEncapsulatingDivClose;
		
				bAutoAttest = true; // tell the other functions we are in autoattest mode (global variable)
				
				buildGroupsInAutoAttest(respText, function(){
				
					// Something might have changed
					updateStatisticsOnScreen();
					
					bAutoAttest = false; // tell the other functions we are not anymore in autoattest mode (global variable)
					});
			 });
				
		
	
		
      }
	  else if ( xmlHttp.responseText == '' ) {
		// do nothing if there is no response at all (which might mean there is nothing else to auto-attest, but no mistake at all)
	  }
      else {
	oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen + 
	"Something went wrong in auto attesting<p>" + 
	xmlHttp.responseText + sEncapsulatingDivClose;
      }
    }
  }
  
  xmlHttp.open("GET", sFile, true);
  
  // The null is essential to Firefox, which will otherwise never return.
  xmlHttp.send(null);
}


// build the groups in auto attest, by firing the functions that are normally fired when
// making a group manually (start-, addto-, endSelection)
function buildGroupsInAutoAttest(respText, fnCallback){

	var aGroups = respText.split("\|");
	
	_buildGroupsInAutoAttest( aGroups, 0, aGroups.length, fnCallback );
}

// build the different groups (loops)
function _buildGroupsInAutoAttest( aGroups, iIndex, iMax, fnCallback ){
	
	var aGroupMembers = aGroups[iIndex].split(",");
	
	// build a single group, if we have one
	if (aGroupMembers.length > 1)
		_buildOneGroupInAutoAttest( aGroupMembers, 0, aGroupMembers.length );
	
	// next loop
	var iDelay = 500; // 500 is proven to be optimal, e.g. 200 isn't
	
	if (iIndex+1 < iMax)
		setTimeout(function(){_buildGroupsInAutoAttest(aGroups, (iIndex+1), iMax, fnCallback);}, iDelay );
	else
		setTimeout(function(){fnCallback();}, iDelay);
}


// build one single group
function _buildOneGroupInAutoAttest( aGroupMembers, iIndex, iMax ){

	var newObj = new Object();
	newObj.id = aGroupMembers[iIndex];
				
	if (iIndex == 0)
		startSelection(newObj)   // first member
	else
		addToSelection(newObj);  // following members

	// next loop
	if (iIndex+1 < iMax)
		_buildOneGroupInAutoAttest( aGroupMembers, iIndex+1, iMax )
	else
		endSelection();          // last member
}



// Automatically update all the quotes with new words the user attested.
function autoDeAttest(iLemmaId) {
  var oCurSelAtt = document.getElementById('att_' +
					   aQuotationIds[iCurSelQuote] + '_'
					   + iCurSelAtt);

  if( oCurSelAtt ) {
  
  // get the whole attestation (as we might have a group of word instead of a single word)
	var iQuoteIdOfLatestAttestedWord = oCurSelAtt.id.split("_")[1];
	var iIndexOfLatestAttestedWord   = parseInt(oCurSelAtt.id.split("_")[2]);
	var i = iIndexOfLatestAttestedWord - 1;
	var bIsAGroup = oCurSelAtt.innerHTML.match(/(<sub>\d+<\/sub>)/i) ? 1 : 0;
	var sGroupIndex = bIsAGroup ? getGroupSubscript(oCurSelAtt) : "";	
	
	// add the words in front
	while (i>0) 
	{
		oToken = document.getElementById('att_' + iQuoteIdOfLatestAttestedWord + '_' + i);
		var sGroupIndexFront = getGroupSubscript(oToken);
		// if we have a part of the group, add it in front
		// (that is: highlighted, and sharing same group subscribt if we have a group)
		if (oToken.className != 'lowlighted' && ( !bIsAGroup || ( bIsAGroup && sGroupIndexFront == sGroupIndex ) ) )
			{
			sLatestAttestation = oToken.title.split("\t")[5] + "@@@" + sLatestAttestation;
			}
		// otherwise stop right away
		else
			{
			i=0; 
			}
		i--;
	}	
	// add the words in back
	i = iIndexOfLatestAttestedWord + 1;
	oToken = document.getElementById('att_' + iQuoteIdOfLatestAttestedWord + '_0');
	var max = oToken.parentNode.getElementsByTagName("span").length;	
	while (i<max)
	{
		oToken = document.getElementById('att_' + iQuoteIdOfLatestAttestedWord + '_' + i);
		var sGroupIndexBack = getGroupSubscript(oToken);		
		// if we have a part of the group, add it in the back
		// (that is: highlighted, and sharing same group subscribt if we have a group)
		if (oToken.className != 'lowlighted' && ( !bIsAGroup || ( bIsAGroup && sGroupIndexBack == sGroupIndex ) ) )
			{
			sLatestAttestation += "@@@" + oToken.title.split("\t")[5];
			}
		// otherwise stop right away
		else
			{
			i=max; 
			}
		i++;
	}
  
  
    // We get the word form from the title in which it is the last column
    // of six (tab separated)
	if (sLatestAttestation.trim() != '')
		{
		ajaxCall_autoDeAttest("./php/autoDeAttest.php?sDatabase=" + sDatabase + 
				  "&iUserId=" + iUserId + "&iLemmaId=" + iLemmaId +
				  "&sWordToBeDeAttested=" + encodeURIComponent(sLatestAttestation) +
				  "&iQuotationId=" + aQuotationIds[iCurSelQuote] +
				  "&iOnset=" + oCurSelAtt.title.split("\t")[3] +
				  uniqueString());
		}
    sLatestAttestation = '';
    sLatestBackgroundColor = '';
    oLatestAttestation = 0;
  }
  else
    alert("Nothing to auto attest");
}

function ajaxCall_autoDeAttest(sFile) {
  var oAjaxDiv = document.getElementById("ajaxDiv");

  var xmlHttp = getXMLHtppRequestObject();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 0 ) {
      oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen +
	"The request is not initialized" + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 1) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
      "Auto de-attesting..." + sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 2) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen + 
	"The auto de-attestation request has been sent" +
      sEncapsulatingDivClose;
    }
    if( xmlHttp.readyState == 3) {
      oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
      "Auto de-attesting..." + sEncapsulatingDivClose;
    }
    if(xmlHttp.readyState == 4) {
      /// alert(xmlHttp.responseText);
      if( xmlHttp.responseText.match(/^att_\d+_\d+/) ) {
	  
	var respText = xmlHttp.responseText;
	  
	deleteInAutoDeAttest(respText);
	  
	unhighlightWords(respText);
	oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	  "Done auto de-attesting" + sEncapsulatingDivClose;
	
	// Something might have changed
	updateStatisticsOnScreen();

	// No attestation selected anymore
	iCurSelAtt = -1;
      }
      else {
	if( xmlHttp.responseText.length) { // Error
	  oAjaxDiv.innerHTML = sEncapsulatingErrorDivOpen + 
	  "Something went wrong in auto de-attesting<p>" + 
	  xmlHttp.responseText + sEncapsulatingDivClose;
	}
	else { // Nothing returned
	  oAjaxDiv.innerHTML = sEncapsulatingMessageDivOpen +
	  "Nothing was auto de-attested" + sEncapsulatingDivClose;
	}
      }
    }
  }
  
  xmlHttp.open("GET", sFile, true);
    
  // The null is essential to Firefox, which will otherwise never return.
  xmlHttp.send(null);
}

function splitToken(sSplitToken, iQuotationId, iOnset, bDubiosity, bElliptical,
		    bError, iTypeId) {
  var xmlHttp = getXMLHtppRequestObject();

  sFile = "./php/splitToken.php?sDatabase=" + sDatabase + "&iLemmaId=" +
    iLemmaId + "&iUserId=" + iUserId + "&sSplitToken=" +
    encodeURIComponent(sSplitToken) + "&iQuotationId=" + iQuotationId +
    "&iOnset=" + iOnset + "&bDubiosity=" + bDubiosity + "&bElliptical=" +
    bElliptical + "&bError=" + bError + "&iTypeId=" + iTypeId + uniqueString();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 4) {
      if( xmlHttp.responseText ) {
	  document.getElementById("ajaxDiv").innerHTML =
	  sEncapsulatingErrorDivOpen + xmlHttp.responseText +
	  "<p>Something went wrong in splitting the token." + 
	  sEncapsulatingDivClose;
      }
      else { // Reload
	fillAttestationsDiv(iLemmaId, false, false, iQuotationId);
	// Put the keys back
	document.onkeydown = keyDown;
      }
    }
  }
  
  xmlHttp.open("GET", sFile, true);
	
  // The null is essential to Firefox, which will otherwise never return.
  xmlHttp.send(null);
}

function changeOEDIcon(sSuffix) {
  document['oedIcon'].src = "./images/OED_icon" + sSuffix + ".png";
}

function unlockLemma(iLemmaId) {
  ajaxCall("./php/unlockLemma.php?sDatabase=" + sDatabase + "&iLemmaId=" +
	   iLemmaId + uniqueString(),
	   "ajaxDiv", "", false);
}

function displayAttestedSoFar(iLemmaId) {
  ajaxCall("./php/attestedSoFar.php?sDatabase=" + sDatabase + "&iLemmaId=" +
	   iLemmaId + uniqueString(), "attestedSoFar",
	   "Nothing attested yet...", true);
}

function hideAttestedSoFar() {
  document.getElementById('attestedSoFar').innerHTML = ">&nbsp;&nbsp;&nbsp;";
}

function windowHeight() {
  if(navigator.appName=="Netscape")
    return window.innerHeight;
  return document.body.offsetHeight;
}

function sleep(milliseconds) {
  var start = new Date().getTime();
  for (var i = 0; i < 1e7; i++) {
    if ((new Date().getTime() - start) > milliseconds){
      break;
    }
  }
}
