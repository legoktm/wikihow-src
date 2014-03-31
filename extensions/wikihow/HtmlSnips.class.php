<?

if ( !defined('MEDIAWIKI') ) die();

/**
* A utility class of static functions that produce html snippets
*/
class HtmlSnips {

	/*
	* Returns script or link tags for including javascript and css
	* 
	* @param string $type	The type of tags to produce.  Valid values are 'css' or 'js'
	* @param array	$files	An array of js or css file names 
	* @param string	$path	The path to the files
	* @param bool	$debug	An optional debug flag. If true, then files aren't minified
	*
	* @return string
	*/
	public static function makeUrlTags($type, $files, $path, $debug = false) {
		$files = array_unique($files);

		$path = preg_replace('/^\/(.*)/', '$1', $path);
		$path = preg_replace('/(.*)\/$/', '$1', $path);

		if ($type == 'css') {
			$fmt = '<link rel="stylesheet" type="text/css" href="%s" />'."\n";
		} else {
			$fmt = '<script src="%s"></script>'."\n";
		}
		if (!$debug) {
			$url = wfGetPad('/extensions/min/f/' . join(',', $files) . '&b=' . $path . '&' . WH_SITEREV);
			$ret = sprintf($fmt, $url);
		} else {
			$ret = '';
			foreach ($files as $file) {
				$ret .= sprintf($fmt, '/' . $path . '/' . $file);
			}
		}
			
		return $ret;
	}
}

