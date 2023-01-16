<?php declare(strict_types=1);

namespace CartoAffect\Service\ViewHelper;

use CartoAffect\View\Helper\CartoHexa;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CartoHexaFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new CartoHexa(
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\Logger'),
            $services->get('Omeka\Settings'),
            $services->get('Omeka\AuthenticationService')
        );
    }
}
