<?php
namespace CartoAffect\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use CartoAffect\View\Helper\QuerySqlViewHelper;

class QuerySqlFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $conn = $services->get('Omeka\Connection');

        return new QuerySqlViewHelper($api, $conn);
    }
}