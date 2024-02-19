<?php



class UespMagicWords
{
	/* Note that these should match the words in the i18n file */
	const MAG_SITE_NS_BASE = 'NS_BASE';
	const MAG_SITE_NS_NAME = 'NS_NAME';
	const MAG_SITE_NS_FULL = 'NS_FULL';
	const MAG_SITE_NS_PARENT = 'NS_PARENT';
	const MAG_SITE_NS_MAINPAGE = 'NS_MAINPAGE';
	const MAG_SITE_NS_CATEGORY = 'NS_CATEGORY';
	const MAG_SITE_NS_TRAIL = 'NS_TRAIL';
	const MAG_SITE_NS_ID = 'NS_ID';
	const MAG_SITE_MOD_NAME = 'MOD_NAME';
	const MAG_SITE_CORENAME = 'CORENAME';
	const MAG_SITE_SORTABLECORENAME = 'SORTABLECORENAME';
	const MAG_SITE_LABELNAME = 'LABELNAME';
	const MAG_SITE_SORTABLE = 'sortable';
	const MAG_SITE_LABEL = 'label';
	const MAG_SITE_INITTRAIL = 'inittrail';
	const MAG_SITE_SETTRAIL = 'settrail';
	const MAG_SITE_ADDTOTRAIL = 'addtotrail';

	const SITEID = "Site";

	public static $egSiteNamespaceMagicWords = array(
/*		self::MAG_SITE_NS_BASE => 1,
		self::MAG_SITE_NS_NAME => 1,
		self::MAG_SITE_NS_FULL => 1,
		self::MAG_SITE_NS_PARENT => 1,
		self::MAG_SITE_NS_MAINPAGE => 1,
		self::MAG_SITE_NS_CATEGORY => 1,
		self::MAG_SITE_NS_TRAIL => 1,
		self::MAG_SITE_NS_ID => 1,
		self::MAG_SITE_MOD_NAME => 1 */
	);

	public static $egSiteOtherMagicWords = array(
		self::MAG_SITE_CORENAME => 0,
		self::MAG_SITE_SORTABLECORENAME => 0,
		self::MAG_SITE_LABELNAME => 0
	);

	public static $egSiteParserFunctions = array(
		self::MAG_SITE_SORTABLE => 0,
		self::MAG_SITE_LABEL => 0,
		self::MAG_SITE_INITTRAIL => 0,
		self::MAG_SITE_SETTRAIL => 0,
		self::MAG_SITE_ADDTOTRAIL => 0
	);


	static function InitFunctionHooks(&$parser)
	{
		// To disable extension-specific search-related code (i.e., mechanics of how pages are looked up), this line could be commented out -- but some features will still be accessed by SiteSpecialSearch
		$hookoption = SFH_OBJECT_ARGS;

		// To disable the {{#sortable:}} parser function, comment out this line
		$parser->setFunctionHook(self::MAG_SITE_SORTABLE, array('UespMagicWords', 'implementSortable'));
		// To disable the {{#label:}} parser function, comment out this line
		$parser->setFunctionHook(self::MAG_SITE_LABEL, array('UespMagicWords', 'implementLabel'));

		// parser function versions of pagename variables
		$parser->setFunctionHook(self::MAG_SITE_CORENAME, array('UespMagicWords', 'implementCorename'), SFH_NO_HASH);
		$parser->setFunctionHook(self::MAG_SITE_LABELNAME, array('UespMagicWords', 'implementLabelname'), SFH_NO_HASH);
		$parser->setFunctionHook(self::MAG_SITE_SORTABLECORENAME, array('UespMagicWords', 'implementSortableCorename'), SFH_NO_HASH);

		// {{#inittrail:}}, {{#settrail:}}, and {{#addtotrail:}} parser functions
		$parser->setFunctionHook(self::MAG_SITE_INITTRAIL, array('UespBreadCrumb', 'implementInitTrail'), $hookoption);
		$parser->setFunctionHook(self::MAG_SITE_SETTRAIL, array('UespBreadCrumb', 'implementSetTrail'), $hookoption);
		$parser->setFunctionHook(self::MAG_SITE_ADDTOTRAIL, array('UespBreadCrumb', 'implementAddToTrail'), $hookoption);

		// parser function versions of Namespace variables (e.g., {{NS_FULL:SI}}, instead of just {{NS_FULL}})
		#$parser->setFunctionHook(self::MAG_SITE_NS_BASE, array('UespNamespace', 'parser_get_ns_base'), SFH_NO_HASH | $hookoption);
		#$parser->setFunctionHook(self::MAG_SITE_NS_FULL, array('UespNamespace', 'parser_get_ns_full'), SFH_NO_HASH | $hookoption);
		#$parser->setFunctionHook(self::MAG_SITE_MOD_NAME, array('UespNamespace', 'parser_get_mod_name'), SFH_NO_HASH | $hookoption);
		#$parser->setFunctionHook(self::MAG_SITE_NS_ID, array('UespNamespace', 'parser_get_ns_id'), SFH_NO_HASH | $hookoption);
		#$parser->setFunctionHook(self::MAG_SITE_NS_PARENT, array('UespNamespace', 'parser_get_ns_parent'), SFH_NO_HASH | $hookoption);
		#$parser->setFunctionHook(self::MAG_SITE_NS_NAME, array('UespNamespace', 'parser_get_ns_name'), SFH_NO_HASH | $hookoption);
		#$parser->setFunctionHook(self::MAG_SITE_NS_MAINPAGE, array('UespNamespace', 'parser_get_ns_mainpage'), SFH_NO_HASH | $hookoption);
		#$parser->setFunctionHook(self::MAG_SITE_NS_CATEGORY, array('UespNamespace', 'parser_get_ns_category'), SFH_NO_HASH | $hookoption);
		#$parser->setFunctionHook(self::MAG_SITE_NS_TRAIL, array('UespNamespace', 'parser_get_ns_trail'), SFH_NO_HASH | $hookoption);
	}


