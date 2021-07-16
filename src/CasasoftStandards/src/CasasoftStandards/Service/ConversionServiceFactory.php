<?php
namespace CasasoftStandards\Service;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class ConversionServiceFactory implements FactoryInterface
{

    function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array $options = null){
        $translator = $container->get('MvcTranslator');
        $numvalService = $container->get('CasasoftNumval');
        $categoryService = $container->get('CasasoftCategory');
        $featureService = $container->get('CasasoftFeature');
        $utilityService = $container->get('CasasoftUtility');
        $integratedOfferService = $container->get('CasasoftIntegratedOffer');
        $heatService = $container->get('CasasoftHeat');
        $service = new ConversionService($translator, $numvalService, $categoryService, $featureService, $utilityService, $integratedOfferService, $heatService);

        return $service;
    }

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $translator = $serviceLocator->get('Translator');
        $numvalService = $serviceLocator->get('CasasoftNumval');
        $categoryService = $serviceLocator->get('CasasoftCategory');
        $featureService = $serviceLocator->get('CasasoftFeature');
        $utilityService = $serviceLocator->get('CasasoftUtility');
        $integratedOfferService = $serviceLocator->get('CasasoftIntegratedOffer');
        $heatService = $serviceLocator->get('CasasoftHeat');
        $service = new ConversionService($translator, $numvalService, $categoryService, $featureService, $utilityService, $integratedOfferService, $heatService);

        return $service;
    }
}
