function getURLParameter (param) {
 var val = "";
 var qs = window.location.search;
 var start = qs.indexOf(param);

 if (start != -1) {
  start += param.length + 1;
  var end = qs.indexOf("&", start);
  if (end == -1) {
   end = qs.length
  }
  val = qs.substring(start,end);
 }
 return val;
}

