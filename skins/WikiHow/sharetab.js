    function findPos(obj) {
	    var curleft = curtop = 0;
	    if (obj.offsetParent) {
		    curleft = obj.offsetLeft
		    curtop = obj.offsetTop
		    while (obj = obj.offsetParent) {
			    curleft += obj.offsetLeft
			    curtop += obj.offsetTop
		    }
	    }
	    return [curleft,curtop];
    }
	
	function ShareTab(obj,bTab) {       
        var ShOptions = document.getElementById("ShareOptions");
        
	    if (ShOptions.style.display !== "block") {
	        //set position if on the tab
	        if (bTab) { 
	            var coords = findPos(obj);
	            ShOptions.style.left = (coords[0] - 19) + "px"; 
	            ShOptions.style.top = (coords[1]) + "px"; 
	        }
	        //show it
	        ShOptions.style.display = "block";
	    } 
	    else { 
	        //hide it
	        ShOptions.style.display = "none"; 
	    }
	}
	var share_requester;
	function handle_shareResponse() {

	}
	function clickshare(selection) {
    	share_requester = null;
    	try {
       	 	share_requester = new XMLHttpRequest();
    	} catch (error) {
       	 try {
       	     share_requester = new ActiveXObject('Microsoft.XMLHTTP');
        	} catch (error) {
       	     return false;
       	 }
    	}
    	share_requester.onreadystatechange =  handle_shareResponse;
    	url = window.location.protocol + '//' + window.location.hostname + '/Special:CheckJS?selection=' + selection;
    	share_requester.open('GET', url); 
    	share_requester.send(' ');
	}

	function shareTwitter() {
		var title = encodeURIComponent(document.title);
		var url = encodeURIComponent(location.href);

		title = title.replace(/%3A%20\d+%20steps(.*)wikiHow/,"");
		title = title.replace("%20steps%20(with%20video)%20-%20wikiHow","");
		title = title.replace("%20steps%20(with%20pictures)%20-%20wikiHow","");
		title = title.replace("%20steps%20-%20wikiHow","");
		title = title.replace("%20(with%20video)%20-%20wikiHow","");
		title = title.replace("%20(with%20pictures)%20-%20wikiHow","");
		title = title.replace("%20-%20wikiHow","");

		window.open('https://twitter.com/home?status=Reading @wikiHow on '+title+'. '+url );

		return false;
	}
