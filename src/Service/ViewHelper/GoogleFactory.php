<?php
namespace CartoAffect\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use CartoAffect\View\Helper\GoogleViewHelper;

class GoogleFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $acl = $services->get('Omeka\Acl');
        $config = $services->get('Config');
        $logger = $services->get('Omeka\Logger');

        return new GoogleViewHelper($api, $acl, $config,$logger);
    }
}