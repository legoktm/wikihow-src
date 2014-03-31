<?

$creator = new ExtensionCreator();
$creator->createExtension();

class ExtensionCreator {
	
	var $vars = null;
	var $extPath = null;
	var $path = null;
	
	public function createExtension() {
		$this->path = dirname(__FILE__);
		$this->promptForParams();
		$this->createDir();

		$templates = array('definition.php' => '.php', 'body.php' => '.body.php', 'i18n.php' => '.i18n.php');
		foreach ($templates as $template => $ext) {
			$this->createFile($template, $ext);
		}
		
		$this->printConfirmation();
	}

	private function promptForParams() {
		echo "Enter 1 for UnlistedSpecialPage, 2 for SpecialPage:\n";
		$line = trim(fgets(STDIN));
		$this->vars['<unlisted>'] = intval($line) == 1 ? 'Unlisted' : '';

		echo "Enter class name:\n";
		$line = trim(fgets(STDIN));
		$this->vars['<classname>'] = $line;
		$this->vars['<mwmsg>'] = strtolower($line);
		
		echo "Enter extension name (eg 'RC Patrol' or 'Quality Guardian' without quotes):\n";
		$line = trim(fgets(STDIN));
		$this->vars['<extname>'] = $line;

		echo "Enter author name:\n";
		$line = trim(fgets(STDIN));
		$this->vars['<author>'] = $line;

		echo "Enter extension description:\n";
		$line = trim(fgets(STDIN));
		$this->vars['<description>'] = $line;

		echo "Enter full path to where extension files should be created:\n";
		$line = trim(fgets(STDIN));
		$this->extPath = $this->formatPath($line);
	}

	private function formatPath($path) {
		if (0 !== stripos($path, '/')) {
			$path = '/' . $path;
		}

		if (0 === preg_match('/(.*)\/$/', $path)) {
			$path .= '/';
		}

		return $path;
	}

	private function createDir() {
		if(!file_exists($dir)) {
			mkdir($this->extPath, 0775, true);
		}
	}

	private function createFile(&$template, $ext) {
		$content = file_get_contents($this->path . '/' . $template);
		$this->replaceVars($content);
		$this->writeFile($content, $ext);
	}

	private function replaceVars(&$file) {
		$vars = $this->vars;
		foreach ($vars as $k => $v) {
			$file = str_replace($k, $v, $file); 
		}
	}

	private function writeFile(&$content, &$ext) {
		$className = $this->vars['<classname>'];
		$dest = $this->extPath . $className . $ext;
		file_put_contents($dest, $content);
	}

	private function printConfirmation() {
		echo "\n=============================================================================";
		echo "\n\nwikiHow extension files have been created under {$this->extPath}";
		echo "\n\nDon't forget to add a line to LocalSettings.php to enable it within the mw framework\n\n";
	}
}
?>
