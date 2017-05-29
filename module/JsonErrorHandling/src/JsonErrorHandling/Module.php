<?php

namespace JsonErrorHandling;

use Zend\EventManager\EventInterface;
use Zend\ModuleManager\Feature\BootstrapListenerInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\Mvc\Application;
use Zend\View\Strategy\JsonStrategy;
use Zend\View\View;

class Module implements BootstrapListenerInterface, ConfigProviderInterface
{
    public function onBootstrap(EventInterface $e) : void
    {
        /* @var $app Application */
        $app      = $e->getTarget();
        $locator  = $app->getServiceManager();
        $view     = $locator->get(View::class);
        /* @var $strategy JsonStrategy */
        $strategy = $locator->get('ViewJsonStrategy');

        $strategy->attach($view->getEventManager(), 100);

        $locator->get(JsonErrorHandler::class)->attach($app->getEventManager());
    }

    public function getConfig() : array
    {
        return [
            'service_manager' => [
                'factories' => [
                    JsonErrorHandler::class => JsonErrorHandlerFactory::class,
                ],
            ],
        ];
    }
}
