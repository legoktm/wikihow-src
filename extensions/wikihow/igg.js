var wgServer = '';
var imgplus = '';
var imgminus = '';
var imgplus2 = '';
var imgminus2 = '';

function init() {
	imgplus = wgServer+'/extensions/wikihow/igg-plus-light.png';
	imgminus = wgServer+'/extensions/wikihow/igg-minus-light.png';
	imgplus2 = wgServer+'/extensions/wikihow/igg-plus-dark.png';
	imgminus2 = wgServer+'/extensions/wikihow/igg-minus-dark.png';
}

function getHTML() {
	var params = {};  
	var url = "";
	
	url = wgServer+"/Special:GoogGadget?type=home&nocache=1";

	_IG_FetchContent(url, response);

	setTimeout('_IG_AdjustIFrameHeight()',1000);
}

function getHTMLCanvas() {
	var params = {};  
	var url = "";

	url = wgServer+"/Special:GoogGadget?type=canvas&nocache=1";

	_IG_FetchContent(url, response);

	setTimeout('_IG_AdjustIFrameHeight()',2000);
}


function response(responseText) {               
	document.getElementById('igcontent_div').innerHTML = responseText;
}

function collapseAll() {

	document.getElementById('article-1').style.display = 'none';
	document.getElementById('article-1-img').src = imgplus;
	document.getElementById('article-2').style.display = 'none';
	document.getElementById('article-2-img').src = imgplus;
	document.getElementById('article-3').style.display = 'none';
	document.getElementById('article-3-img').src = imgplus;

	//document.getElementById('article-3-exp').innerHTML = '[+]';
}

function expand(obj) {
	var objidstr = obj.getAttribute('id');
	var adiv = objidstr.replace('-exp','');
	var adiv2 = document.getElementById(adiv + '-img');

	if ((adiv2.src == imgplus) || (adiv2.src == imgplus2)) {	
		collapseAll();
		document.getElementById(adiv).style.display = 'block';
		adiv2.src = imgminus2;
	} else {
		document.getElementById(adiv).style.display = 'none';
		adiv2.src = imgplus2;
	}

	_IG_AdjustIFrameHeight();
}

function expMouseOver(obj) {
	if (imgplus == '') { init(); }

	if (obj.src == imgplus) {
		obj.src = imgplus2;
	} else {
		obj.src = imgminus2;
	}
}
function expMouseOut(obj) {
	if (obj.src == imgplus2) {
		obj.src = imgplus;
	} else {
		obj.src = imgminus;
	}
}
