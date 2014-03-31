// Copyright 2004 and onwards Google Inc.
//
// uncompressed / commented / renamed by Chris... 
//

var w="";
var pa=false;
var _oldInputFieldValue=""; // inputField value (set during call to google...)...(was ta)
var da=false;
var _currentInputFieldValue=""; // also inputField value (was g)
var G="";
var _eventKeycode=""; // event keycode... (was m)
var _highlightedSuggestionIndex=-1; // currently hightlighted suggestion index (was j)
var _highlightedSuggestionDiv=null; // currently highlisted suggestion div... (was h)
var _completeDivRows=-1; // completeDiv rows at time of keypress... (was Z)
var _completeDivDivList=null; // completeDiv div list at time of keypress (was za)
var _completeDivRows2=5; // was Ca... initially 5? not sure difference between this and _completeDivRows...
var q="";
var _divTag="div"; // Was Lb
var _spanTag="span"; // Was Bb
var _documentForm=null; // Form on html page... (was la...)
var _inputField=null; // Input field on form... (was a)
var _completeDiv=null; // document.completeDiv (was b)
var _submitButton=null; // submit button (was Xa)
var mb=null;
var X=null;
var _enString=null; // This becomes the string "en" (was ha)
var _cursorUpDownPressed=false;  // Was ra...
var kc=null;
var hc=null;
var _resultCache=new Object(); // This is a cache of results from google... (was Ua)
var ca=1;
var Aa=1;
var Y=false;
var _lastKeyCode=-1; // Gets set on keyDown... Was na... 
var Va=(new Date()).getTime();
var _hasXMLHTTP=false; // Gets set to true if XMLHTTP Supported (was Q)
var _xmlHttp=null; // This is the XMLHttp Object... (was k) 
var _completeSearchEnString=null; // Gets set to "/complete/search/?hl=en" (was sa)
var _completeSearchString=null; // Gets set to "/complete/search" ... (was E)
var B=null;
var aa=null;
var Ba=false;
var Ka=false;
var p=60;
var _searchString=null; // Gets set to "search" in installAC (was ia)
var ya=null;
var _timeoutAdjustment=0; // timeout adjustment... (was W)... gets adjusted over time...

// This is the function that get's called from the google html page...
// Line from page:
// InstallAC(document.f,document.f.q,document.f.btnG,"search","en");
// document.f is the name of the form on the page...
// document.f.q is the input text box on the page... 
//  -> <input autocomplete="off" maxLength=256 size=55 name=q value="">
// document.f.btnG Google Search button
InstallAC=function(frm,fld,sb,pn,rl,hd,sm,ufn){
_documentForm=frm;
  _inputField=fld;
  _submitButton=sb;
  if(!pn) {
    pn="search";
  }
  _searchString=pn;
  var Kb="en|";
  var Jb="zh-CN|zh-TW|ja|ko|vi|";
  if(!rl||Kb.indexOf(rl+"|")==-1) {
    rl="en";
  }
  _enString=escapeURI(rl);
  if(Jb.indexOf(_enString+"|")==-1){
    // We won't pass through here...
    X=true;
    Y=false;
    Ba=false
  }else{
    // but will come through here...
    X=false;
    if(_enString.indexOf("zh")==0) {
      // not here...
      Y=false;
    } else {
      // but here...
      Y=true;
    }
    Ba=true
  }
  // hd is not defined, so becomes false...
  if(!hd) {
    hd=false;
  }
  ya=hd;
  // sm not defined, so becomes the string "query"
  if(!sm) {
    sm="query";
  }
  w=sm;
  // ufn is not defined...
  mb=ufn;
  installACPartTwo()
}
;

// blurs focus, then sets focus again... 
// This is getting aclled when we press cursor up / cursor down...
// Was Yb...
function blurThenGetFocus(){
  _cursorUpDownPressed=true;
  _inputField.blur();
  setTimeout("setInputFieldFocus();",10);
  return
}

// setup a keydown event...
// Was Fb...
function setupKeydown1(){
  if(document.createEventObject) {
    var y=document.createEventObject();
    y.ctrlKey=true;
    y.keyCode=70;
    document.fireEvent("onkeydown",y)
  }
}

