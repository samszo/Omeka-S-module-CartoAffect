<?php
namespace CartoAffect;

return [
    
    'view_helpers' => [
        
        'invokables' => [
            'CartoAffectViewHelper' => View\Helper\CartoAffectViewHelper::class,
            'CribleViewHelper' => View\Helper\CribleViewHelper::class,
            'EntityRelationViewHelper' => View\Helper\EntityRelationViewHelper::class,
            'QuerySqlViewHelper' => View\Helper\QuerySqlViewHelper::class,
            'DiagrammeViewHelper' => View\Helper\DiagrammeViewHelper::class,
            'ScenarioViewHelper' => View\Helper\ScenarioViewHelper::class,
            'GoogleViewHelper' => View\Helper\GoogleViewHelper::class,
            'CartoHexaViewHelper' => View\Helper\CartoHexaViewHelper::class,
        ],
                
        'factories'  => [
            'CartoAffectFactory' => Service\ViewHelper\CartoAffectFactory::class,
            'CribleFactory' => Service\ViewHelper\CribleFactory::class,
            'EntityRelationFactory' => Service\ViewHelper\EntityRelationFactory::class,
            'QuerySqlFactory' => Service\ViewHelper\QuerySqlFactory::class,
            'DiagrammeFactory' => Service\ViewHelper\DiagrammeFactory::class,
            'ScenarioFactory' => Service\ViewHelper\ScenarioFactory::class,
            'GoogleFactory' => Service\ViewHelper\GoogleFactory::class,
            'CartoHexaFactory' => Service\ViewHelper\CartoHexaFactory::class
        ],

    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'CartoAffect' => [
        'config' => [
            'cartoaffect_mail' => 'anonyme.cartoaffect@univ-paris8.fr',
            'cartoaffect_pwd' => 'anonyme',
            'ajouteAnnotation'=> 1
        ],
    ],


];