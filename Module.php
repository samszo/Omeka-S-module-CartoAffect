<?php declare(strict_types=1);
/**
 * CartoAffect
 *
 * Module pour cartographier les affects
 *
 * @copyright Samuel Szoniecky, 2020
 */
namespace CartoAffect;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public $rsVocabularies = [
        ['prefix' => 'arcanes', 'label' => 'Arcanes'],
        ['prefix' => 'cito', 'label' => 'Gestion des citations'],
        ['prefix' => 'genex', 'label' => "Générateur d'expressions"],
        ['prefix' => 'geom', 'label' => 'IGN geometry'],
        ['prefix' => 'jdc', 'label' => 'Jardin des connaissances'],
        ['prefix' => 'lexinfo', 'label' => 'LexInfo'],
        ['prefix' => 'ma', 'label' => 'Ontology for Media Resources'],
        ['prefix' => 'plmk', 'label' => 'Polemika'],
        ['prefix' => 'schema', 'label' => 'schema.org'],
        ['prefix' => 'skos', 'label' => 'SKOS'],
    ];

    public $rsRessourceTemplate = [
        'Actant',
        'Cartographie sémantique',
        'Concept dans crible',
        'Crible',
        'Fragment aléatoire',
        'Histoire',
        'Indexation vidéo',
        'Position étoile',
        'Position sémantique : corrections',
        'Position sémantique : Geneva Emotion corrections',
        'Position sémantique : Geneva Emotion',
        'Position sémantique : sonar',
        'Position sémantique',
        'Processus CartoAffect',
        'Rapports entre concepts',
        'Scénario event',
        'Scénario Timeliner',
        'Scénario track',
        'Scénario',
    ];

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    protected function preInstall():void
    {
        //vérifie les dépendances
        $services = $this->getServiceLocator();
        $module = $services->get('Omeka\ModuleManager')->getModule('Generic');
        if ($module && version_compare($module->getIni('version'), '3.0.18', '<')) {
            $translator = $services->get('MvcTranslator');
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('This module requires the module "%s", version %s or above.'), // @translate
                'Generic', '3.0.18'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException($message);
        }
        $module = $services->get('Omeka\ModuleManager')->getModule('Annotate');
        if ($module && version_compare($module->getIni('version'), '3.1.2', '<')) {
            $translator = $services->get('MvcTranslator');
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('This module requires the module "%s", version %s or above.'), // @translate
                'Annotate', '3.1.2'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException($message);
        }
        $module = $services->get('Omeka\ModuleManager')->getModule('ValueSuggest');
        if ($module && version_compare($module->getIni('version'), '1.5.0', '<')) {
            $translator = $services->get('MvcTranslator');
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('This module requires the module "%s", version %s or above.'), // @translate
                'ValueSuggest', '1.5.0'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException($message);
        }
        $module = $services->get('Omeka\ModuleManager')->getModule('Generateur');
        if ($module && version_compare($module->getIni('version'), '3.0.3-alpha', '<')) {
            $translator = $services->get('MvcTranslator');
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('This module requires the module "%s", version %s or above.'), // @translate
                'Generateur', '3.0.3-alpha'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException($message);
        }
    }

    protected function postUninstall():void
    {
        $services = $this->getServiceLocator();

        if (!class_exists(\Generic\InstallResources::class)) {
            require_once file_exists(dirname(__DIR__) . '/Generic/InstallResources.php')
                ? dirname(__DIR__) . '/Generic/InstallResources.php'
                : __DIR__ . '/src/Generic/InstallResources.php';
        }

        $installResources = new \Generic\InstallResources($services);
        $installResources = $installResources();

        if (!empty($_POST['remove-vocabulary'])) {
            foreach ($this->rsVocabularies as $v) {
                $installResources->removeVocabulary($v['prefix']);
            }
        }

        if (!empty($_POST['remove-template'])) {
            foreach ($this->rsRessourceTemplate as $r) {
                $installResources->removeResourceTemplate($r);
            }
        }

        //parent::uninstall($services);
    }

    public function warnUninstall(Event $event): void
    {
        $view = $event->getTarget();
        $module = $view->vars()->module;
        if ($module->getId() != __NAMESPACE__) {
            return;
        }

        $serviceLocator = $this->getServiceLocator();
        $t = $serviceLocator->get('MvcTranslator');

        $html = '<p>';
        $html .= '<strong>';
        $html .= $t->translate('WARNING'); // @translate
        $html .= '</strong>' . ': ';
        $html .= '</p>';

        $html .= '<p>';
        $html .= $t->translate('All the annotations will be removed.'); // @translate
        $html .= '</p>';

        $html .= '<p>';
        $html .= $t->translate('If checked, the values of the vocabularies will be removed too. The class of the resources that use a class of these vocabularies will be reset.'); // @translate
        $html .= '</p>';
        $html .= '<label><input name="remove-vocabulary" type="checkbox" form="confirmform">';
        $html .= $t->translate('Remove the vocabularies :<br/>'); // @translate
        foreach ($this->rsVocabularies as $v) {
            $html .= '"' . $v['label'] . '"<br/>'; // @translate
        }
        $html .= '</label>';

        $html .= '<p>';
        $html .= $t->translate('If checked, the resource templates will be removed too. The resource template of the resources that use it will be reset.'); // @translate
        $html .= '</p>';
        $html .= '<label><input name="remove-template" type="checkbox" form="confirmform">';
        $html .= $t->translate('Remove the resource templates :<br/>'); // @translate
        foreach ($this->rsRessourceTemplate as $rt) {
            $html .= '"' . $rt . '"<br/>'; // @translate
        }
        $html .= '</label>';

        echo $html;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Display a warn before uninstalling.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Module',
            'view.details',
            [$this, 'warnUninstall']
        );
    }
}