// setup a keydown event...
// I can't figure out what calls this...
// was nc...
function setupKeydown2(vb){
  var y=document.createEventObject();
  y.ctrlKey=true;
  y.keyCode=vb;
  document.fireEvent("onkeydown",y)
}

function gc(event){}
function ic(event){}

// Was Pb
function keyDownHandler(event){
  if(!event&&window.event) {
    event=window.event;
  }
  if(event) {
    _lastKeyCode=event.keyCode;
  }

  // We are backspacing here...
  if(event&&event.keyCode==8){
    if(X&&(_inputField.createTextRange&&(event.srcElement==_inputField&&(bb(_inputField)==0&&lb(_inputField)==0)))){
      cc(_inputField);
      event.cancelBubble=true;
      event.returnValue=false;
      return false
    }
  }
}

function mc(){}

// Was Db..
function resizeHandler(){
  if(w=="url"){
    setInputFieldSize()
  }
  setCompleteDivSize()
}

// was ba...
function setCompleteDivSize(){
  if(_completeDiv){
    _completeDiv.style.left=calculateOffsetLeft(_inputField)+"px";
    _completeDiv.style.top=calculateOffsetTop(_inputField)+_inputField.offsetHeight-1+"px";
    _completeDiv.style.width=calculateWidth()+"px"
  }
}

// calculate width of inputField... Note browser specific adjustments...
// Was Ja()
function calculateWidth(){
  if(navigator&&navigator.userAgent.toLowerCase().indexOf("msie")==-1){
    return _inputField.offsetWidth-ca*2
  }else{
    return _inputField.offsetWidth
  }
}


// Called from InstallAC...
// was ac()
function installACPartTwo(){
  if(getXMLHTTP()){
    _hasXMLHTTP=true
  }else{
    _hasXMLHTTP=false
  }
    
  // pa init'd to false at the top of this file...
  if(pa) {
    _completeSearchString="complete";
  } else {
    _completeSearchString=_searchString;
  }

  _completeSearchEnString=_completeSearchString+"?hl="+_enString;

  if(!_hasXMLHTTP){
    acSetCookie("qu","",0,_completeSearchString,null,null)
  }

  //_documentForm.onsubmit=Fa;
  _inputField.autocomplete="off";
  _inputField.onblur=onBlurHandler;
  if(_inputField.createTextRange) {
    _inputField.onkeyup=new Function("return okuh(event);");
  } else {
    _inputField.onkeyup=okuh;
  }
  ///_inputField.onsubmit=Fa;
  _currentInputFieldValue=_inputField.value;
  _oldInputFieldValue=_currentInputFieldValue;
  _completeDiv=document.createElement("DIV");
  _completeDiv.id="completeDiv";
  ca=1;
  Aa=1;
 
  _completeDiv.style.borderRight="black "+ca+"px solid";
  _completeDiv.style.borderLeft="black "+ca+"px solid";
  _completeDiv.style.borderTop="black "+Aa+"px solid";
  _completeDiv.style.borderBottom="black "+Aa+"px solid";
  _completeDiv.style.zIndex="1";
  _completeDiv.style.paddingRight="0";
  _completeDiv.style.paddingLeft="0";
  _completeDiv.style.paddingTop="0";
  _completeDiv.style.paddingBottom="0";
  setCompleteDivSize();
  _completeDiv.style.visibility="hidden";
  _completeDiv.style.position="absolute";
  _completeDiv.style.backgroundColor="white";
  document.body.appendChild(_completeDiv);
  cacheResults("",new Array(),new Array());
  Gb(_completeDiv);
  var s=document.createElement("DIV");
  s.style.visibility="hidden";
  s.style.position="absolute";
  s.style.left="-10000";
  s.style.top="-10000";
  s.style.width="0";
  s.style.height="0";
  var M=document.createElement("IFRAME");
  M.completeDiv=_completeDiv;
  M.name="completionFrame";
  M.id="completionFrame";
  M.src=_completeSearchEnString;
  s.appendChild(M);
  document.body.appendChild(s);

  if(frames&&(frames["completionFrame"]&&frames["completionFrame"].frameElement)) {
    B=frames["completionFrame"].frameElement;
  } else {
    B=document.getElementById("completionFrame");
  }
  if(w=="url"){
    setInputFieldSize();
    setCompleteDivSize()
  }
  window.onresize=resizeHandler;
  document.onkeydown=keyDownHandler;
  setupKeydown1()
}

