<?php

use MediaWiki\MediaWikiServices;

class BreadCrumb
{
	public const NA_NS_BASE    = 'breadcrumb-ns_base';
	public const STTNG_SAVEAS = 'SavePropertyAs';
	public const VAL_TRAIL = 'breadCrumbTrail';

	private const MSG_ADD = 'breadcrumb-trailadd';
	private const MSG_PREFIX = 'breadcrumb-trailprefix';
	private const MSG_SEP = 'breadcrumb-trailseparator';
	private const VAL_NSROOT = 'breadcrumb-nsroot';

	#region Private Properties
	/** @var Config $config */
	private static $config;
	#endregion

	#region Public Static Functions
	/**
	 * Gets the global variable configuration for MetaTemplate.
	 *
	 * @return GlobalVarConfig
	 */
	public static function configBuilder(): GlobalVarConfig
	{
		return new GlobalVarConfig('egBreadCrumb');
	}

	public static function doAddToTrail(Parser $parser, PPFrame $frame, array $args)
	{
		[$nsBase, $separator, $values] = self::getArgs($parser, $frame, $args);
		if (isset($values)) {
			self::addToTrail($parser, $nsBase, $separator, $values);
		}

		return '';
	}

	public static function doInitTrail(Parser $parser, PPFrame $frame, array $args): string
	{
		self::setTrail($parser, $frame, $args, true);
		return '';
	}

	public static function doSetTrail(Parser $parser, PPFrame $frame, array $args)
	{
		self::setTrail($parser, $frame, $args, false);
		return '';
	}

	/**
	 * Gets a confiuration object, as required by modern versions of MediaWiki.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Configuration_for_developers
	 *
	 * @return Config
	 */
	public static function getConfig(): Config
	{
		if (is_null(self::$config)) {
			self::$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig(strtolower(__CLASS__));
		}

		return self::$config;
	}

	/**
	 * This low-level function determines how MetaTemplate should behave. Possible values can be found in the "config"
	 * section of extension.json. Prepend the names with $metatemplate to alter their values in LocalSettings.php.
	 * Currently, these include:
	 *
	 *     SavePageProperty (self::STTNG_ENABLECPT) Possible values:
	 *         'wiki'/'wikitext' = save as wikitext
	 *         'html' = save as HTML
	 *         null or invalid = do not save
	 *
	 * @param string $setting
	 *
	 * @return bool Whether MetaTemplate can/should use a particular feature.
	 */
	public static function getSetting($setting)
	{
		return self::getConfig()->get($setting);
	}

	public static function parse(Parser $parser, string $value)
	{
		// While this could use a frame in theory, in practice, this might produce unexpected results. In the case of
		// NSInfo's NS_ commands, we can simply pass {{NS_BASE}} as the ns, as ns doesn't care in the slightest whether
		// the ns name is actually a namespace or something else, like a pseudo-space name.

		// The best way used to be recursiveTagParse() followed by replaceLinkHolders() but the latter is now
		// deprecated, so we use Fully and try to compensate for the additional HTML it adds.
		$retval = $parser->recursiveTagParseFully($value, false);
		if (substr($retval, 0, 3) === '<p>' && substr($retval, -5, 5) === "\n</p>") {
			$retval = substr($retval, 3, strlen($retval) - 8);
		}

		return $retval;
	}
	#endregion

	#region Private Static Functions
	private static function addToTrail(Parser $parser, string $ns, string $separator, array $values)
	{
		$output = $parser->getOutput();
		foreach ($values as $value) {
			$trim = trim($value);
			if ($trim !== '') {
				$link = (strpos($trim, '[[') === false)
					? wfMessage(self::MSG_ADD, $ns, $trim)->inContentLanguage()->plain()
					: $trim;
				$trail = $output->getExtensionData(self::VAL_TRAIL) ?? '';
				$prepend = $trail === ''
					? self::getPrefix()
					: $separator;
				$output->setExtensionData(self::VAL_TRAIL, $trail . $prepend . $link);
			}
		}
	}

	private static function getArgs(Parser $parser, PPFrame $frame, array $args)
	{
		$helper = VersionHelper::getInstance();
		$parserNs = $helper->getParserNamespace($parser);
		if ($parserNs === NS_TEMPLATE) {
			return null;
		}

		static $magicWords;
		$magicWords = $magicWords ?? new MagicWordArray([
			self::NA_NS_BASE,
			ParserHelper::NA_IF,
			ParserHelper::NA_IFNOT,
			ParserHelper::NA_SEPARATOR
		]);

		/** @var array $magicArgs */
		/** @var array $values */
		[$magicArgs, $values] = ParserHelper::getMagicArgs($frame, $args, $magicWords);
		foreach ($values as &$value) {
			$value = $frame->expand($value);
		}

		unset($value);

		if (!ParserHelper::checkIfs($magicArgs)) {
			return null;
		}

		if (isset($magicArgs[self::NA_NS_BASE])) {
			$nsBase = $magicArgs[self::NA_NS_BASE];
		} else {
			$nsBase = $parser->getOutput()->getExtensionData(self::VAL_NSROOT) ??
				$helper->getContentLanguage()->getNsText($parserNs ?? 0);
		}

		$separator = isset($magicArgs[ParserHelper::NA_SEPARATOR])
			? ParserHelper::getSeparator($magicArgs)
			: wfMessage(self::MSG_SEP)->inContentLanguage()->plain();
		$separator = str_replace(':', '&#058;', $separator);

		return [$nsBase, $separator, $values];
	}

	private static function getPrefix(): string
	{
		static $message;
		$message = wfMessage(self::MSG_PREFIX)->inContentLanguage()->text();
		return $message;
	}

	private static function setTrail(Parser $parser, PPFrame $frame, array $args, bool $addRoot): void
	{
		$output = $parser->getOutput();
		$output->setExtensionData(self::VAL_NSROOT, null);
		$output->setExtensionData(self::VAL_TRAIL, null);
		[$nsBase, $separator, $values] = self::getArgs($parser, $frame, $args);
		if (is_null($nsBase)) {
			return;
		}

		$output->setExtensionData(self::VAL_NSROOT, $nsBase);

		if ($addRoot) {
			$root = wfMessage('breadcrumb-trailinit', $nsBase)->inContentLanguage()->plain();
			$rootCheck = self::parse($parser, $root);
			if ($rootCheck !== '') {
				$output->setExtensionData(self::VAL_TRAIL, self::getPrefix() . $root);
			}
		}

		self::addToTrail($parser, $nsBase, $separator, $values);
	}
};
