<?php


class UespBreadCrumb
{
	protected static $_titles = array();
	protected $_titleid;
	protected $_parser;
	protected $_frame;
	protected $_trailtext = NULL;
	protected $_trailns = NULL;
	protected $_fulltrail = NULL;
	protected $_display = false;
	
	
	function __construct(&$titleid, &$parser = NULL)
	{
		$this->_titleid = $titleid;
		$this->_parser = $parser;
		$this->_frame = NULL;
		
			// don't bother to set hook if parser is NULL (page is being generated from cache, not parsed)
		if (!is_null($this->_parser)) 
		{
			global $wgHooks;
			
				// ParserAfterTidy works on save and auto-update -- called after each individual article is processed
			$wgHooks['ParserAfterTidy'][] = array($this, 'finishTrail');
		}
	}
	
	
	static function newFromParser(&$parser)
	{
		$id = $parser->getTitle()->getArticleID();
		if (!array_key_exists($id, self::$_titles)) self::$_titles[$id] = new UespBreadCrumb($id, $parser);
		return self::$_titles[$id];
	}
	
	
	static function newFromWgTitle($create = false)
	{
		global $wgTitle;
		
		$id = $wgTitle->getArticleID();
		
		if (array_key_exists($id, self::$_titles))
		{
			return self::$_titles[$id];
		}
		elseif (!$create)
		{
		
			return NULL;
		}
		else 
		{
			self::$_titles[$id] = new UespBreadCrumb($id);
			return self::$_titles[$id];
		}
	}
	
	
	protected function getArgs ($args, &$skip, &$separator)
	{
		$this->_frame = $args[0];
		$origargs = $args[1];
		$args = array();
		
		if (is_array($origargs))
		{
			foreach ($origargs as $arg)
			{
				$args[] = $this->_frame->expand($arg);
			}
		}
		
		$separator = wfMessage(strtolower(UespMagicWords::SITEID).'trailseparator')->inContentLanguage()->text();
		$output = array();
		$skip = false;
		
		foreach ($args as $arg)
		{
			$arg = trim($arg);
			if ($arg === false || $arg === '') continue;
			
			if (preg_match('/^([^\s=]+?)\s*=\s*(.*)/', $arg, $matches)) 
			{
				if ($matches[1] == 'if')
					$skip = !($matches[2] == true);
				elseif ($matches[1]=='ifnot')
					$skip = ($matches[2] == true);
				elseif ($matches[1] == 'ns' && !$skip)
					$this->_trailns = $matches[2];
				elseif ($matches[1] == 'separator')
					$separator = $matches[2];
				else
					$output[] = $arg;
			}
			else
			{
				$output[] = $arg;
			}
		}
		
		$separator = preg_replace('/:/', '&#058;', $separator);
		
			// make it possible to add vertical pipes -- which otherwise would get misread by the parsing
		$separator = preg_replace('/\!/', '|', $separator);
		if (strlen($separator) > 1 && $separator[0] == substr($separator,-1,1) && ($separator[0] == '\'' || $separator[0] == '"')) $separator = substr($separator,1,-1);
		
		return $output;
	}
	
