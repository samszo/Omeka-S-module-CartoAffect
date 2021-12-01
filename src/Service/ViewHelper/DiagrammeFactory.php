<?php
namespace CartoAffect\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use CartoAffect\View\Helper\DiagrammeViewHelper;

class DiagrammeFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $helpers = $services->get('ViewHelperManager');
        $serverUrlHelper = $helpers->get('ServerUrl');
        $acl = $services->get('Omeka\Acl');
        return new DiagrammeViewHelper($api, $serverUrlHelper,$acl);
    }
}