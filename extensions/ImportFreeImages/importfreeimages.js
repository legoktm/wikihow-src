    //<![CDATA[
    // Our global state
    var gImageSearch;
    var gMap;
    var gSelectedResults = [];
    var gCurrentResults = [];
    var gSearchForm;

    google.load("search", "1");

    function OnLoad() {
      // Create a search control
      var searchControl = new google.search.SearchControl();

        gSearchForm = new GSearchForm(false, document.getElementById("searchform"));
        gSearchForm.setOnSubmitCallback(null, CaptureForm);
		if (navigator.appVersion.indexOf("MSIE 8.0") < 0)
        	gSearchForm.input.focus();

        // Initialize the local searcher
        gImageSearch = new GimageSearch();
        gImageSearch.setSiteRestriction(gAjaxDomain);
        gImageSearch.setSearchCompleteCallback(null, OnImageSearch);
        gImageSearch.setResultSetSize(GSearch.LARGE_RESULTSET);
        // Execute the initial search
        gSearchForm.execute(gInitialSearch);
		gImageSearch.gotoPage(8);
    }
   // Cancel the form submission, executing an AJAX Search API search.
   function CaptureForm(searchForm) {
      gImageSearch.setResultSetSize(GSearch.LARGE_RESULTSET);
      gImageSearch.execute(searchForm.input.value);
      return false;
    }
    function OnImageSearch() {
		if (!gImageSearch.results) return;
		var r = document.getElementById('ajax_results');
		var s = "<table cellpadding='10' width='100%' align='center' class='ifi_table'>";
    	if (gImageSearch.cursor && gImageSearch.cursor.currentPageIndex < gPage) {
			gImageSearch.gotoPage(gPage);
			return;
    	}
      	if (gImageSearch.cursor.currentPageIndex == gPage) {
				// get another page
				gImageSearch.gotoPage(gPage+1);
		}
      	for (var i = 0; i < gImageSearch.results.length; i++) {
			if (i % 4 == 0) 
				s += "<tr>";
			title = gImageSearch.results[i].titleNoFormatting;
			if (gAjaxDomain == "wikimedia.org") {
				title = title.replace(/[0-9]*px-/, "");	
				title = title.replace(/\..*/, "");	
				title = title.replace(/\_/g, " ");	
				title = title.split(":")[1];
			}
			var x = gImageSearch.results[i];
			if (typeof title_id == 'undefined') title_id = 0;
			title_id++;
			var input_html = "<input type='hidden' id='google_imgtitle_" + title_id + "' name='google_imgtitle_" + title_id + "' value='" + title + "' />";
			if (x.url.indexOf("http://upload.wikimedia.org/wikipedia/commons") != 0) {
            	s += "<td style='background: #eee;'>";
				s += input_html;
				s += "<a href='" + x.url + "'>" + title + "<br/><img src='" + x.tbUrl + "'><br/>";
				s += "(<a href='" + x.originalContextUrl + "' target='new'>" + gImportMsgManual + "</a>)<br/><a href='" + gManualURL + "' target='new'>" + gMoreInfo + "</a>";
			} else {
            	s += "<td>";
				s += input_html;
				s += "<a href='" + x.url + "'>" + title + "<br/><img src='" + x.tbUrl + "'><br/>";
				// quote string for JS
				var origUrl = x.originalContextUrl
					.replace(/[\\]/g, '\\')
					.replace(/'/g, '\\\'');
				var html = "(<a href='#' onclick=\"s2('" + x.url + "', '" + origUrl + "','none', 'none', '" + title + "');\">" + gImportMsg + "</a>)";
				s += html;
			}
			s += "</td>\n";    
			if (i % 4 == 3) 
				s += "</tr>";
      	}
     	 s += "</table>";

      	var attribution = gImageSearch.getAttribution();
      	if (attribution) 
        	document.getElementById("ajax_results").appendChild(attribution);
		var r = document.getElementById('ajax_results');
		if (r) r.innerHTML += s;
    }   
    google.setOnLoadCallback(OnLoad);
