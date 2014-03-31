        <div class="search">
			<? // below is the modified output of: GoogSearch::getSearchBox("cse-search-box") ?>
			<? //<form action="/Special:GoogSearch" id="cse-search-box"> ?>
			<form action="http://www.google.<?=wfMsg('cse_domain_suffix')?>/cse/m" id="cse-search-box">
				<div>
					<input type="hidden" name="cx" value="<?=wfMsg('cse_cx')?>" />
					<!--<input type="hidden" name="cof" value="FORID:10" />-->
					<input type="hidden" name="ie" value="UTF-8" />
					<input type="text" class="cse_q search_box" name="q" value="" x-webkit-speech />
					<input type="submit" value="" class="cse_sa" alt="" />
				</div>
			</form>
		</div><!--end search-->
