<script type="text/javascript">
	var WH = WH || {};
	WH.mobile = WH.mobile || {};

	var isBig = true;
	var isLandscape = (document.documentElement.clientHeight < document.documentElement.clientWidth);
	if (document.documentElement.clientWidth < 600 || (document.documentElement.clientHeight < 421 && isLandscape)) {
		isBig = false;
	}

	WH.mobile.swapEm=function(img_id){
		var img=document.getElementById(img_id),
			sa=function(a,v){img.setAttribute(a,v);},
			ss=img?img.getAttribute('srcset'):0,
			ns=ss?ss.split(' ')[0]:0;
		if(!ns)return;
		sa('srcset','');
		sa('width','');
		sa('height','');
		sa('src',ns);
		img.onload=function(){
			sa('width',img.width);
			sa('height',img.height);
			var div=img.parentNode.parentNode;
			if(div&&div.className.indexOf('thumb')>=0)
				div.setAttribute('style','width:'+img.width+'px;height:'+img.height+'px;');
		};
	};
</script>
<? /*
	// Scott's reference implementation of swapEm, which uses jQuery
	function swapEm(img_id) {
		//swap the src image with the srcset (larger) image value
		var img = $('#'+img_id);
		if (img.attr('srcset')) {
			new_src = img.attr('srcset').split(' ')[0];
			if (new_src == '') return;

			//reset a bunch of attributes
			img.attr('srcset','');
			img.attr('width','');
			img.attr('height','');

			//swap in our new src
			img.attr('src',new_src);

			//regrab so we have the new image object
			$('#'+img_id).load(function() {
				width = $(this).width();
				height = $(this).height();
				$(this).attr('width',width);
				$(this).attr('height',height);

				//update the surrounding div for spacing needs
				var div = $(this).parent().parent().parent();
				if (div.attr('class').indexOf('thumb') > -1) {
					div.attr('style','width:'+width+'px;height:'+height+'px;');
				}
			});
		}
	}
*/
