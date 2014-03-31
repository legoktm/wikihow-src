<?

class Microdata {

	static $showRecipeTags = false;
	static $showhRecipeTags = false;

	//prep times for recipes in the test
	static $recipePrepTimes = array(
		'Make-Gluten-Free-Chocolate-Coconut-Macaroons' => 
			array('preptime' => 'PT10M',
				  'cooktime' => 'PT20M'),
		'Make-Gluten-Free-Cheesy-Spinach-Quesadillas' =>
			array('preptime' => 'PT5M',
				  'cooktime' => 'PT8M'),
		'Make-Gluten-Free-Apple-Buckwheat-Cereal' =>
			array('preptime' => 'PT5M',
				  'cooktime' => 'PT10M'),
		'Make-Gluten-Free-Pancakes' =>
			array('preptime' => 'PT5M',
				  'cooktime' => 'PT10M'),
		'Make-Gluten-Free-Peanut-Butter-Cookies' =>
			array('preptime' => 'PT10M',
				  'cooktime' => 'PT8M'),
	);

	//gotta be in the Recipes category and have an ingredients section
	private static function checkForRecipeMicrodata() {
		global $wgTitle, $wgUser, $wgRequest;

		static $calculated = false;
		if ($calculated) return;
		$calculated = true;

		if ($wgTitle &&
			$wgTitle->getNamespace() == NS_MAIN &&
			$wgTitle->exists() &&
			$wgRequest->getVal('oldid') == '' &&
			($wgRequest->getVal('action') == '' || $wgRequest->getVal('action') == 'view'))
		{
			if (true || wikihowAds::$mCategories['Recipes'] != null) {
				$wikihow = WikihowArticleEditor::newFromTitle($wgTitle);
				$index = $wikihow->getSectionNumber('ingredients');
				if ($index != -1) {
					self::$showRecipeTags = true;
					
					//our hRecipe subset
					if (stripos($wgTitle->getText(),'muffin') > 0) {
						self::$showhRecipeTags = true;
					}
				}
			}
		}
	}

	static function showRecipeTags() {
		self::checkForRecipeMicrodata();
		return self::$showRecipeTags;
	}

	static function showhRecipeTags() {
		self::checkForRecipeMicrodata();
		return self::$showhRecipeTags;
	}

	static function genSchemaHeader() {
		global $wgTitle, $wgRequest;
		if (self::$showRecipeTags) {
			if (self::$showhRecipeTags) {
				$schema_hdr = ' class="hrecipe"';
			} else {
				$schema_hdr = ' itemscope itemtype="http://schema.org/Recipe"';
			}
		} elseif ($wgTitle->getNamespace() == NS_MAIN
			&& $wgTitle->exists()
			&& $wgRequest->getVal('oldid') == ''
			&& $wgRequest->getVal('action') == 'view')
		{
			$schema_hdr = ' itemscope itemtype="http://schema.org/CreativeWork"';
		}
		return $schema_hdr;
	}

	static function insertPrepTimeTest($titleDBkey, &$body) {
		if (self::$showRecipeTags && isset(self::$recipePrepTimes[$titleDBkey])) {
			$times = self::$recipePrepTimes[$titleDBkey];
			$body = preg_replace('@Prep Time:</b> @','Prep Time:</b> <meta itemprop="prepTime" content="'.$times['preptime'].'" />',$body);
			$body = preg_replace('@Cook Time:</b> @','Cook Time:</b> <meta itemprop="cookTime" content="'.$times['cooktime'].'" />',$body);
		}
	}

}

