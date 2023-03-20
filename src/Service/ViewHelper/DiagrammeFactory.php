<?php declare(strict_types=1);

namespace CartoAffect\Service\ViewHelper;

use CartoAffect\View\Helper\Diagramme;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class DiagrammeFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Diagramme(
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\Acl'),
            $services->get('Omeka\EntityManager'),
            $services->get('ViewHelperManager')->get('ServerUrl')(true)
        );
    }
}