// Was Ob
function onBlurHandler(event){
  if(!event&&window.event) {
    event=window.event;
  }
  if(!_cursorUpDownPressed){
    hideCompleteDiv();
    // check if tab pressed...
    if(_lastKeyCode==9){
      setSubmitButtonFocus();
      _lastKeyCode=-1
    }
  }
  _cursorUpDownPressed=false
}

okuh=function(e){
  _eventKeycode=e.keyCode;
  aa=_inputField.value;
  Oa()
}
;
// Was Xb...
function setSubmitButtonFocus(){
  _submitButton.focus()
}

// Was sfi..
setInputFieldFocus=function(){
  _inputField.focus()
}
;

// strip CR from string...
// was Wb
function stripCRFromString(va){
  for(var f=0,oa="",zb="\n\r"; f<va.length; f++) {
    if (zb.indexOf(va.charAt(f))==-1) {
      oa+=va.charAt(f);
    } else {
      oa+=" ";
    }
  }
return oa
}

// Find span value with className = dc...
// Was Qa
function findSpanValueForClass(i,dc){
  var ga=i.getElementsByTagName(_spanTag);
  if(ga){
    for(var f=0; f<ga.length; ++f){
      if(ga[f].className==dc){
        var value=ga[f].innerHTML;
        if(value=="&nbsp;") {
          return"";
        } else{
          var z=stripCRFromString(value);
          return z
        }
      }
    }
  }else{
    return""
  }
}

// Return null if i undefined...
// otherwise return value of span cAutoComplete...
// was U
function valueOfCAutoComplete(i){
  if(!i) {
    return null;
  }
  return findSpanValueForClass(i,"cAutoComplete")
}

// Return null if i undefined...
// otherwise return value of span dAutoComplete...
// was wa
function valueOfDAutoComplete(i){
  if(!i) {
    return null;
  }
  return findSpanValueForClass(i,"dAutoComplete")
}

// Was F
function hideCompleteDiv(){
  document.getElementById("completeDiv").style.visibility="hidden"
}
// Was cb
function showCompleteDiv(){
  document.getElementById("completeDiv").style.visibility="visible";
  setCompleteDivSize()
}

// This is a result caching mechanism...
// was Ma
function cacheResults(is,cs,ds){
  _resultCache[is]=new Array(cs,ds)
}

// We get the following javascript code dynamically returned from google:
// sendRPCDone(frameElement, "fast bug", new Array("fast bug track", "fast bugs", "fast bug", "fast bugtrack"), new Array("793,000 results", "2,040,000 results", "6,000,000 results", "7,910 results"), new Array(""));
sendRPCDone=function(fr,is,cs,ds,pr){
  if(_timeoutAdjustment>0) {
    _timeoutAdjustment--;
  }
  var lc=(new Date()).getTime();
  if(!fr) {
    fr=B;
  }
  cacheResults(is,cs,ds);
  var b=fr.completeDiv;
  b.completeStrings=cs;
  b.displayStrings=ds;
  b.prefixStrings=pr;
  displaySuggestedList(b,b.completeStrings,b.displayStrings);
  Pa(b,valueOfCAutoComplete);
  if(_completeDivRows2>0) {
    b.height=16*_completeDivRows2+4;
  } else {
    hideCompleteDiv();
  }
}

function Oa(){
  // 38 is up cursor key, 40 is down cursor key...
  if(_eventKeycode==40||_eventKeycode==38) {
    blurThenGetFocus();
  }
  var N=lb(_inputField);
  var v=bb(_inputField);
  var V=_inputField.value;
  if(X&&_eventKeycode!=0){
    if(N>0&&v!=-1) {
      V=V.substring(0,v);
    } 
    if(_eventKeycode==13||_eventKeycode==3){ 
      var d=_inputField; 
      if(d.createTextRange){
        var t=d.createTextRange();
        t.moveStart("character",d.value.length);
        t.select()
      } else if (d.setSelectionRange){
        d.setSelectionRange(d.value.length,d.value.length)
      }
    } else { 
      if(_inputField.value!=V) {
        selectEntry(V)
      }
    }
  }
  _currentInputFieldValue=V;
  if(handleCursorUpDownEnter(_eventKeycode)&&_eventKeycode!=0) { 
    Pa(_completeDiv,valueOfCAutoComplete)
  }
}

