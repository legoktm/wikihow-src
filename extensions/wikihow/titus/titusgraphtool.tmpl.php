<script type="text/javascript" src="http://www.google.com/jsapi"></script>
<script type="text/javascript">

google.load('visualization', '1', {packages: ['annotatedtimeline', 'linechart']});    
		$.getJSON('/Special:TitusGraphTool?json=true', function(data) {
			loadTableData(data);
			google.setOnLoadCallback(drawVisualization);
    	}); 
function drawVisualization() {
	var annotatedtimeline = new google.visualization.AnnotatedTimeLine( document.getElementById('visualization1'));
	var data = new google.visualization.DataTable(dt);
	annotatedtimeline.draw(data, {'displayAnnotations': true, 'displayZoomButtons': false, 'legendPosition': 'newRow'});
	var lineChart = new google.visualization.LineChart(document.getElementById('visualization'));
	var data = new google.visualization.DataTable(dt);
	 lineChart.draw(data, {curveType: "none", width: 600, height: 400});
}

$(document).ready(function() { 
    $('.fetch').click(function(){
		$.getJSON('/Special:TitusGraphTool?json=true', function(data) {
			loadTableData(data);
			google.setOnLoadCallback(drawVisualization);
    	}); 
	});
});

function loadTableData(data) {
	// Format colums
	var header = data.shift();
	var graphColumns = [];
	$.each(header, function(key, val) {
		if (val == 'date') {
			var column = {label: val, type: 'date'};
		} else {
			var column = {label: val, type: 'number'};
		}
		graphColumns.push(column);
	});


	// Format rows
	var graphRows = [];
	$.each(data, function(dataKey, row) {
		var graphRow = {c:[]};
		$.each(row, function(key, val) {
			if (key == 0) {
				graphRow.c.push({v: new Date(val)}); 	
			} else {
				graphRow.c.push({v: parseFloat(val)}); 	
			}
		});
		graphRows.push(graphRow);
	});
	dt = {cols: graphColumns, rows: graphRows};
}
</script>

<button class="fetch" style="padding: 5px;" value="CSV">Gimme</button>
<div id="visualization1" style="width: 600px; height: 400px;"></div>
<div id="visualization" style="width: 600px; height: 400px;"></div>

