<?
$messages = array();

$messages['en'] = 
        array(
			'createpage_congratulations' => 'Congratulations - Your Article is Published',
			'createpage' => 'Create a Page',
			'createpage_instructions' => 'Enter the title of wikiHow you wish to create and hit submit:',
			'createpage_new_head' => 'I know what I want to write about',
			'createpage_topic_sugg_head' => 'I want topic suggestions',
			'createpage_other_head' => 'I have other writing I want to share',
			'createpage_new_details' => "Enter your article's title below and click on Submit. <br/>Be sure to phrase it in the form of a \"how-to\" (e.g. How to <b>Walk a Dog</b>)",
			'createpage_topic_sugg_details' => "Enter any keyword(s) and we will suggest some unwritten topics for you to write. <br/>",
			'createpage_other_details' => "Do you want to publish an article you already wrote on your computer or another website? Just email us the file or URLs and we'll post it to wikiHow for you:<br /><br />Email <a id='gatPubAssist' href='mailto:publish@wikihow.com'>publish@wikiHow.com</a>",
			'createpage_related_head' => 'Hey! We think we might already have an article with a duplicate title as "<i>$1</i>". If one of the titles listed below means the <b>exact same thing,</b> please select it. <u>Do not select one of the titles just because it is on a similar topic or a related topic.</u> Instead select "<i>None of these are duplicates. I am ready to create the article</i>".',
			'createpage_related_none' => 'None of these are duplicates. I am ready to create the article!',
			'createpage_related_nomatches' => 'We did not find any potential related articles, it seems like a good topic to write about!',
			'createpage_nomatches' => 'Sorry, we had no matches for those keywords. Please try again.',
			'createpage_matches' => 'Your search returned the following matches:',
			'createpage_tryagain' => "Didn't find what you were looking for? Try another search here:",
			'managesuggestions' => "Manage suggestions", 
			'managesuggestions_boxes' => "<div class='cpbox'>
			<h3>Search for existing suggestions to delete</h3>
			<form method='POST' onsubmit='return checkform()' name='createform_topics'>
			<input type='text' name='q' size='50'>
			<br/>
			<input type='submit' value='Submit'>
			</form>
			</div>
			<div class='cpbox'>
			<h3>Add new suggestions</h3>
			<form method='POST' name='managesuggestions_add'>
			<textarea name='new_suggestions' style='width:500px; height:100px;'></textarea><br/>
			<input type='checkbox' name='formatted'> My suggestions are already formatted<br/>
			<input type='submit' value='Add'>
			</form>
			</div>
			<div class='cpbox'>
			<h3>Delete suggestions</h3>
			<form method='POST' name='managesuggestions_delete'>
			<textarea name='remove_suggestions' style='width:500px; height:100px;'></textarea><br/>
			<input type='submit' value='Delete'>
			</form>
			</div>
	",
			'managesuggestions_log_add' => '$1 added a suggestion for "$2"',
			'managesuggestions_log_remove' => '$1 removed the suggestion for "$2"',
			'createpage_fromsuggestions' => "<div class='cpbox'>
			<h3>Create a page</h3>
					<form action='$2' method='GET'>
					<input type='hidden' name='action' value='edit'/>
					<input type='hidden' name='suggestion' value='1'/>
					<input type='text' style='width: 300px;' name='title' value=\"$1\">
					<input type='submit' value='Create page'>
					</form>
				</div>
			",
			'cp_loading' => 'Loading...',
			'createpage_review_options' => "<div><center>
					<a onclick='clickshare(28); closeModal();' class='button'>Continue Editing</a> 
					<input type='button' value='Save & Publish' onclick='clickshare(29); saveandpublish(); return false;' class='button primary' />
					</center></div>",
		);
