(function($) {
    $(document).ready(function() {
        $("#langcode").chosen({no_results_text: "No results matched."});
		$('#fromDate,#toDate').datepicker({
			minDate: "-6W",
			maxDate: "+0D",
		}).datepicker("option", "dateFormat", "yy-mm-dd");
		$('#fromDate').datepicker("setDate", "-6W");
		$('#toDate').datepicker("setDate", new Date());
    });

}(jQuery));
