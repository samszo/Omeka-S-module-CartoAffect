<?php declare(strict_types=1);
namespace CartoAffect\Service\ViewHelper;

use CartoAffect\View\Helper\DiagrammeViewHelper;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class DiagrammeFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $helpers = $services->get('ViewHelperManager');
        $serverUrlHelper = $helpers->get('ServerUrl');
        $acl = $services->get('Omeka\Acl');
        $em = $services->get('Omeka\EntityManager');
        return new DiagrammeViewHelper($api, $serverUrlHelper, $acl, $em);
    }
}
