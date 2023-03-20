<?php declare(strict_types=1);

namespace CartoAffect\Service\ViewHelper;

use CartoAffect\View\Helper\EntityRelation;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class EntityRelationFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new EntityRelation(
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\Connection')
        );
    }
}
