<?php

namespace LinguoTest\Library\Mvc\Router\Http;

use Linguo\Library\Mvc\Router\Http\MultiLangSegment;
use LinguoTest\Base;

use Zend\Config\Config;
use Zend\Http\Request;
use Zend\I18n\Translator\TextDomain;
use Zend\I18n\Translator\Translator;
use Zend\Mvc\Router\RoutePluginManager;
use Zend\Mvc\Service\RoutePluginManagerFactory;
use Zend\Stdlib\Request as BaseRequest;
use Zend\Mvc\Router\Http\Segment;
use ZendTest\Mvc\Router\FactoryTester;

/**
 * Class MultiLangSegmentTest
 * @package LinguoTest\Library\Mvc\Router\Http
 */
class MultiLangSegmentTest extends Base {

	/**
	 * @param null $name
	 * @param array $data
	 * @param string $dataName
	 */
	public function __construct($name = null, array $data = array(), $dataName = '') {
		parent::__construct($name, $data, $dataName);

		$this->getServiceLocator()->setAllowOverride(true);
		$this->getServiceLocator()->setFactory("Config", function(){
			return new Config([
				"linguo" => [
					"langMap" => [
						"en-US" => "en",
						"de-DE" => "de",
					]
				]
			]);
		});
	}

	/**
	 * @return RoutePluginManager
	 */
	public function getPluginManager(){

		$plugins = new RoutePluginManager();
		$plugins->setServiceLocator($this->getServiceLocator());

		return $plugins;
	}

	/**
	 * @return Translator
	 */
	public function getTranslator(){

		$translator = new Translator();
		$translator->setLocale('en-US');
		$enLoader     = $this->getMock('Zend\I18n\Translator\Loader\FileLoaderInterface');
		$deLoader     = $this->getMock('Zend\I18n\Translator\Loader\FileLoaderInterface');
		$domainLoader = $this->getMock('Zend\I18n\Translator\Loader\FileLoaderInterface');
		$enLoader->expects($this->any())->method('load')->willReturn(new TextDomain(['fw' => 'framework']));
		$deLoader->expects($this->any())->method('load')->willReturn(new TextDomain(['fw' => 'baukasten']));
		$domainLoader->expects($this->any())->method('load')->willReturn(new TextDomain(['fw' => 'fw-alternative']));
		$translator->getPluginManager()->setService('test-en',     $enLoader);
		$translator->getPluginManager()->setService('test-de',     $deLoader);
		$translator->getPluginManager()->setService('test-domain', $domainLoader);
		$translator->addTranslationFile('test-en', null, 'default', 'en-US');
		$translator->addTranslationFile('test-de', null, 'default', 'de-DE');
		$translator->addTranslationFile('test-domain', null, 'alternative', 'en-US');

		return $translator;
	}