function Fa(){
  return xb(w)
}

function xb(eb) {
  da=true;
  if(!_hasXMLHTTP){
    acSetCookie("qu","",0,_completeSearchString,null,null)
  }
  hideCompleteDiv();
  if(eb=="url"){
    var R="";
    if(_highlightedSuggestionIndex!=-1&&h) {
      R=valueOfCAutoComplete(_highlightedSuggestionDiv);
    }
    if(R=="") {
      R=_inputField.value;
    }
    if(q=="") { 
      document.title=R;
    } else {
      document.title=q;
    }
    var Tb="window.frames['"+mb+"'].location = \""+R+'";';
    setTimeout(Tb,10);
    return false
  } else if(eb=="query"){
    //_documentForm.submit();
    return true
  }
}

newwin=function(){
  window.open(_inputField.value);
  hideCompleteDiv();
  return false
}
;

idkc=function(e){
  if(Ba){
    var Ta=_inputField.value;
    if(Ta!=aa){
      _eventKeycode=0;
      Oa()
    }
    aa=Ta;
    setTimeout("idkc()",10)
  }
}
;
setTimeout("idkc()",10);

// Go read about encodeURIComponent here: http://msdn.microsoft.com/library/default.asp?url=/library/en-us/script56/html/js56jsmthencodeuricomponent.asp
// Basically converts a string to a valid uri... (spaces become %20, etc, etc..)
// this function was nb...
function escapeURI(La){
  if(encodeURIComponent) {
    return encodeURIComponent(La);
  }
  if(escape) {
    return escape(La)
  }
}

// Was yb
// If Mb is 0, will return 150...
// If Mb is 3, will return 250...
// If Mb is 4, will return 450...
// If Mb is X, will return 850...
function recalculateTimeout(Mb){
  var H=100;
  for(var o=1; o<=(Mb-2)/2; o++){
    H=H*2
  }
  H=H+50;
  return H
}

// This function sets itself up and gets called over and over (timeout driven)
// was idfn...
mainLoop=function(){
  if(_oldInputFieldValue!=_currentInputFieldValue){
    if(!da){
      var Za=escapeURI(_currentInputFieldValue);
      var ma=_resultCache[_currentInputFieldValue];
      if(ma){
        // Found in our cache...
        Va=-1;
        sendRPCDone(B,_currentInputFieldValue,ma[0],ma[1],B.completeDiv.prefixStrings)
      }else{
        _timeoutAdjustment++;
        Va=(new Date()).getTime();
        if(_hasXMLHTTP){
          callGoogle(Za)
        }else{
          acSetCookie("qu",Za,null,_completeSearchString,null,null);
          frames["completionFrame"].document.location.reload(true)
        }
      }
      _inputField.focus()
    }
    da=false
  }
  _oldInputFieldValue=_currentInputFieldValue;
  setTimeout("mainLoop()",recalculateTimeout(_timeoutAdjustment));
  return true
}
;
// Call mainLoop() after 10 milliseconds...
setTimeout("mainLoop()",10);

// This is onMouseDown function...
var Cb=function(){
  selectEntry(valueOfCAutoComplete(this));
  q=valueOfDAutoComplete(this);
  da=true;
  Fa()
}
;

// on mouseover...
var pb=function(){
  if(_highlightedSuggestionDiv) {
    setStyleForElement(_highlightedSuggestionDiv,"aAutoComplete");
  }
  setStyleForElement(this,"bAutoComplete")
}
;

// On Mouse out...
var ec=function(){
  setStyleForElement(this,"aAutoComplete")
}
;

// Called when cursor up/down pressed... selects new entry in completeDiv...
// was Na
function highlightNewValue(C){
  _currentInputFieldValue=G;
  selectEntry(G);
  q=G;
  if(!_completeDivDivList||_completeDivRows<=0) {
    return;
  }
  showCompleteDiv();
  if(C>=_completeDivRows){
    C=_completeDivRows-1
  }
  if(_highlightedSuggestionIndex!=-1&&C!=_highlightedSuggestionIndex){
    setStyleForElement(_highlightedSuggestionDiv,"aAutoComplete"); 
    _highlightedSuggestionIndex=-1
  }
  if(C<0){
    _highlightedSuggestionIndex=-1;
    _inputField.focus();
    return
  }
  _highlightedSuggestionIndex=C;
  _highlightedSuggestionDiv=_completeDivDivList.item(C);
  setStyleForElement(_highlightedSuggestionDiv,"bAutoComplete");
  _currentInputFieldValue=G;
  q=valueOfDAutoComplete(_highlightedSuggestionDiv);
  selectEntry(valueOfCAutoComplete(_highlightedSuggestionDiv))
}