	public static function onGetMagicVariableIDs(&$variableIDs)
	{
		$allCustomMagicWords = array_merge(self::$egSiteNamespaceMagicWords, self::$egSiteOtherMagicWords);

		foreach ($allCustomMagicWords as $magicword => $case) {
			$variableIDs[] = $magicword;
		}

		return true;
	}


	public static function onParserGetVariableValueSwitch($parser, &$variableCache, $magicWordId, &$ret, $frame)
	{
		if (array_key_exists($magicWordId, self::$egSiteNamespaceMagicWords)) {
			$ret = UespNamespace::find_nsobj($parser, $frame)->get(strtolower($magicWordId));
			$variableCache[$magicWordId] = $ret;
			$parser->addTrackingCategory('uespbreadcrumb-tracking-deprecated');
		} elseif ($magicWordId == self::MAG_SITE_CORENAME) {
			$ret = self::implementCorename($parser);
			$variableCache[$magicWordId] = $ret;
		} elseif ($magicWordId == self::MAG_SITE_LABELNAME) {
			$ret = self::implementLabelname($parser);
			$variableCache[$magicWordId] = $ret;
		} elseif ($magicWordId == self::MAG_SITE_SORTABLECORENAME) {
			$ret = self::implementSortableCorename($parser);
			$variableCache[$magicWordId] = $ret;
		}

		return true;
	}


	// Implementation of CORENAME magic word (also used by SORTABLECORENAME and LABELNAME magic words)
	public static function implementCorename(&$parser, $page_title = NULL)
	{
		if (is_null($page_title) && is_object($parser)) $page_title = $parser->getTitle();
		if (is_object($page_title)) $page_title = $page_title->getText();

		$sections = explode('/', $page_title);
		if (count($sections) == 1) return $sections[0];

		$last = $sections[count($sections) - 1];

		if ($last == 'Description' || $last == 'Author' || $last == 'Desc' || $last == 'Directions') array_pop($sections);
		return $sections[count($sections) - 1];
	}

	// Implementation of SORTABLECORENAME magic word
	public static function implementSortableCorename(&$parser, $page_title = NULL)
	{
		$corename = self::implementCorename($parser, $page_title);
		return self::doSortable($corename);
	}

	// Implementation of LABELNAME magic word
	public static function implementLabelname(&$parser, $page_title = NULL)
	{
		$corename = self::implementCorename($parser, $page_title);
		return self::doLabel($corename);
	}

	// Implementation of {{#sortable}} parser function
	public static function implementSortable(&$parser, $pagename = '')
	{
		return self::doSortable($pagename);
	}


	// Used by both SORTABLECORENAME and {{#sortable}}
	public static function doSortable($page_title = '')
	{
		if (preg_match('/^\s*(A|An|The)\s+(.*)/', $page_title, $matches)) {
			return $matches[2] . ", " . $matches[1];
		} else {
			return $page_title;
		}
	}

	// Implementation of {{#label}} parser function
	public static function implementLabel(&$parser, $pagename = '')
	{
		return self::doLabel($pagename);
	}


	public static function doLabel($pagename = '')
	{
		$text = preg_replace('/\s*\([^\)]+\)\s*$/', '', $pagename);
		return $text;
	}
};
