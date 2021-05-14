<?php
namespace CartoAffect;

return [
    'view_helpers' => [
        
        'invokables' => [
            'CartoAffectViewHelper' => View\Helper\CartoAffectViewHelper::class,
            'CribleViewHelper' => View\Helper\CribleViewHelper::class,
        ],
                
        'factories'  => [
            'CartoAffectFactory' => Service\ViewHelper\CartoAffectFactory::class,
            'CribleFactory' => Service\ViewHelper\CribleFactory::class,
        ],

    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'CartoAffect' => [
        'config' => [
            'cartoaffect_mail' => 'anonyme.polemika@univ-paris8.fr',
            'cartoaffect_pwd' => 'anonyme'
        ],
    ],


];