<?php

namespace Linguo\Library\Mvc\Router\Http;

use Zend\Config\Config;
use Zend\Mvc\Router\Http\RouteMatch;
use Zend\Mvc\Router\Http\Segment;
use Zend\I18n\Translator\TranslatorInterface as Translator;
use Zend\Mvc\Router\Exception;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Stdlib\ArrayUtils;
use Zend\Stdlib\RequestInterface as Request;


/**
 * Class Linguo\Library\Mvc\Router\Http\MultiLangSegment
 * Date: 14/07/2015
 * @author Paolo Agostinetto <paolo@redokun.com>
 */
class MultiLangSegment extends Segment implements ServiceLocatorAwareInterface {

	/**
	 * @var ServiceLocatorInterface
	 */
	protected $sl;

	/**
	 * @var array
	 */
	protected $langMap;

	/**
	 * match(): defined by RouteInterface interface.
	 *
	 * @see    \Zend\Mvc\Router\RouteInterface::match()
	 * @param  Request     $request
	 * @param  string|null $pathOffset
	 * @param  array       $options
	 * @return RouteMatch|null
	 * @throws Exception\RuntimeException
	 */
	public function match(Request $request, $pathOffset = null, array $options = array())
	{
		if (!method_exists($request, 'getUri')) {
			return;
		}

		if (!isset($options['translator']) || !$options['translator'] instanceof Translator) {
			throw new Exception\RuntimeException('No translator provided');
		}

		// Default text domain
		if(!array_key_exists("text_domain", $options) || $options["text_domain"] == ""){
			$options["text_domain"] = "default";
		}

		/** @var Translator $translator */
		$translator = $options["translator"];

		$uri  = $request->getUri();
		$path = $uri->getPath();

		$textDomain = (isset($options['text_domain']) ? $options['text_domain'] : 'default');
		$regex = $this->regex;

		$originalRegexp = $regex;
		if ($this->translationKeys) {

			$tranRegexpChunks = [];
			foreach ($this->translationKeys as $key) {
				foreach ($this->getLanguageMap() as $locale => $slug) {
					$tranRegexpChunks[] = "(".$translator->translate($key, $textDomain, $locale).")";
				}

				$regex = str_replace('#' . $key . '#', sprintf("(%s)", implode("|", $tranRegexpChunks)), $regex);
			}
		}

		// First pass

		if ($pathOffset !== null) {
			$result = preg_match('(\G' . $regex . ')', $path, $matches, null, $pathOffset);
		} else {
			$result = preg_match('(^' . $regex . '$)', $path, $matches);
		}

		if (!$result) {
			return;
		}

		$matchedLength = strlen($matches[0]);
		$params = array();

		foreach ($this->paramMap as $index => $name) {
			if (isset($matches[$index]) && $matches[$index] !== '') {
				$params[$name] = $this->decode($matches[$index]);
			}
		}

		// Check if the 'lang' parameter is present. If not, it's not a match
		if(!array_key_exists("lang", $params)) {
			return;
		}

		$reqLocale = array_search($params["lang"], $this->getLanguageMap(), true);
		if(!$reqLocale){
			return;
		}

		// Second pass

		$regex = $originalRegexp;
		if ($this->translationKeys) {
			foreach ($this->translationKeys as $key) {
				$regex = str_replace('#' . $key . '#', $translator->translate($key, $textDomain, $reqLocale), $regex);
			}
		}

		if ($pathOffset !== null) {
			$result = preg_match('(\G' . $regex . ')', $path, $matches, null, $pathOffset);
		} else {
			$result = preg_match('(^' . $regex . '$)', $path, $matches);
		}

		if (!$result) {
			return;
		}

		return new RouteMatch(array_merge($this->defaults, $params), $matchedLength);
	}

	/**
	 * assemble(): Defined by RouteInterface interface.
	 *
	 * @see    \Zend\Mvc\Router\RouteInterface::assemble()
	 * @param  array $params
	 * @param  array $options
	 * @return mixed
	 */
	public function assemble(array $params = array(), array $options = array())
	{
		if (!isset($options['translator']) || !$options['translator'] instanceof Translator) {
			throw new Exception\RuntimeException('No translator provided');
		}

		/** @var Translator $translator */
		$translator = $options["translator"];

		$locale = $translator->getLocale();
		$langMap = $this->getLanguageMap();

		if(!array_key_exists($locale, $langMap)){
			throw new Exception\RuntimeException('Locale not present in language map');
		}

		$params["lang"] = $langMap[$locale];

		$textDomain = (isset($options['text_domain']) ? $options['text_domain'] : 'default');

		// Translate parts
		$parts = $this->translateParts($translator, $textDomain);

		$this->assembledParams = array();

		return $this->buildPath(
			$parts,
			array_merge($this->defaults, $params),
			false,
			(isset($options['has_child']) ? $options['has_child'] : false),
			$options
		);
	}

	/**
	 * Set service locator
	 *
	 * @param ServiceLocatorInterface $serviceLocator
	 */
	public function setServiceLocator(ServiceLocatorInterface $serviceLocator) {
		$this->sl = $serviceLocator;
	}

	/**
	 * Get service locator
	 *
	 * @return ServiceLocatorInterface
	 */
	public function getServiceLocator() {
		return $this->sl;
	}

	/**
	 * @param Translator $translator
	 * @param $textDomain
	 * @return array
	 */
	protected function translateParts(Translator $translator, $textDomain){

		// Translate
		$parts = [];
		foreach ($this->parts as $part) {
			if($part[0] == "translated-literal"){
				$part[0] = "literal";
				$part[1] = $translator->translate($part[1], $textDomain);
			}

			$parts[] = $part;
		}

		 return $parts;
	}

	/**
	 * Lang map
	 * @return array
	 */
	protected function getLanguageMap(){

		if(!$this->langMap){

			if(!$this->getServiceLocator()){
				throw new \Exception("PluginManager not provided");
			}

			$this->langMap = $this->getServiceLocator()
				->getServiceLocator()
				->get("Config")["linguo"]["langMap"];

			if($this->langMap instanceof Config){
				$this->langMap = $this->langMap->toArray();
			}
		}

		return $this->langMap;
	}
}