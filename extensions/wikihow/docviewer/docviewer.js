$(document).ready(function() {

	$('#dv_dls li').hover(
		function(){
			$(this).find(".sample_hover").fadeIn(100);
		},
		function(){
			$(this).find(".sample_hover").fadeOut(100);
		}
	);

    $('#sampleAccuracyYes').click(function(e){
        e.preventDefault();
        rateItem(1, wgSampleName, 'sample');
    });

    $('#sampleAccuracyNo').click(function(e){
        e.preventDefault();
        rateItem(0, wgSampleName, 'sample');
    });

});
