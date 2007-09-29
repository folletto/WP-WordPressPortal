<?php
/************************************************************************** WPP PAGE INJECT
 ******************************************************************************************
 * WordPress Portal - Page Inject
 * version 1.0
 * 
 * by Davide 'Folletto' Casali
 * http://digitalhymn.com
 * 
 * Released under CreativeCommons (CC) by-sa 2.5 2007.
 *
 * Allows the injection of a custom template structure and page into the WP
 * rewrite system.
 * 
 * USAGE:
 * $templates = array(
 *  TEMPLATEPATH . "/example.php",
 *  dirname(__FILE__) . "/example.php"
 * );
 * $pi = new WPP_PageInject('injected', $templates);
 *
 * The objection object will also provide the pre-parsed virtual URL that follows the
 * one specified as first parameter of the object constructor.
 * 
 * URL: http://example.com/blog/injected/test/3
 * CONSTRUCTOR: $pi = new WPP_PageInject('injected', $templates);
 * PURL: $pi->purl = array('test', '3');
 *
 */
class WPP_PageInject {
	
	var $root = null;
	var $templates = array();
	
	var $purl = array();
	var $localurl = '';
	
	/******************************************************************************************
	 * Costructor
	 */
	function WPP_PageInject($url, $fx) {
		$this->add_handler($url, $fx);
		$this->localurl = get_bloginfo('url') . '/' . preg_replace('/.*(wp-content\/.*)/i', '\\1', dirname(__FILE__)) . '/';
	}
	
	/******************************************************************************************
	 * Specifies where the new page (and template) should be attached.
	 *
	 * @param		root-relative URL (i.e. pajeinject/) with trailing slash
	 * @param		array with fallbacks of template pages
	 */
	function add_handler($root, $templates) {
		if (is_array($templates)) {
			$this->root = rtrim($root, "/");
			$this->templates = $templates;
			
			// ****** SmartAss Rewrite
			$url_wanted = rtrim(dirname($_SERVER['PHP_SELF']), "/") . "/" . $this->root;
			$url_requested = substr(trim($_SERVER['REQUEST_URI']), 0, strlen($url_wanted));
			if ($url_wanted == $url_requested) {
				// ****** Add Filter
				add_filter('404_template', array($this, 'page_handler'));
				
				// ****** Service Operations
				$this->purl = explode("/", substr(trim($_SERVER['REQUEST_URI']), strlen($url_wanted) + 1));
			}
		}
	}
	
	/******************************************************************************************
	 * Filter for the page template loader.
	 * Asks for a different template page.
	 * 
	 */
	function page_handler($template) {
		$out = $template;
		
		foreach ($this->templates as $custom_template) {
			if (file_exists($custom_template)) {
				$out = $custom_template;
				break;
			}
		}
		
		return $out;
	}
}

?>