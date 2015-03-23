// Some functions having to do with colors

function hexColor(sColor) {
  var aMatches = sColor.match(/rgb\D+(\d+)\D+(\d+)\D+(\d+)/);
  if( aMatches != null ) {
    return RGBtoHex(aMatches[1], aMatches[2], aMatches[3]);
  }
  // It was just #abcdef. We upper case it because IE lowercases it...
  return sColor.toUpperCase();
}

// Got this code from http://www.linuxtopia.org/online_books/javascript_guides/javascript_faq/rgbtohex.htm
function RGBtoHex(sRed, sGreen, sBlue) {
  return '#' + toHex(sRed) + toHex(sGreen) + toHex(sBlue);
}

function toHex(N) {
  if (N == null)
    return "00";
  N = parseInt(N);
  if (N==0 || isNaN(N))
    return "00";
  N=Math.max(0,N);
  N=Math.min(N,255);
  N=Math.round(N);
  return "0123456789ABCDEF".charAt((N-N%16)/16) +
    "0123456789ABCDEF".charAt(N%16);
}
