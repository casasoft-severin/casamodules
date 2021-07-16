<?php
namespace CasasoftStandards\View\Helper;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class GetListFactory implements FactoryInterface
{

  function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array$options = NULL)
  {
      $conversionService = $container->get('CasasoftConversion');
      $helper = new GetList($conversionService);
      return $helper;
  }

    public function createService(ServiceLocatorInterface $sl)
    {
        $serviceLocator = $sl->getServiceLocator();
        $conversionService = $serviceLocator->get('CasasoftConversion');

        $helper = new GetList($conversionService);

        return $helper;
    }
}
