<?php
namespace CartoAffect;

return [
    'view_helpers' => [
        
        'invokables' => [
            'CartoAffectViewHelper' => View\Helper\CartoAffectViewHelper::class,
        ],
                
        'factories'  => [
            'CartoAffectFactory' => Service\ViewHelper\CartoAffectFactory::class,
        ],

    ],
];