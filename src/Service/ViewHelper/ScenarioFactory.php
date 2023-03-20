<?php declare(strict_types=1);

namespace CartoAffect\Service\ViewHelper;

use CartoAffect\View\Helper\Scenario;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ScenarioFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Scenario(
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\Acl'),
            $services->get('Config'),
            $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files')
        );
    }
}
