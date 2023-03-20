<?php declare(strict_types=1);

namespace CartoAffect\Service\ViewHelper;

use CartoAffect\View\Helper\QuerySql;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class QuerySqlFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new QuerySql(
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\Connection')
        );
    }
}