// Was Eb
// returns false if cursor up / cursor down or enter pressed...
function handleCursorUpDownEnter(eventCode){
  if(eventCode==40){
    highlightNewValue(_highlightedSuggestionIndex+1);
    return false
  }else if(eventCode==38){
    highlightNewValue(_highlightedSuggestionIndex-1);
    return false
  }else if(eventCode==13||eventCode==3){
    return false
  }
  return true
}

// Pa(completeDiv,H)
// This function gets called for every keypress I make...
function Pa(localCompleteDiv,ib){
  var localInputField=_inputField;
  var T=false;
  _highlightedSuggestionIndex=-1;
  // This becomes the rows in our suggestion list...
  var J=localCompleteDiv.getElementsByTagName(_divTag);
  // # of rows in list...
  var O=J.length;
  _completeDivRows=O;
  _completeDivDivList=J;
  _completeDivRows2=O;
  G=_currentInputFieldValue;
  if(_currentInputFieldValue==""||O==0){
    hideCompleteDiv()
  }else{
    showCompleteDiv()
  }
  var Ab="";
  if(_currentInputFieldValue.length>0){
    var f;
    var o;
    // My prefixStrings was always an empty array...
    // So local variable T never would be set to true...
    // And the local variable Ab would remain empty...
    for(var f=0; f<O; f++){
      for(o=0; o<localCompleteDiv.prefixStrings.length; o++){
        var Ib=localCompleteDiv.prefixStrings[o]+_currentInputFieldValue;
        if(Y||ib(J.item(f)).toUpperCase().indexOf(Ib.toUpperCase())==0) {
          Ab=localCompleteDiv.prefixStrings[o]; 
          T=true; 
          break
        }
      }
      if(T){
        break
      }
    }
  }
  if(T) {
    _highlightedSuggestionIndex=f;
  }
  for(var f=0; f<O; f++) {
    setStyleForElement(J.item(f),"aAutoComplete");
  }
  if(T){
    _highlightedSuggestionDiv=J.item(_highlightedSuggestionIndex);
    q=valueOfDAutoComplete(_highlightedSuggestionDiv)
  }else{
    q=_currentInputFieldValue;
    _highlightedSuggestionIndex=-1;
    _highlightedSuggestionDiv=null
  }
  var ab=false;
  switch(_eventKeycode){
    // cursor left, cursor right, others??
    case 8:
    case 33:
    case 34:
    case 35:
    case 35:
    case 36:
    case 37:
    case 39:
    case 45:
    case 46:
      ab=true;
      break;
    default:
      // regular keypress ...
      break
  }
  //alert("ab: " + ab);
  if(!ab&&_highlightedSuggestionDiv){
    var Da=_currentInputFieldValue;
    setStyleForElement(_highlightedSuggestionDiv,"bAutoComplete");
    var z;
    if(T) {
      z=ib(_highlightedSuggestionDiv).substr(localCompleteDiv.prefixStrings[o].length);
    } else {
      z=Da;
    }
    if(z!=localInputField.value){
      if(localInputField.value!=_currentInputFieldValue) {
        return;
      }
      if(X){
        if(localInputField.createTextRange||localInputField.setSelectionRange) {
          selectEntry(z);
        }
        if(localInputField.createTextRange){
          var t=localInputField.createTextRange();
          t.moveStart("character",Da.length);
          t.select()
        }else if(localInputField.setSelectionRange){
          localInputField.setSelectionRange(Da.length,localInputField.value.length)
        }
      }
    }
  }else{
    _highlightedSuggestionIndex=-1;
    q=_currentInputFieldValue
  }
}

// Called as:
// calculateOffsetLeft(_inputField)
// was ob
function calculateOffsetLeft(r){
  return Ya(r,"offsetLeft")
}