	public function routeProvider()
	{
		$translator = $this->getTranslator();

		return [
			'simple-match' => [
				new MultiLangSegment('/:lang/:foo'),
				'/en/bar',
				null,
				['foo' => 'bar'],
				["translator" => $translator]
			],
			'no-match-without-leading-slash' => [
				new MultiLangSegment(':lang/:foo'),
				'/en/bar/',
				null,
				null,
				["translator" => $translator]
			],
			'no-match-with-trailing-slash' => [
				new MultiLangSegment('/:lang/:foo'),
				'/en/bar/',
				null,
				null,
				["translator" => $translator]
			],
			'offset-skips-beginning' => [
				new MultiLangSegment(':lang/:foo'),
				'/en/bar',
				1,
				['foo' => 'bar'],
				["translator" => $translator]
			],
			'offset-enables-partial-matching' => [
				new MultiLangSegment('/:lang/:foo'),
				'/en/bar/baz',
				0,
				['foo' => 'bar'],
				["translator" => $translator]
			],
			'match-overrides-default' => [
				new MultiLangSegment('/:lang/:foo', [], ['foo' => 'baz']),
				'/en/bar',
				null,
				['foo' => 'bar'],
				["translator" => $translator]
			],
			'constraints-prevent-match' => [
				new MultiLangSegment('/:lang/:foo', ['foo' => '\d+']),
				'/en/bar',
				null,
				null,
				["translator" => $translator]
			],
			'constraints-allow-match' => [
				new MultiLangSegment('/:lang/:foo', ['foo' => '\d+']),
				'/en/123',
				null,
				['foo' => '123'],
				["translator" => $translator]
			],
			'constraints-override-non-standard-delimiter' => [
				new MultiLangSegment('/:lang/:foo{-}/bar', ['foo' => '[^/]+']),
				'/en/foo-bar/bar',
				null,
				['foo' => 'foo-bar'],
				["translator" => $translator]
			],
			'constraints-with-parantheses-dont-break-parameter-map' => [
				new MultiLangSegment('/:lang/:foo/:bar', ['foo' => '(bar)']),
				'/en/bar/baz',
				null,
				['foo' => 'bar', 'bar' => 'baz'],
				["translator" => $translator]
			],
			'simple-match-with-optional-parameter' => [
				new MultiLangSegment('/:lang[/:foo]', [], ['foo' => 'bar']),
				'/en',
				null,
				['foo' => 'bar'],
				["translator" => $translator]
			],
			'optional-parameter-is-ignored' => [
				new MultiLangSegment('/:lang/:foo[/:bar]'),
				'/en/bar',
				null,
				['foo' => 'bar'],
				["translator" => $translator]
			],
			'optional-parameter-is-provided-with-default' => [
				new MultiLangSegment('/:lang/:foo[/:bar]', [], ['bar' => 'baz']),
				'/en/bar',
				null,
				['foo' => 'bar', 'bar' => 'baz'],
				["translator" => $translator]
			],
			'optional-parameter-is-consumed' => [
				new MultiLangSegment('/:lang/:foo[/:bar]'),
				'/en/bar/baz',
				null,
				['foo' => 'bar', 'bar' => 'baz'],
				["translator" => $translator]
			],
			'optional-group-is-discared-with-missing-parameter' => [
				new MultiLangSegment('/:lang/:foo[/:bar/:baz]', [], ['bar' => 'baz']),
				'/en/bar',
				null,
				['foo' => 'bar', 'bar' => 'baz'],
				["translator" => $translator]
			],
			'optional-group-within-optional-group-is-ignored' => [
				new MultiLangSegment('/:lang/:foo[/:bar[/:baz]]', [], ['bar' => 'baz', 'baz' => 'bat']),
				'/en/bar',
				null,
				['foo' => 'bar', 'bar' => 'baz', 'baz' => 'bat'],
				["translator" => $translator]
			],
			'non-standard-delimiter-before-parameter' => [
				new MultiLangSegment('/:lang/foo-:bar'),
				'/en/foo-baz',
				null,
				['bar' => 'baz'],
				["translator" => $translator]
			],
			'non-standard-delimiter-between-parameters' => [
				new MultiLangSegment('/:lang/:foo{-}-:bar'),
				'/en/bar-baz',
				null,
				['foo' => 'bar', 'bar' => 'baz'],
				["translator" => $translator]
			],
			'non-standard-delimiter-before-optional-parameter' => [
				new MultiLangSegment('/:lang/:foo{-/}[-:bar]/:baz'),
				'/en/bar-baz/bat',
				null,
				['foo' => 'bar', 'bar' => 'baz', 'baz' => 'bat'],
				["translator" => $translator]
			],
			'non-standard-delimiter-before-ignored-optional-parameter' => [
				new MultiLangSegment('/:lang/:foo{-/}[-:bar]/:baz'),
				'/en/bar/bat',
				null,
				['foo' => 'bar', 'baz' => 'bat'],
				["translator" => $translator]
			],
			'parameter-with-dash-in-name' => [
				new MultiLangSegment('/:lang/:foo-bar'),
				'/en/baz',
				null,
				['foo-bar' => 'baz'],
				["translator" => $translator]
			],
			'url-encoded-parameters-are-decoded' => [
				new MultiLangSegment('/:lang/:foo'),
				'/en/foo%20bar',
				null,
				['foo' => 'foo bar'],
				["translator" => $translator]
			],
			'urlencode-flaws-corrected' => [
				new MultiLangSegment('/:lang/:foo'),
				"/en/!$&'()*,-.:;=@_~+",
				null,
				['foo' => "!$&'()*,-.:;=@_~+"],
				["translator" => $translator]
			],
			'empty-matches-are-replaced-with-defaults' => [
				new MultiLangSegment('/:lang/foo[/:bar]/baz-:baz', [], ['bar' => 'bar']),
				'/en/foo/baz-baz',
				null,
				['bar' => 'bar', 'baz' => 'baz'],
				["translator" => $translator]
			],
			'translate-with-correct-locale-en' => [
				new MultiLangSegment('/:lang/{fw}', [], []),
				'/en/framework',
				null,
				[],
				['translator' => $translator, 'locale' => 'en-US']
			],
			'translate-with-correct-locale-de' => [
				new MultiLangSegment('/:lang/{fw}', [], []),
				'/de/baukasten',
				null,
				[],
				['translator' => $translator, 'locale' => 'de-DE']
			],
			'translate-with-specific-text-domain' => [
				new MultiLangSegment('/:lang/{fw}', [], []),
				'/en/fw-alternative',
				null,
				[],
				['translator' => $translator, 'locale' => 'en-US', 'text_domain' => 'alternative']
			],
			'translate-with-locale-at-the-end' => [
				new MultiLangSegment('/{fw}/:lang', [], []),
				'/framework/en',
				null,
				[],
				['translator' => $translator]
			],
			'translate-with-locale-at-the-middle' => [
				new MultiLangSegment('/{fw}/:lang/bar', [], []),
				'/framework/en/bar',
				null,
				[],
				['translator' => $translator]
			],
		];
	}

