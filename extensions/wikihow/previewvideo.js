
var pv_request; 

function pv_Handler() {
    if ( pv_request.readyState == 4) {
        if ( pv_request.status == 200) {
            var e = document.getElementById('viewpreview_innards');
            e.innerHTML = pv_request.responseText;
        }
    }
}

function pv_Preview() {
    try {       
        pv_request = new XMLHttpRequest();
    } catch (error) {
        try {   
            pv_request = new ActiveXObject('Microsoft.XMLHTTP');
        } catch (error) {
            return false;
        }       
    }   
    pv_request.open('GET', vp_URL,true);
    pv_request.send('');
    pv_request.onreadystatechange = pv_Handler;
    
    var e = document.getElementById('viewpreview_innards');
    e.innerHTML = "<img src='/extensions/wikihow/rotate.gif'/>";
}

function showVideoPreview() {
    if (!$('#viewpreview').is(':visible'))  showHideVideoPreview();
}

function showHideVideoPreview() {
    if ($('#viewpreview').is(':visible')) {
		$('#viewpreview').hide();
		$('#show_preview_button').show();
    } else {
		$('#viewpreview').show();
		$('#show_preview_button').hide();
    }

}