// Called as:
// calculateOffsetTop(_inputField)
// Was Qb...
function calculateOffsetTop(r){
  return Ya(r,"offsetTop")
}

function Ya(r,attr){
  var kb=0;
  while(r){
    kb+=r[attr]; 
    r=r.offsetParent
  }
  return kb
}

// Sets cookie...
// (was called qa...)
function acSetCookie(name,value,Ra,hb,fb,Sb){
  var Nb=name+"="+value+(Ra?";expires="+Ra.toGMTString():"")+(hb?";path="+hb:"")+(fb?";domain="+fb:"")+(Sb?";secure":"");
  document.cookie=Nb
}

// Was Ha
function setInputFieldSize(){
  var xa=document.body.scrollWidth-220;
  xa=0.73*xa;
  _inputField.size=Math.floor(xa/6.18)
}
function lb(n){
  var N=-1;
  if(n.createTextRange){
    var fa=document.selection.createRange().duplicate();
    N=fa.text.length
  }else if(n.setSelectionRange){
    N=n.selectionEnd-n.selectionStart
  }
  return N
}

function bb(n){
  var v=0;
  if(n.createTextRange){
    var fa=document.selection.createRange().duplicate();
    fa.moveEnd("textedit",1);
    v=n.value.length-fa.text.length
  }else if(n.setSelectionRange){
    v=n.selectionStart
  }else{
    v=-1
  }
  return v
}
function cc(d){
  if(d.createTextRange){
    var t=d.createTextRange();
    t.moveStart("character",d.value.length);
    t.select() 
  } else if(d.setSelectionRange) {
    d.setSelectionRange(d.value.length,d.value.length)
  }
}

function jc(Zb,Ea){
  if(!Ea)Ea=1;
  if(pa&&pa<=Ea){
    var Ia=document.createElement("DIV");
    Ia.innerHTML=Zb;
    document.getElementById("console").appendChild(Ia)
  }
}

// Ex: setStyleForElement(document.createElement("DIV"), "aAutoComplete");
// was l
function setStyleForElement(c,name){
  db();
  c.className=name;
  if(Ka){
    return
  }
  switch(name.charAt(0)){
    case "m":
      c.style.fontSize="13px";
      c.style.fontFamily="arial,sans-serif";
      c.style.wordWrap="break-word";
      break;
    case "l":
      c.style.display="block";
      c.style.paddingLeft="3";
      c.style.paddingRight="3";
      c.style.height="16px";
      c.style.overflow="hidden";
      break;
    case "a":
      c.style.backgroundColor="white";
      c.style.color="black";
      if(c.displaySpan){
        c.displaySpan.style.color="green"
      }
      break;
    case "b":
      c.style.backgroundColor="#3366cc";
      c.style.color="white";
      if(c.displaySpan){
        c.displaySpan.style.color="white"
      }
      break;
    case "c":
      c.style.width=p+"%";
      c.style.cssFloat="left";
      break;
    case "d":
      c.style.cssFloat="right";
	 c.style.float="right";
      c.style.width=100-p+"%";
      if(w=="query"){
        c.style.fontSize="10px";
        c.style.textAlign="right";
        c.style.color="green";
        c.style.paddingTop="3px"
      }else{
        c.style.color="#696969"
      }
      break
  }
}

function db(){
  p=65;
  if(w=="query"){
    var wb=110;
    var Sa=calculateWidth();
    var tb=(Sa-wb)/Sa*100;
    p=tb
  }else{
    p=65
  }
  if(ya){
    p=99.99
  }
	p = 55;
}
function Gb(i){
  db();
  var Ub="font-size: 13px; font-family: arial,sans-serif; word-wrap:break-word; ";
  var Vb="display: block; padding-left: 3; padding-right: 3; height: 16px; overflow: hidden; ";
  var bc="background-color: white; ";
  var qb="background-color: #3366cc; color: white ! important; ";
  var ub="display: block; margin-left: 0%; width: "+p+"%; float: left; ";
  var Ga="display: block; margin-left: "+p+"%; ";
  if(w=="query"){
    Ga+="font-size: 10px; text-align: right; color: green; padding-top: 3px; "
  }else{
    Ga+="color: #696969; "
  }
  D(".mAutoComplete",Ub);
  D(".lAutoComplete",Vb);
  D(".aAutoComplete *",bc);
  D(".bAutoComplete *",qb);
  D(".cAutoComplete",ub);
  D(".dAutoComplete",Ga);
  setStyleForElement(i,"mAutoComplete")
}

