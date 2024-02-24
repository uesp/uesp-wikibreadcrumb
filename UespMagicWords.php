<?php

class UespMagicWords
{
	/* Note that these should match the words in the i18n file */
	const MAG_SITE_INITTRAIL = 'inittrail';
	const MAG_SITE_SETTRAIL = 'settrail';
	const MAG_SITE_ADDTOTRAIL = 'addtotrail';

	const SITEID = "Site";

	public static $egSiteParserFunctions = array(
		self::MAG_SITE_INITTRAIL => 0,
		self::MAG_SITE_SETTRAIL => 0,
		self::MAG_SITE_ADDTOTRAIL => 0
	);

	static function InitFunctionHooks(&$parser)
	{
		// {{#inittrail:}}, {{#settrail:}}, and {{#addtotrail:}} parser functions
		$parser->setFunctionHook(self::MAG_SITE_INITTRAIL, array('UespBreadCrumb', 'implementInitTrail'), SFH_OBJECT_ARGS);
		$parser->setFunctionHook(self::MAG_SITE_SETTRAIL, array('UespBreadCrumb', 'implementSetTrail'), SFH_OBJECT_ARGS);
		$parser->setFunctionHook(self::MAG_SITE_ADDTOTRAIL, array('UespBreadCrumb', 'implementAddToTrail'), SFH_OBJECT_ARGS);
	}
};
