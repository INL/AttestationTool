

// This function is used to unbind all the bindings of keyDown(). This is e.g.
// handy when somebody starts typing in the 'search by date' box (otherwise if
// someone hits the spacebar, suddenly the next lemma will be shown).
// (call it like this: document.onkeydown = dummyKey;)

function dummyKey (e) {
  // empty function
}


function warnForSaveAndNewButton() {

 if ( !bSaveAndNewButtonPopupAlreadyShown)
	{
		answer = confirm("BEWARE!\n\nThe 'Save & new' button tells the tool you're finished with this page,\nso you won't see it again the next time you log in!\n\nAre you sure that's what you want?");
		// NOTE actually, the user will be able to see the page again, but only if he/she uses the 'previous' button.
		// But if he/she doesn't, the page won't be shown again.
		bSaveAndNewButtonPopupAlreadyShown = true;
		if (!answer)
			return false;
	}
	return true;
	
};

function commentKeydown(e) {
  var keyCode;

  if( window.event ) // Explorer
    keyCode = window.event.keyCode;
  else if (e) // Else
    keyCode = e.which;

  // Enter validates a comment about the lemma 
  if( keyCode == 13 )
    saveComment(document.getElementById('commentInput').value);
}

function tokenSplitKeydown(e, iQuotationId, iOnset, bDubiosity, bElliptical,
			   bError,iTypeId) {
  
  var keyCode;

  if( window.event ) // Explorer
    keyCode = window.event.keyCode;
  else if (e) // Else
    keyCode = e.which;

  // NOTE that this is where we decide what characters are allowed to be typed
  
  // Enter
  if( keyCode == 13 )
    splitToken(document.getElementById('splitTokenBox').value, iQuotationId,
	       iOnset, bDubiosity, bElliptical, bError, iTypeId);
  // Allow for space, left arrow, right arrow 
  return (keyCode == 32 || keyCode == 37 || keyCode == 39);
}

function fileMenuKeyDown(e) {
  var keyCode;

  if( window.event ) // Explorer
    keyCode = window.event.keyCode;
  else if (e) // Else
    keyCode = e.which;
	
	
	// if we are editing the user comment field, ignore key actions
	e = e || window.event;
	var target = e.target || e.srcElement;
	if (typeof target != 'undefined' && target.id == 'usercomment' && keyCode != 13)
		return;
	
	// check if the striken key is known in the hash mapping  types shortcuts to row indexes (which is linked to types)
	sKey = String.fromCharCode(keyCode).toLowerCase();
	if (typeof hKeyToSelectedrowindex != 'undefined' && typeof hKeyToSelectedrowindex[sKey] != 'undefined')
		{
		iSelectedTypeRow = parseInt(hKeyToSelectedrowindex[sKey]);
		insertTypedAttestation();
		return;
		}
  
  switch(keyCode) {
  
  // ==== OLD, but still here for backward compatibility ====
  case 80:  // 'p' -> PERS
    iSelectedTypeRow = 0;
    insertTypedAttestation();
    break;
  case 79: // 'o' -> ORG
    iSelectedTypeRow = 1;
    insertTypedAttestation();
    break;  
  case 76: // 'l' -> LOC
    iSelectedTypeRow = 2;
    insertTypedAttestation();
    break;
  case 78: // 'n' -> NOT KNOWN
    iSelectedTypeRow = 3;
    insertTypedAttestation();
    break;
	// ==== end of OLD ====
	
	
  case 40: // 'down arrow
    iSelectedTypeRow = Math.min((aBackgroundColors.length/2) -1,
				iSelectedTypeRow + 1);
    selectTypeMenuRow();
    break;
  case 38: // up arrow
    iSelectedTypeRow = (iSelectedTypeRow == -1)
      ? (aBackgroundColors.length/2) -1 : Math.max(0, iSelectedTypeRow -1);
    selectTypeMenuRow();
    break;
  case 13: // 'Return'
    sAttestationComment = document.getElementById('usercomment').value;
	insertTypedAttestation();
    break;
  }
}

