<?php declare(strict_types=1);

namespace CartoAffect;

return [

    'view_helpers' => [
        'factories' => [
            'cartoAffect' => Service\ViewHelper\CartoAffectFactory::class,
            'crible' => Service\ViewHelper\CribleFactory::class,
            'entityRelation' => Service\ViewHelper\EntityRelationFactory::class,
            'querySql' => Service\ViewHelper\QuerySqlFactory::class,
            'diagramme' => Service\ViewHelper\DiagrammeFactory::class,
            'scenario' => Service\ViewHelper\ScenarioFactory::class,
            'google' => Service\ViewHelper\GoogleFactory::class,
            'cartoHexa' => Service\ViewHelper\CartoHexaFactory::class,
        ],
        // Pour compatibilité avec les anciens thèmes
        'aliases' => [
            'CartoAffectViewHelper' => 'cartoAffect',
            'CribleViewHelper' => 'cribleView',
            'EntityRelationViewHelper' => 'entityRelation',
            'QuerySqlViewHelper' => 'querySql',
            'DiagrammeViewHelper' => 'diagramme',
            'ScenarioViewHelper' => 'scenarioView',
            'GoogleViewHelper' => 'googleView',
            'CartoHexaViewHelper' => 'cartoHexa',
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'cartoaffect' => [
        'config' => [
            'cartoaffect_mail' => 'anonyme.cartoaffect@univ-paris8.fr',
            'cartoaffect_pwd' => 'anonyme',
            'ajouteAnnotation' => 1,
        ],
    ],

];