	protected function initialize($use_ns = true)
	{
		if ($use_ns)
			$this->_trailtext = UespNamespace::parser_get_value( $this->_parser, 'ns_trail', $this->_frame, $this->_trailns);
		else
			$this->_trailtext = '';
	}
	
	
	protected function addlinks($data, $separator)
	{
		foreach ($data as $text)
		{
			if (!$text) continue;
			
			if (strpos($text, '[[')===false) $text = '[[:'.UespNamespace::parser_get_value( $this->_parser, 'ns_full', $this->_frame, $this->_trailns ).$text.'|'.$text.']]';
			if ($this->_trailtext != '') $this->_trailtext .= $separator;
			$this->_trailtext .= $text;
		}
	}
	
	
	public static function implementInitTrail( &$parser )
	{
		$object = self::newFromParser($parser);
		$args = func_get_args();
		array_shift($args);
		$data = $object->getArgs($args, $skip, $separator);
		
		if ($skip) return '';
		
		$object->initialize(true);
		$object->addlinks($data, $separator);
		return '';
	}
	
	
	public static function implementSetTrail( &$parser )
	{
		$object = self::newFromParser($parser);
		$args = func_get_args();
		array_shift($args);
		$data = $object->getArgs($args, $skip, $separator);
		
		if ($skip) return '';
		
			// n.b. this should work even for a call such as {{#settrail:}} -> i.e., trail does end up
			// being erased, which should be an allowable option
			// note that an empty trail means that default subpage links will appear instead, if appropriate
		$object->initialize(false);
		$object->addlinks($data, $separator);
		return '';
	}
	
	
	public static function implementAddToTrail( &$parser )
	{
		$object = self::newFromParser($parser);
		$args = func_get_args();
		array_shift($args);
		$data = $object->getArgs($args, $skip, $separator);
		
		if ($skip) return '';
		
		if (is_null($object->_trailtext)) $object->_trailtext = '';
		$object->addlinks($data, $separator);
		return '';
	}
	
	
	// This is being called by ParserAfterTidy
	// Note that ParserAfterTidy is normally called mutiple times on a page view -- once for each bit
	// of parsed text anywhere on the page
	// Therefore I have to be sure this function only takes effect after the real page contents have
	// been parsed, and does not take effect all the other times
	public function finishTrail( &$parser, &$text )
	{
		$dotrail = wfMessage(strtolower(UespMagicWords::SITEID) .'settrail')->inContentLanguage()->text();
		
		if (!$dotrail) return true;
		if (is_null($trail = $this->_trailtext)) return true;
		
			// clear trail so that next call to this function doesn't repeat the processing
		$this->_trailtext = NULL;
		if (!$trail) return true;
		
			// never display bread crumb trail on template pages
		if ($parser->getTitle()->getNamespace()==NS_TEMPLATE) return true;
		
			// convert trail from wikitext to HTML
		$trail = $parser->recursiveTagParse($trail);
			// necessary to actually insert the links into the text
		$parser->replaceLinkHolders($trail);
		$trail = '&lt;&nbsp;'.$trail;
			// save processed trail to a different variable, so it can be accessed by subpageHook
		$this->_fulltrail = $trail;
		
		$parser->getOutput()->setProperty( 'breadCrumbTrail', $trail );
		
		return true;
	}
	
	
	// display bread crumb trail in subpage location
	public static function subpageHook( &$subpage )
	{
		$dotrail = wfMessage(strtolower(UespMagicWords::SITEID).'settrail')->inContentLanguage()->text();
		
			// only use bread crumb trail if feature is enabled and if trail has been set
		if ($dotrail && (!is_null($object = self::newFromWgTitle())) && !is_null($object->_fulltrail))
		{
			$subpage = $object->_fulltrail;
			return false;
		}
		
			// otherwise do default processing
		return true;
	}
	
	
	// Use parserOutput->mProperties to allow customized information to be cached
	public static function getCachedTrail( &$out, $parserout )
	{
		if ( method_exists( $parserout, 'getPageProperty' ) )
			$trail = $parserout->getPageProperty('breadCrumbTrail');
		else
			$trail = $parserout->getProperty('breadCrumbTrail');
			
		if ($trail)
		{
			$object = self::newFromWgTitle(true);
			$object->_fulltrail = $trail;
		}
		
		// Even more hacking... if Subtitle is completely empty, the empty <div id='subContent'></div>
		// tags mess up the location of siteSub, when siteSub is set to float:right (the siteSub tag
		// is displaced vertically). Forcing a nbsp to be displayed fixes the problem
		/* elseif ($out->getSubtitle()=='')
			$out->setSubtitle( '&nbsp;' ); */
		return true;
	}
	
	
};