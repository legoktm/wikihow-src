var article = "";
var user = "";
var maxChar = 10000;

function initQuickNote( qnArticle, qnUser, contrib, regdate ) {
	article = urldecode(qnArticle);
	user = qnUser;

	var mesid = document.getElementById('comment_text');
	var message = qnMsgBody.replace(/\<nowiki\>|\<\/nowiki\>/ig, '');
	message = message.replace(/\[\[ARTICLE\]\]/, '[['+article+']]');
	mesid.value = message;
	maxChar2 = maxChar + message.length;
	
	var targetid = document.getElementById('qnTarget');
	targetid.value = "User_talk:"+qnUser;

	var editorid = document.getElementById('qnEditorInfo');
	editorid.innerHTML = "<strong>Leave a quick note for <a href=\"/User:"+qnUser+"\" >"+qnUser+"</a></strong><br />\n ";
	if (regdate == "") {
		editorid.innerHTML += qnUser+" has <b>"+contrib+"</b> edits. <br />\n";
	} else {
		editorid.innerHTML += qnUser+" has <b>"+contrib+"</b> edits and joined us on <b>"+regdate+"</b>. <br />\n";
	}


	//var countid = document.getElementById('qnCharcount');
	//countid.innerHTML = maxChar + " Characters Left";

	document.getElementById('modalPage').style.display = 'block';
	//alert(article+"\n"+user+"\n");
	return false;
}

function qnClose() {
	document.getElementById('modalPage').style.display = 'none';
}

function qnButtons(pc_newmsg, obj, tmpl) {
	var mesid = document.getElementById('comment_text');
	var message = tmpl.replace(/\[\[ARTICLE\]\]/, '[['+article+']]');
	mesid.value = message;
	
	postcommentPublish(pc_newmsg, obj);
	document.getElementById('modalPage').style.display = 'none';
	return false;
}

function qnSend(pc_newmsg, obj) {
	var commentid = document.getElementById('comment_text');

	if (commentid.value.length > maxChar2) {
		alert("Your message is too long.  Please delete "+(commentid.value.length - maxChar2)+" characters.");
	} else {
		postcommentPublish(pc_newmsg, obj);
		document.getElementById('modalPage').style.display = 'none';
	}
	return false;
}

function qnCountchars(obj) {
	//var countid = document.getElementById('qnCharcount');

	//while(obj.value.length>maxChar2){
	//	obj.value=obj.value.replace(/.$/,'');//removes the last character
	//}

	//countid.innerHTML = (maxChar2 - obj.value.length) + " Characters Left";

	return false;
}


//###########################

function urldecode( str ) {
    // Decodes URL-encoded string
    // 
    // +    discuss at: http://kevin.vanzonneveld.net/techblog/article/javascript_equivalent_for_phps_urldecode/
    // +       version: 901.1411
    // +   original by: Philip Peterson
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +      input by: AJ
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Brett Zamir
    // %          note: info on what encoding functions to use from: http://xkr.us/articles/javascript/encode-compare/
    
    var histogram = {};
    var ret = str.toString();
    
    var replacer = function(search, replace, str) {
        var tmp_arr = [];
        tmp_arr = str.split(search);
        return tmp_arr.join(replace);
    };
    
    // The histogram is identical to the one in urlencode.
    histogram["'"]   = '%27';
    histogram['(']   = '%28';
    histogram[')']   = '%29';
    histogram['*']   = '%2A';
    histogram['~']   = '%7E';
    histogram['!']   = '%21';
    histogram['%20'] = '+';

    for (replace in histogram) {
        search = histogram[replace]; // Switch order when decoding
        ret = replacer(search, replace, ret) // Custom replace. No regexing   
    }
    
    // End with decodeURIComponent, which most resembles PHP's encoding functions
    ret = decodeURIComponent(ret);

    return ret;
}
