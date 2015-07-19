<?php

namespace LinguoTest;

use Zend\Mvc\Service\ServiceManagerConfig;
use Zend\ServiceManager\ServiceManager;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

/**
 * Class LinguoTest\Base
 * Date: 25/02/2015
 * @author Paolo Agostinetto <paolo@redokun.com>
 */
class Base extends \PHPUnit_Framework_TestCase {

	/**
	 * @var ServiceManager
	 */
	protected static $serviceManager;

	/**
	 * @var ServiceManager
	 */
	private $serviceLocator;

	/**
	 * @return \Zend\ServiceManager\ServiceManager
	 */
	public function getServiceLocator() {

		if(!self::$serviceManager){
			self::$serviceManager = new ServiceManager(new ServiceManagerConfig());
			self::$serviceManager->setService('ApplicationConfig', []);
		}

		return self::$serviceManager;
	}
}
