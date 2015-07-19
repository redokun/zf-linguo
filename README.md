# Linguo

*True multi-lingual routes support for ZF2*

This zf2 module helps dealing with fully-localized URLS

## Example

Consider you have an action with two completely localized URLs

	GET /en/contacts
	GET /it/contatti


**module/Application/config/config.php**

	<?php
	return array(
		"router" => array(
			"router_class" => "Zend\Mvc\Router\Http\TranslatorAwareTreeRouteStack", // Add this
			'routes' => => array(
		
				// Route
				'contacts' => array(
					'type' => 'Linguo\Library\Mvc\Router\Http\MultiLangSegment', // Change this
					'options' => array(
						'route' => '/:lang/{contacts}',
						'defaults' => array(
							'__NAMESPACE__' => 'Application\Controller',
							'controller' => 'Site',
							'action' => 'contacts'
						),
					),
				),
				
			),
		),
	);

## Configuration

**config/application.config.php**

	<?php
	return array(
		'modules' => array(
			'Linguo',
		
			// Your modules here
		)
	)
	
**config/autoload/linguo.php**
	
	"linguo" => array(
		"langMap" => array(
			// locale - slug pair
			"en_US" => "en",
			"it_IT" => "it",
		)
	)
	
**module/Application/Module.php**

	<?php
	/**
	 * On bootstrap
	 * @param MvcEvent $e
	 */
	public function onBootstrap(MvcEvent $e){
		
		/* @var $eventManager EventManager */
		$eventManager = $e->getApplication()->getEventManager();
		
		// Set translator locale based on the request URL
		$eventManager->attach($this->getServiceLocator()->get('Linguo\Library\Translator\SetLocaleFromUrlStrategy'));
	}
	
	
	
## Limitations

* The language parameter must be named **:lang**
* The route isn't very optimized (for conservative reasons), it might be slower than the Segment route.