function keyUp (e) {
  var keyCode;

  if( window.event ) // Explorer
    keyCode = window.event.keyCode;
  else if (e) // Else
    keyCode = e.which;
  
  switch(keyCode) {
  case 192:  // ` (backtick) end group attestation
    bGroupKeyPressed = 0;
    break;
	
	// === OLD ===
  case 80: // 'p'
  case 79: // 'o'
  case 78: // 'n'
    // === OLD ===
  
  case 76: // 'l'
    iPreSelectedTypeRow = -1;
    break;
  case 49: // '1' (which is next to the `)
    bSelectKeyPressed = 0;
    break;
  case 17 : // Control
    bCtrlKeyDown = false;
    break;
  case 16 : // Shift
    bShiftKeyDown = false;
    break;
  }
}

function keyDown (e) {
  var keyCode;

  if( window.event ) // Explorer
    keyCode = window.event.keyCode;
  else if (e) // Else
    keyCode = e.which;

  switch(keyCode) {
  case 16 : // Shift
    bShiftKeyDown = true;
    break;
  case 17 : // Control
    bCtrlKeyDown = true;
    break;

  }

  if( bCtrlKeyDown || bShiftKeyDown )
    return;
	
	
	// check if the striken key is known in the hash mapping shortcuts to row indexes (which is linked to types)
	sKey = String.fromCharCode(keyCode).toLowerCase();
	if (typeof hKeyToSelectedrowindex != 'undefined' && typeof hKeyToSelectedrowindex[sKey] != 'undefined')
		{
		iPreSelectedTypeRow = parseInt(hKeyToSelectedrowindex[sKey]);
		return;
		}

  switch(keyCode) {
  
  // === OLD, kept voor backward compatibility ===
  
  case 80:  // 'p' -> PERS. Only in the multi type attestation case
    iPreSelectedTypeRow = 0;
    break;
  case 79: // 'o' -> ORG. Only in the multi type attestation case
    iPreSelectedTypeRow = 1;
    break;
  case 76: // 'l' -> LOC. Only in the multi type attestation case
    iPreSelectedTypeRow = 2;
    break;
  case 78: // 'n' -> NOT KNOWN. Only in the multi type attestation case
    iPreSelectedTypeRow = 3;
    break;
  
  // === end of OLD ===
  
  
  case 77: // 'm' -> Mark lemma.
    markLemma(document.getElementById('lemmaMark'));
    break;
  case 192: // ` -> (if held down) start group attestation
    bGroupKeyPressed = 1;
    break;
  case 220: // '\' -> Backslash, split tokens
    makeCurTokenEditable();
	// no 'break' after a return
    return false; 
  case 69: // 'e' -> part of elliptical expression
    toggleCurrentAttElliptical();
    break;
  case 83: // 's' -> scan error
    toggleCurrentAttError();
    break;
  case 87: // 'w' -> What/Who
    toggleCurrentAttDoubt();
    break;
  
  case 120: // F9
  case 88:  // 'x'
    toggleBadCitation(iLemmaId,aQuotationIds[iCurSelQuote]);
    break;
  case 119: // F8 (used to be the same as Spacebar)
    makeCommentBoxEditable();
    break;
  case 32:  // Spacebar
    // warning first
    if ( !bSpacebarPopupAlreadyShown)
	{
		answer = confirm("BEWARE!\n\nThe spacebar (and the 'Save & new' button) tells the tool you're finished with this page,\nso you won't see it again the next time you log in!\n\nAre you sure that's what you want?");
		// NOTE actually, the user will be able to see the page again, but only if he/she uses the 'previous' button.
		// But if he/she doesn't, the page won't be shown again.
		bSpacebarPopupAlreadyShown = true;
		if (!answer)
			break;
	}
    // Save
    if(sRevDate == 'unknown')
      reviseLemma(iLemmaId);
    // New citation
    fillAttestationsDiv(false, false, false, false);
    break;
  case 115: // F4
  case 70:  // 'f'
    // Next/forward
    // If there is no Next, we don't do anything
    if(bNextButton) {
      fillAttestationsDiv(iLemmaId, 'next', false, false);
    }
    break;
  case 113: // F2
  case 68:  // 'd'
    // Previous/backward
    if( bPrevButton ) {
      // When nothing happened to this lemma, its revision date will not be set
      // yet so we should unlock it.
      if( sRevDate == 'unknown')
	unlockLemma(iLemmaId);
      fillAttestationsDiv(iLemmaId, 'prev', false, false);
    }
    break;
  case 85: // 'u' (unfortunate)
    // Toggle unfortunate citation
    toggleUnfortunateCitation(iLemmaId, aQuotationIds[iCurSelQuote]);
    break;
  case 84: // 't' (menu -> pull down the *t*ype menu if appropriate)
    // Only in the multi type attestation case
    var oTypeMenu = document.getElementById('typeMenu');
    if( oTypeMenu ) {
      var oCurSelAtt = document.getElementById('att_' +
					       aQuotationIds[iCurSelQuote] +
					       '_' + iCurSelAtt);
      // iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset
      // <TAB>sWordform
      var aParams = oCurSelAtt.title.split("\t");
      fillTypeMenu(oCurSelAtt, oTypeMenu, aParams[2], aParams[0], aParams[1],
		   aParams[3], aParams[4], aParams[5], 'visible');
      bClickedInTypeMenu = 0;
    }
    break;
  case 65: // 'a' (auto de-attest = 'z')
    autoAttest(iLemmaId);
    break;
  case 90: // 'z' -> Auto de-attest (auto attest = 'a')
    autoDeAttest(iLemmaId);
    break;
  case 49: // '1' (which is next to the `)
    bSelectKeyPressed = 1;
    break;
  case 46: // Delete
    // Throw the attesation out
    deleteAttestation(document.getElementById('att_' +
					      aQuotationIds[iCurSelQuote] +
					      '_' + iCurSelAtt),
		      aQuotationIds[iCurSelQuote], iLemmaId, iCurSelAtt);
    break;
  case 45: // Insert
    addAttestation(aQuotationIds[iCurSelQuote], iLemmaId);
    break;
  case 40: // Down arrow
    if( (iCurSelQuote + 1) < aQuotationIds.length ) {
      unselectCurrentQuotation();
      iCurSelQuote++; // Next one
      selectCurrentQuotation();
    }
    break;
  case 39: // Right arrow
    if( iCurSelAtt != -1 ) {
      // Find out if it makes sense
      var obj = document.getElementById('att_' + aQuotationIds[iCurSelQuote] +
					'_' + (iCurSelAtt + 1) );
      if( obj ) {
	// iQuotationId<TAB>iLemmaId<TAB>iPos<TAB>iOnset<TAB>iOffset
	// <TAB>sWordform
	var aParams = obj.title.split("\t");
	changeAttestation(aQuotationIds[iCurSelQuote], iLemmaId, iCurSelAtt+ 1,
			  aParams[3], aParams[4], aParams[5]);
      }
    }
    break;
  case 38: // Up arrow
    if( (iCurSelQuote - 1) >= 0 ) {
      unselectCurrentQuotation();
      iCurSelQuote--; // Previous one
      selectCurrentQuotation();
    }
    break;
  case 37: // Left arrow
    if( iCurSelAtt > 0 ) {
      var aParams =
	document.getElementById('att_' + aQuotationIds[iCurSelQuote] + '_' +
				(iCurSelAtt-1)).title.split("\t");

      changeAttestation(aQuotationIds[iCurSelQuote], iLemmaId, iCurSelAtt - 1,
			aParams[3], aParams[4], aParams[5]);
    }
    break;
  case 188: // '<' Previous attestation 
    selectNextAttestation(-1);
    break;
  case 190: // '>' Next attestation
    selectNextAttestation(1);
    break;
  }
}
