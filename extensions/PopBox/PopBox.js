var is_gecko = /gecko/i.test(navigator.userAgent);
var is_ie    = /MSIE/.test(navigator.userAgent);

function setSelectionRange(input, start, end) {
    if (is_gecko) {
        input.setSelectionRange(start, end);
    } else {
        // assumed IE
        var range = input.createTextRange();
        range.collapse(true);
        range.moveStart("character", start);
        range.moveEnd("character", end - start);
        range.select();
    }
};


function CountValue(strText){
	var count = 0;
	for (var i = 0; i < strText.length; i++) {
		if(strText.charAt(i) == '\n') {
			count++;
		}
	}
	return count;
};
 
function getSelectionStartEnd(input) {

	var range = document.selection.createRange();
	var stored_range = range.duplicate();
	stored_range.moveToElementText( input );
	stored_range.setEndPoint( 'EndToEnd', range );
	sStart = stored_range.text.length - range.text.length;
	sStart = sStart - CountValue(stored_range.text, new RegExp("\n"));
	sEnd = sStart + range.text.length;
	//alert("Range: " + range.text);
	//alert("Store" + stored_range.text);
	//alert("new line count" + CountValue(stored_range.text, new RegExp("\n")));
};

      function inputKey(input, ev) {
        setTimeout(function() {
			getSelection(input);
          document.getElementById("selStart").value = sStart;//getSelectionStart(input);
          document.getElementById("selEnd").value = sEnd; //getSelectionEnd(input);
        }, 20);
      }
      function doSelect() {
        var start = document.getElementById("selStart").value;
        var end = document.getElementById("selEnd").value;
        var input = document.getElementById("testfield");
        input.focus();
        setSelectionRange(input, start-10, end-10);
      }
