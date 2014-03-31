function UL_show() {
	var i = document.getElementById('useful_links_body'); 
	var j = document.getElementById('useful_links_title');
	i.style.display = 'inline';
	j.style.backgroundImage = 'url(http://www.wikihow.com/skins/WikiHow/UsefulLinks_compress.gif)';

}

function UL_hide() {
	var i = document.getElementById('useful_links_body'); 
	var j = document.getElementById('useful_links_title');
	i.style.display = 'none';
	j.style.backgroundImage = 'url(http://www.wikihow.com/skins/WikiHow/UsefulLinks_expand.gif)';
}
function UL_showhide() {			
	var i = document.getElementById('useful_links_body'); 
	var j = document.getElementById('useful_links_title');
	if (i.style.display == 'none') {
		UL_show();
		UL_set(1);
	} else {
		UL_hide();
		UL_set(0);
	}
}
function UL_set(value) {
	var date = new Date();
	if (value == 0)
		date.setTime(date.getTime()-(24*60*60*1000));
	else
		date.setTime(date.getTime()+(24*60*60*1000));
	var expires = "; expires="+date.toGMTString();
	document.cookie = "wikihow_usefullinks="+value+expires+"; path=/";
}

function UL_isset() {
	var nameEQ = "wikihow_usefullinks=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return 0;
}
function UL_init() {
	if (UL_isset()) 	
		UL_show();
}