	public static function parseExceptionsProvider()
	{
		return [
			'unbalanced-brackets' => [
				'[',
				'Zend\Mvc\Router\Exception\RuntimeException',
				'Found unbalanced brackets'
			],
			'closing-bracket-without-opening-bracket' => [
				']',
				'Zend\Mvc\Router\Exception\RuntimeException',
				'Found closing bracket without matching opening bracket'
			],
			'empty-parameter-name' => [
				':',
				'Zend\Mvc\Router\Exception\RuntimeException',
				'Found empty parameter name'
			],
			'translated-literal-without-closing-backet' => [
				'{test',
				'Zend\Mvc\Router\Exception\RuntimeException',
				'Translated literal missing closing bracket'
			],
		];
	}

	/**
	 * @dataProvider routeProvider
	 * @param        MultiLangSegment $route
	 * @param        string  $path
	 * @param        integer $offset
	 * @param        array   $params
	 * @param        array   $options
	 */
	public function testMatching(MultiLangSegment $route, $path, $offset, array $params = null, array $options = [])
	{
		$route->setServiceLocator($this->getPluginManager());

		$request = new Request();
		$request->setUri('http://example.com' . $path);
		$match = $route->match($request, $offset, $options);

		if ($params === null) {
			$this->assertNull($match);
		} else {
			$this->assertInstanceOf('Zend\Mvc\Router\Http\RouteMatch', $match);

			if ($offset === null) {
				$this->assertEquals(strlen($path), $match->getLength());
			}

			foreach ($params as $key => $value) {
				$this->assertEquals($value, $match->getParam($key));
			}
		}
	}

	/**
	 * @dataProvider routeProvider
	 * @param        MultiLangSegment $route
	 * @param        string  $path
	 * @param        integer $offset
	 * @param        array   $params
	 * @param        array   $options
	 */
	public function testAssembling(MultiLangSegment $route, $path, $offset, array $params = null, array $options = [])
	{
		$route->setServiceLocator($this->getPluginManager());

		if ($params === null) {
			// Data which will not match are not tested for assembling.
			return;
		}

		if(isset($options["locale"])){
			$options["translator"]->setLocale($options["locale"]);
		}

		$result = $route->assemble($params, $options);

		if ($offset !== null) {
			$this->assertEquals($offset, strpos($path, $result, $offset));
		} else {
			$this->assertEquals($path, $result);
		}
	}

	/**
	 * @dataProvider parseExceptionsProvider
	 * @param        string $route
	 * @param        string $exceptionName
	 * @param        string $exceptionMessage
	 */
	public function testParseExceptions($route, $exceptionName, $exceptionMessage)
	{
		$this->setExpectedException($exceptionName, $exceptionMessage);
		new MultiLangSegment($route);
	}

	public function testAssemblingWithMissingParameterInRoot()
	{
		$this->setExpectedException('Zend\Mvc\Router\Exception\InvalidArgumentException', 'Missing parameter "foo"');
		$route = new MultiLangSegment('/:lang/:foo');
		$route->setServiceLocator($this->getPluginManager());
		$route->assemble([], [
			"translator" => $this->getTranslator()
		]);
	}

	public function testAssemblingWithMissingTranslator()
	{
		$this->setExpectedException('Zend\Mvc\Router\Exception\RuntimeException', 'No translator provided');
		$route = new MultiLangSegment('/:lang/:foo');
		$route->setServiceLocator($this->getPluginManager());
		$route->assemble();
	}

	public function testTranslatedAssemblingThrowsExceptionWithoutTranslator()
	{
		$this->setExpectedException('Zend\Mvc\Router\Exception\RuntimeException', 'No translator provided');
		$route = new MultiLangSegment('/{foo}');
		$route->setServiceLocator($this->getPluginManager());
		$route->assemble();
	}

	public function testTranslatedMatchingThrowsExceptionWithoutTranslator()
	{
		$this->setExpectedException('Zend\Mvc\Router\Exception\RuntimeException', 'No translator provided');
		$route = new MultiLangSegment('/{foo}');
		$route->setServiceLocator($this->getPluginManager());
		$route->match(new Request());
	}

