<?php
namespace CartoAffect\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use CartoAffect\View\Helper\CartoAffectViewHelper;

class CartoAffectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $auth = $services->get('Omeka\AuthenticationService');
        $config = $services->get('Config');

        return new CartoAffectViewHelper($api, $auth, $config);
    }
}