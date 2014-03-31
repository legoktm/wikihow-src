
function approve(id) {
	$("#check_" + id).fadeOut(400, function() {
		$("#check_" + id).attr('src', '/extensions/wikihow/checkmark.png');
		$("#check_" + id).fadeIn();
	});
}


function moveUp(id) {
	// is it the first child?
	var e = $("#row_" + id);
	var p = e.parent();
	var f = p.find(":first");
	if (f.html()  == e.html()) {
		// first child
		e.fadeOut(400, function() {
			p = e.parent().parent();
			id = p.attr('id').replace(/feed_/, '');
			id--;
			var newp = $("#feed_" + id);
			newp.append("<tr id='row_" + e.attr('id') + "'>" + e.html() + "</tr>");
			e.remove();
		});
	} else {
		e.fadeOut(400, function() {
			e.insertBefore(e.prev());
			e.fadeIn();
		});
	}
}


function moveDown(id) {
    // is it the first child?
    var e = $("#row_" + id);
    var p = e.parent();
	var f;
	p.children().each(function() {
		f = $(this);
	});

    if (f.html()  == e.html()) {
        // last child
        e.fadeOut(400, function() {
            p = e.parent().parent();
            id = p.attr('id').replace(/feed_/, '');
            id++;
            var newp = $("#feed_" + id);
            newp.prepend("<tr id='row_" + e.attr('id') + "'>" + e.html() + "</tr>");
            e.remove();
        });
    } else {
        e.fadeOut(400, function() {
            e.insertAfter(e.next());
            e.fadeIn();
        });
    }
}


function removefeeditem(id) {
	var e = $("#row_" + id);
	e.fadeOut();
}

function add(day) {
	var index = Math.random();
	var url = prompt("Please copy and paste the URL of the new article here");
	var day = $("#feed_" + day);
	var name = url.replace(/.*\//, "");
	name = name.replace(/-/g, " ");
	day.append("<tr><td><a href='" + url + "'>" + name + "</a></td><td class='options'>" + getOptions(index) + "</td></tr>");
}


function renameTitle(id) {
    var e = $("#title_" + id);
    var r = $("#renamed_" + id);
	var d = "";
	if (r.html()) {
		d = r.html().replace(/Display:/, "");
	}
	var newtitle = prompt("Please enter the new title", d);
	if (r.html())
		r.html("Display: " + newtitle);
	else
		e.append("<br/><span class='renamed' id='renamed_" + id + "'>Display: " + newtitle + "</span>");
	
}
function getOptions(index) {
	s = " <input type='image' src='/extensions/wikihow/arrow-up.png' height='16px' onclick='moveUp(" + index + ");'> <input type='image' src='/extensions/wikihow/arrow-down.png' height='16px' onclick='moveDown($index);'> <input type='image'id='minus_" + index + "' onclick='removefeeditem(" + index + ");' src='/extensions/wikihow/minus.png' height='16px'/><img id='check_" + index + "' src='/extensions/wikihow/checkmark.png' height='16px' onclick='approve($index);'/>";
	return s;

}


