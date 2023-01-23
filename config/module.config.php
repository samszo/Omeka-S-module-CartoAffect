<?php declare(strict_types=1);

namespace CartoAffect;

return [

    'view_helpers' => [
        'factories' => [
            'cartoAffect' => Service\ViewHelper\CartoAffectFactory::class,
            'cartoHexa' => Service\ViewHelper\CartoHexaFactory::class,
            'crible' => Service\ViewHelper\CribleFactory::class,
            'diagramme' => Service\ViewHelper\DiagrammeFactory::class,
            'entityRelation' => Service\ViewHelper\EntityRelationFactory::class,
            'google' => Service\ViewHelper\GoogleFactory::class,
            'querySql' => Service\ViewHelper\QuerySqlFactory::class,
            'scenario' => Service\ViewHelper\ScenarioFactory::class,
        ],
        // Pour compatibilité avec les anciens thèmes
        'aliases' => [
            'CartoAffectViewHelper' => 'cartoAffect',
            'CartoHexaViewHelper' => 'cartoHexa',
            'CribleViewHelper' => 'cribleView',
            'DiagrammeViewHelper' => 'diagramme',
            'EntityRelationViewHelper' => 'entityRelation',
            'GoogleViewHelper' => 'googleView',
            'QuerySqlViewHelper' => 'querySql',
            'ScenarioViewHelper' => 'scenarioView',
            'CartoAffectFactory' => 'cartoAffect',
            'CartoHexaFactory' => 'cartoHexa',
            'CribleFactory' => 'cribleView',
            'DiagrammeFactory' => 'diagramme',
            'EntityRelationFactory' => 'entityRelation',
            'GoogleFactory' => 'googleView',
            'QuerySqlFactory' => 'querySql',
            'ScenarioFactory' => 'scenarioView',
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