	public function testNoMatchWithoutUriMethod()
	{
		$route   = new MultiLangSegment('/foo');
		$route->setServiceLocator($this->getPluginManager());
		$request = new BaseRequest();

		$this->assertNull($route->match($request));
	}

	public function testNoMatchWithWrongLocalization(){

		$route   = new MultiLangSegment('/:lang/{fw}');
		$route->setServiceLocator($this->getPluginManager());

		$request = new Request();
		$request->setUri("/de/framework");

		$this->assertNull($route->match($request, null, [
			'translator' => $this->getTranslator()
		]));
	}

	public function testAssemblingWithExistingChild()
	{
		$route = new MultiLangSegment('/:lang/[:foo]', [], ['foo' => 'bar']);
		$route->setServiceLocator($this->getPluginManager());
		$path = $route->assemble([], [
			'has_child' => true,
			'translator' => $this->getTranslator()
		]);

		$this->assertEquals('/en/bar', $path);
	}

	public function testAssemblingCalledTwoTimesWithTranslator()
	{
		$route = new MultiLangSegment('/:lang/{fw}');
		$route->setServiceLocator($this->getPluginManager());

		// First

		$path = $route->assemble([], [
			'has_child' => true,
			'translator' => $this->getTranslator()
		]);

		$this->assertEquals('/en/framework', $path);

		// Second

		$translator = $this->getTranslator();
		$translator->setLocale("de-DE");

		$path = $route->assemble([], [
			'has_child' => true,
			'translator' => $translator
		]);

		$this->assertEquals('/de/baukasten', $path);
	}

	public function testFactory()
	{
		$this->markTestIncomplete("todo");

		$tester = new FactoryTester($this);
		$tester->testFactory(
			'Linguo\Library\Mvc\Router\Http\MultiLangSegment',
			[
				'route' => 'Missing "route" in options array'
			],
			[
				'route'       => '/:foo[/:bar{-}]',
				'constraints' => ['foo' => 'bar']
			]
		);
	}

	public function testRawDecode()
	{
		// verify all characters which don't absolutely require encoding pass through match unchanged
		// this includes every character other than #, %, / and ?
		$raw = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789`-=[]\\;\',.~!@$^&*()_+{}|:"<>';
		$request = new Request();
		$request->setUri('http://example.com/en/' . $raw);

		$route   = new MultiLangSegment('/:lang/:foo');
		$route->setServiceLocator($this->getPluginManager());

		$match   = $route->match($request, null, [
			'translator' => $this->getTranslator()
		]);

		$this->assertSame($raw, $match->getParam('foo'));
	}

	public function testEncodedDecode()
	{
		// every character
		$in  = '%61%62%63%64%65%66%67%68%69%6a%6b%6c%6d%6e%6f%70%71%72%73%74%75%76%77%78%79%7a%41%42%43%44%45%46%47%48%49%4a%4b%4c%4d%4e%4f%50%51%52%53%54%55%56%57%58%59%5a%30%31%32%33%34%35%36%37%38%39%60%2d%3d%5b%5d%5c%3b%27%2c%2e%2f%7e%21%40%23%24%25%5e%26%2a%28%29%5f%2b%7b%7d%7c%3a%22%3c%3e%3f';
		$out = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789`-=[]\\;\',./~!@#$%^&*()_+{}|:"<>?';
		$request = new Request();
		$request->setUri('http://example.com/en/' . $in);

		$route   = new MultiLangSegment('/:lang/:foo');
		$route->setServiceLocator($this->getPluginManager());

		$match   = $route->match($request, null, [
			'translator' => $this->getTranslator()
		]);

		$this->assertSame($out, $match->getParam('foo'));
	}

	public function testEncodeCache()
	{
		$params1 = ['p1' => 6.123, 'p2' => 7];
		$uri1 = 'example.com/en/'.implode('/', $params1);
		$params2 = ['p1' => 6, 'p2' => 'test'];
		$uri2 = 'example.com/en/'.implode('/', $params2);

		$route = new MultiLangSegment('example.com/:lang/:p1/:p2');
		$route->setServiceLocator($this->getPluginManager());

		$request = new Request();

		$request->setUri($uri1);
		$route->match($request, null, [
			'translator' => $this->getTranslator()
		]);
		$this->assertSame($uri1, $route->assemble($params1, [
			'translator' => $this->getTranslator()
		]));

		$request->setUri($uri2);
		$route->match($request, null, [
			'translator' => $this->getTranslator()
		]);
		$this->assertSame($uri2, $route->assemble($params2, [
			'translator' => $this->getTranslator()
		]));
	}

}
