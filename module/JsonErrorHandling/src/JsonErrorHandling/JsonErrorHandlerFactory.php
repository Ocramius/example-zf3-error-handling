<?php

namespace JsonErrorHandling;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

final class JsonErrorHandlerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) : JsonErrorHandler
    {
        return new JsonErrorHandler($container->get('Config')['view_manager']['display_exceptions']);
    }
}
