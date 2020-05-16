<?php
/**
 * CartoAffect
 *
 * Module pour cartographier les affects
 *
 * @copyright Samuel Szoniecky, 2020

 */
namespace CartoAffect;

use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

}
