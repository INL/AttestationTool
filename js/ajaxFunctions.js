// AJAX code //

// Got the AJAX code from http://www.w3schools.com/ajax/ajax_browsers.asp
function getXMLHtppRequestObject() {
  var xmlHttp;
  try {
    // Firefox, Opera 8.0+, Safari
    xmlHttp=new XMLHttpRequest();
  }
  catch (e) {
    // Internet Explorer
    try {
      xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
    }
    catch (e) {
      try {
	xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
      }
      catch (e) {
	alert("Your browser does not support AJAX!");
	return false;
      }
    }
  }
  return xmlHttp;
}

function ajaxCall(sFile, sDiv, sMessage, bSilent) {
  var oAjaxDiv;
  var bOutput = sMessage.length;
  // NOTE that the next three are not the globally defined ones
  // (which are called sEncapsulating..etc.)
  var sMessageDivOpen = '';
  var sErrorDivOpen = '';
  var sDivClose = '';
  if( bOutput ) {
    oAjaxDiv = document.getElementById(sDiv);
    if( sDiv == 'ajaxDiv') {
      sMessageDivOpen = "<div class=message>";
      sErrorDivOpen = "<div class=error>";
      sDivClose = "</div>";
    }
  }

  var xmlHttp = getXMLHtppRequestObject();

  // (Re)define the onreadystatechange function
  // We have to do this everytime because the message changes every time
  xmlHttp.onreadystatechange = function() {
    if(xmlHttp.readyState == 0 ) {
      if( bOutput && (! bSilent) ) 
	oAjaxDiv.innerHTML = sErrorDivOpen +
	  "The request is not initialized" + sDivClose;
    }
    if( (! bSilent) && xmlHttp.readyState == 1) {
      if( bOutput && ! bSilent) 
	oAjaxDiv.innerHTML = sMessageDivOpen + "Loading..." +
	  sDivClose;
    }
    if( (! bSilent) && xmlHttp.readyState == 2) {
      if( bOutput && ! bSilent) 
	oAjaxDiv.innerHTML = sMessageDivOpen + 
	  "The request has been sent" + sDivClose;
    }
    if( (! bSilent) && xmlHttp.readyState == 3) {
      if( bOutput && ! bSilent) 
	oAjaxDiv.innerHTML = sMessageDivOpen +
	  "The request is in process" + sDivClose;
    }
    if(xmlHttp.readyState == 4) {
      if( bOutput )
	if( xmlHttp.responseText == '')
	  oAjaxDiv.innerHTML = sMessageDivOpen + sMessage +
	    sDivClose;
	else
	  oAjaxDiv.innerHTML = sErrorDivOpen +
	    xmlHttp.responseText + sDivClose;
    }
  }
  
  xmlHttp.open("GET", sFile, true);
	
  // The null is essential to Firefox, which will otherwise never return.
  xmlHttp.send(null);
}
