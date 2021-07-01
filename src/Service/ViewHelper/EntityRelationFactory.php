<?php
namespace CartoAffect\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use CartoAffect\View\Helper\EntityRelationViewHelper;

class EntityRelationFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $conn = $services->get('Omeka\Connection');

        return new EntityRelationViewHelper($api, $conn);
    }
}