<?php

namespace Linguo\Library\Translator;

use Zend\Console\Request as ConsoleRequest;
use Zend\Http\Header\SetCookie;
use Zend\I18n\Translator\Translator;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Log\Logger;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class Linguo\Library\Translator\SetLocaleFromUrlStrategy
 * Date: 13/07/2015
 * @author Paolo Agostinetto <paolo@redokun.com>
 */
class SetLocaleFromUrlStrategy implements ListenerAggregateInterface, ServiceLocatorAwareInterface {

	/**
	 * @var \Zend\Stdlib\CallbackHandler[]
	 */
	protected $listeners = array();

	/**
	 * @var ServiceLocatorInterface
	 */
	protected $sm;

	/**
	 * Attach one or more listeners
	 *
	 * Implementors may add an optional $priority argument; the EventManager
	 * implementation will pass this to the aggregate.
	 *
	 * @param EventManagerInterface $events
	 *
	 * @return void
	 */
	public function attach(EventManagerInterface $events) {
		$this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, array($this, "onRoute"), 1000);
	}

	/**
	 * Detach all previously attached listeners
	 *
	 * @param EventManagerInterface $events
	 *
	 * @return void
	 */
	public function detach(EventManagerInterface $events) {
		foreach($this->listeners as $index => $listener){
			if($events->detach($listener)){
				unset($this->listeners[$index]);
			}
		}
	}

	/**
	 * Set service locator
	 *
	 * @param ServiceLocatorInterface $serviceLocator
	 */
	public function setServiceLocator(ServiceLocatorInterface $serviceLocator) {
		$this->sm = $serviceLocator;
	}

	/**
	 * Get service locator
	 *
	 * @return ServiceLocatorInterface
	 */
	public function getServiceLocator() {
		return $this->sm;
	}

	/**
	 * ACL Handler
	 * @param MvcEvent $event
	 */
	public function onRoute(MvcEvent $event){

		// Make sure that we are NOT running in a console
		if($event->getRequest() instanceof ConsoleRequest){
			return; // Ignore console request
		}

		$routeMatch = $event->getRouteMatch();

		if(!$routeMatch->getParam("lang")){
			return;
		}

		$reqLocale = array_search($routeMatch->getParam("lang"), $this->getLanguageMap(), true);
		if($reqLocale){
			/** @var Translator $translator */
			$translator = $this->getServiceLocator()->get('Translator');

			// Change locale if needed
			if($translator->getLocale() != $reqLocale){
				$translator->setLocale($reqLocale);
			}
		}

	}

	/**
	 * Lang map
	 * @return array
	 */
	protected function getLanguageMap(){
		return $this->getServiceLocator()->get("Config")["linguo"]["langMap"];
	}
}