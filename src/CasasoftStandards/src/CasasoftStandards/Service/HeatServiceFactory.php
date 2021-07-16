<?php
namespace CasasoftStandards\Service;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class HeatServiceFactory implements FactoryInterface
{

    public function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array $options = null){
        $translator = $container->get('MvcTranslator');
        //$viewRenderer = $serviceLocator->get('viewRenderer');

        $service = new HeatService($translator);

        return $service;
    }

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $translator = $serviceLocator->get('Translator');

        $service = new HeatService($translator);

        return $service;
    }
}