// Called from sendRPCResponse...
// i = fr.completeDiv
// cs = list of comlete strings...
// Hb = list of results...
// was rb
function displaySuggestedList(i,cs,Hb){
  while(i.childNodes.length>0) {
    i.removeChild(i.childNodes[0]);
  }
  // For each element in our list, we create:
  // <DIV (u) - mousedown/mouseover/mouseout aAutoComplete>
  //   <SPAN (ka) lAutoComplete>
  //     <SPAN (ua) cAutoComplete>
  //        bug tracking
  //     </SPAN (ua)>
  //     <SPAN (ea) dAutoComplete>
  //        500,000 results
  //     </SPAN (ea)>
  //   </SPAN>
  // </DIV (u)>
  for(var f=0; f<cs.length; ++f){
    var u=document.createElement("DIV");
    setStyleForElement(u,"aAutoComplete");
    u.onmousedown=Cb;
    u.onmouseover=pb;
    u.onmouseout=ec;
    var ka=document.createElement("SPAN");
    setStyleForElement(ka,"lAutoComplete");
    var ua=document.createElement("SPAN");
    ua.innerHTML= cs[f]; // the text for the suggested result...
    var ea=document.createElement("SPAN");
    setStyleForElement(ea,"dAutoComplete");
    setStyleForElement(ua,"cAutoComplete");
    u.displaySpan=ea;
    if(!ya) {
      ea.innerHTML=Hb[f]; // the text for # results for suggested result...
    }
    ka.appendChild(ua);
    ka.appendChild(ea);
    u.appendChild(ka);
    i.appendChild(u)
  }
}

function D(name,gb){
  if(Ka){
    var I=document.styleSheets[0];
    if(I.addRule){
      I.addRule(name,gb)
    }else if(I.insertRule){
      I.insertRule(name+" { "+gb+" }",I.cssRules.length)
    }
  }
}

// Was function jb...
// returns an XMLHttp object... gets it in an IE/Mozilla friendly way..
function getXMLHTTP(){
  var A=null;
  try{
    A=new ActiveXObject("Msxml2.XMLHTTP")
  }catch(e){
    try{
      A=new ActiveXObject("Microsoft.XMLHTTP")
    } catch(oc){
      A=null
    }
  }
  if(!A && typeof XMLHttpRequest != "undefined") {
    A=new XMLHttpRequest()
  }
  return A
}

// This function uses the xmlHttp object to send a message back to google...
// This is the primary function that dynamically communicates with google.
// was fc
// This is the call:
// http://www.google.com/complete/search?hl=en&js=true&qu=fast%20bug
// And we get back:
// sendRPCDone(frameElement, "fast bug", new Array("fast bug track", "fast bugs", "fast bug", "fast bugtrack"), new Array("793,000 results", "2,040,000 results", "6,000,000 results", "7,910 results"), new Array(""));
function callGoogle(Rb){
  if(_xmlHttp&&_xmlHttp.readyState!=0){
    _xmlHttp.abort()
  }
  _xmlHttp=getXMLHTTP();
  if(_xmlHttp){
    // We end up calling:
    // /complete/search?hl=en&js=true&qu=<my query string...> ... 
    _xmlHttp.open("GET",_completeSearchEnString+"&js=true&qu="+Rb,true);
    // Note that this function will ONLY be called when we get a complete
    // response back from google!!
    _xmlHttp.onreadystatechange=function() {
      if(_xmlHttp.readyState==4&&_xmlHttp.responseText) {
        var frameElement=B;
        if(_xmlHttp.responseText.charAt(0)=="<"){
          _timeoutAdjustment--
        }else{
          // The response text gets executed as javascript... 
          eval(_xmlHttp.responseText)
        }
      }
    }
    ;
    // DON'T TRY TO TALK WHEN WE'RE LOCAL...
    // Comment out when running from a local file...
    _xmlHttp.send(null)
  }
}

// Select suggested entry...
// wa is the value to set the inputfield to...
// was S
function selectEntry(Wa){
  _inputField.value=Wa;
  aa=Wa
}

