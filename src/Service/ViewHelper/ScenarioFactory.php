<?php
namespace CartoAffect\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use CartoAffect\View\Helper\ScenarioViewHelper;

class ScenarioFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $arrS = [
            'api'=>$services->get('Omeka\ApiManager')
            ,'acl' => $services->get('Omeka\Acl')
            ,'config' => $services->get('Config')
            ,'basePath' => $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files')
        ];

        return new ScenarioViewHelper($arrS);
    }
}