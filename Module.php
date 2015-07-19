<?php

namespace Linguo;

use Zend\Log\Writer\Stream;
use Zend\ModuleManager\Listener\ServiceListener;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\Mvc\MvcEvent;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;

/**
 * Module Linguo
 * @author paul
 */
class Module implements AutoloaderProviderInterface, ServiceLocatorAwareInterface {

	/**
	 * SM
	 * @var ServiceLocatorInterface
	 */
	protected $serviceLocator;
	
	/**
	 * obBootstrap
	 * @param MvcEvent $e
	 */
	public function onBootstrap(MvcEvent $e){

	}

	/**
	 * getConfig
	 * @return array
	 */
	public function getServiceConfig(){
		return array(
			'factories' => array(
			),
			'abstract_factories' => array(
			),
			'aliases' => array(
			),
			'invokables' => array(
				'Linguo\Library\Mvc\Router\Http\MultiLangSegment',
				'Linguo\Library\Translator\SetLocaleFromUrlStrategy' => 'Linguo\Library\Translator\SetLocaleFromUrlStrategy'
			)
		);
	}

	/**
	 * getAutoloaderConfig
	 * @return array
	 */
	public function getAutoloaderConfig(){
		return array(
			'Zend\Loader\StandardAutoloader' => array(
				'namespaces' => array(
					__NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__
				),
			),
		);
	}

	/**
	 * ServiceLocatorAwareInterface method
	 * @param ServiceLocatorInterface $serviceLocator
	 * @return \Application\Module
	 */
	public function setServiceLocator(ServiceLocatorInterface $serviceLocator){
		$this->serviceLocator = $serviceLocator;
		return $this;
	}

	/**
	 * ServiceLocatorAwareInterface method
	 * @return ServiceLocatorInterface
	 */
	public function getServiceLocator(){
		return $this->serviceLocator;
	}
}
