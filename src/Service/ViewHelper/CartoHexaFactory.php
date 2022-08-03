<?php
namespace CartoAffect\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use CartoAffect\View\Helper\CartoHexaViewHelper;

class CartoHexaFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $arrS = [
            'api'=>$services->get('Omeka\ApiManager')
            ,'logger' => $services->get('Omeka\Logger')
            ,'settings' => $services->get('Omeka\Settings')
            ,'auth' => $services->get('Omeka\AuthenticationService')
        ]; 

        
        return new CartoHexaViewHelper($arrS);
    }
}