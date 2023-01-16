<?php declare(strict_types=1);

namespace CartoAffect;

use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$config = $services->get('Config');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (!class_exists(\Generic\InstallResources::class)) {
    if (file_exists(dirname(__DIR__, 3) . '/Generic/InstallResources.php')) {
        require_once dirname(__DIR__, 3) . '/Generic/InstallResources.php';
    } elseif (file_exists(dirname(__DIR__, 2) . '/src/Generic//InstallResources.php')) {
        require_once dirname(__DIR__, 2) . '/src/Generic//InstallResources.php';
    } else {
        // Nothing to install.
        return $this;
    }
}
$installResources = new \Generic\InstallResources($services);

// Installation du crible étoile.
$rc = ['jdc:Crible', 'skos:Concept'];
foreach ($rc as $v) {
    $rs[$v] = $api->search('resource_classes', ['term' => $v])->getContent()[0];
}
$rt = ['Crible', 'Concept dans crible'];
foreach ($rt as $v) {
    $rs[$v] = $api->search('resource_templates', ['label' => $v])->getContent()[0];
}
$props = [
    'dcterms:title',
    'plmk:hasIcon',
    'skos:inScheme',
    'schema:color',
    'jdc:ordreCrible',
    'jdc:hasCrible',
    'jdc:hasCribleCarto',
    'dcterms:isReferencedBy',
    'dcterms:description',
    'schema:repeatCount',
    'schema:actionApplication',
    'schema:targetCollection',
];
foreach ($props as $v) {
    $rs[$v] = $api->search('properties', ['term' => $v])->getContent()[0];
}

// Création des cribles.
$cribles = [
    ['dcterms:title' => 'Evaluation étoiles'],
];
foreach ($cribles as $v) {
    $oItem = [];
    $oItem['o:resource_class'] = ['o:id' => $rs['jdc:Crible']->id()];
    $oItem['o:resource_template'] = ['o:id' => $rs['Crible']->id()];
    foreach ($v as $p => $d) {
        $valueObject = [];
        $valueObject['property_id'] = $rs[$p]->id();
        $valueObject['@value'] = $d;
        $valueObject['type'] = 'literal';
        $oItem[$rs[$p]->term()][] = $valueObject;
    }
    $result = $api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
    $rs[$v['dcterms:title']] = $result->id();
}

// Création des concepts.
$concepts = [
    [
        'dcterms:title' => 'Etoile',
        'plmk:hasIcon' => '☆',
        'schema:color' => '#c13b6c',
        'jdc:ordreCrible' => '1',
        'skos:inScheme' => [
            'Evaluation étoiles',
        ],
    ],
];
foreach ($concepts as $v) {
    $oItem = [];
    $oItem['o:resource_class'] = ['o:id' => $rs['skos:Concept']->id()];
    $oItem['o:resource_template'] = ['o:id' => $rs['Concept dans crible']->id()];
    foreach ($v as $p => $d) {
        if ($p == 'skos:inScheme') {
            foreach ($d as $s) {
                $valueObject = [];
                $valueObject['value_resource_id'] = $rs[$s];
                $valueObject['property_id'] = $rs[$p]->id();
                $valueObject['type'] = 'resource';
                $oItem[$rs[$p]->term()][] = $valueObject;
            }
        } else {
            $valueObject = [];
            $valueObject['property_id'] = $rs[$p]->id();
            $valueObject['@value'] = $d;
            $valueObject['type'] = 'literal';
            $oItem[$rs[$p]->term()][] = $valueObject;
        }
    }
    $result = $api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
}

// Installation des cribles emotionGeneva.
$rc = ['jdc:Crible', 'skos:Concept', 'schema:Action'];
foreach ($rc as $v) {
    $rs[$v] = $api->search('resource_classes', ['term' => $v])->getContent()[0];
}
$rt = ['Crible', 'Concept dans crible'];
foreach ($rt as $v) {
    $rs[$v] = $api->search('resource_templates', ['label' => $v])->getContent()[0];
}
$props = [
    'dcterms:title',
    'plmk:hasIcon',
    'skos:inScheme',
    'schema:color',
    'jdc:ordreCrible',
    'jdc:hasCrible',
    'jdc:hasCribleCarto',
    'dcterms:isReferencedBy',
    'dcterms:description',
    'schema:repeatCount',
    'schema:actionApplication',
    'schema:targetCollection',
];
foreach ($props as $v) {
    $rs[$v] = $api->search('properties', ['term' => $v])->getContent()[0];
}

// Création des cribles.
$cribles = [
    ['dcterms:title' => 'Partage'],
    ['dcterms:title' => 'Contrôle'],
    ['dcterms:title' => 'Concernement'],
    ['dcterms:title' => 'Valence'],
    ['dcterms:title' => 'Pertinence'],
    [
        'dcterms:title' => 'Rapports aux émotions',
        'jdc:hasCrible' => [
            'Partage',
            'Contrôle',
            'Concernement',
            'Valence',
            'Pertinence',
        ],
    ],
    [
        'dcterms:title' => 'Emotions Geneva',
        'jdc:hasCrible' => [
            'Contrôle', 'Valence'],
        'jdc:hasCribleCarto' => 'emotionsGeneva',
        'dcterms:isReferencedBy' => 'https://www.researchgate.net/publication/280880848_Geneva_Emotion_Wheel_Rating_Study',
    ],
];
foreach ($cribles as $v) {
    $oItem = [];
    $oItem['o:resource_class'] = ['o:id' => $rs['jdc:Crible']->id()];
    $oItem['o:resource_template'] = ['o:id' => $rs['Crible']->id()];
    foreach ($v as $p => $d) {
        if ($p == 'jdc:hasCrible') {
            foreach ($d as $s) {
                $valueObject = [];
                $valueObject['value_resource_id'] = $rs[$s];
                $valueObject['property_id'] = $rs[$p]->id();
                $valueObject['type'] = 'resource';
                $oItem[$rs[$p]->term()][] = $valueObject;
            }
        } else {
            $valueObject = [];
            $valueObject['property_id'] = $rs[$p]->id();
            $valueObject['@value'] = $d;
            $valueObject['type'] = 'literal';
            $oItem[$rs[$p]->term()][] = $valueObject;
        }
    }
    $result = $api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
    $rs[$v['dcterms:title']] = $result->id();
}

// Création des collections.
$collections = ['Concepts pour les émotions'];
foreach ($collections as $c) {
    $oItem = [];
    $valueObject = [];
    $valueObject['property_id'] = $rs['dcterms:title']->id();
    $valueObject['@value'] = $c;
    $valueObject['type'] = 'literal';
    $oItem[$rs[$p]->term()][] = $valueObject;
    $result = $api->create('item_sets', $oItem, [], ['continueOnError' => true])->getContent();
    $rs[$c] = $result->id();
}

// Création des concepts.
$concepts = [
    ['dcterms:title' => 'colère', 'plmk:hasIcon' => '😡', 'schema:color' => '#c13b6c', 'jdc:ordreCrible' => '16', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'mépris', 'plmk:hasIcon' => '🙄', 'schema:color' => '#aa348b', 'jdc:ordreCrible' => '15', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'dégout', 'plmk:hasIcon' => '🤢', 'schema:color' => '#4403ff', 'jdc:ordreCrible' => '14', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'envie', 'plmk:hasIcon' => '🤨', 'schema:color' => '#1e1ce4', 'jdc:ordreCrible' => '13', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'culpabilité', 'plmk:hasIcon' => '😟', 'schema:color' => '#569ec0', 'jdc:ordreCrible' => '12', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'honte', 'plmk:hasIcon' => '😳', 'schema:color' => '#45b3b9', 'jdc:ordreCrible' => '11', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'peur', 'plmk:hasIcon' => '😱', 'schema:color' => '#01f7e2', 'jdc:ordreCrible' => '10', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'tristesse', 'plmk:hasIcon' => '😢', 'schema:color' => '#00f98a', 'jdc:ordreCrible' => '09', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'surprise', 'plmk:hasIcon' => '😮', 'schema:color' => '#0bad00', 'jdc:ordreCrible' => '08', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'intérêt', 'plmk:hasIcon' => '🤔', 'schema:color' => '#52dc00', 'jdc:ordreCrible' => '07', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'espoir', 'plmk:hasIcon' => '🤗', 'schema:color' => '#b6f603', 'jdc:ordreCrible' => '06', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'soulagement', 'plmk:hasIcon' => '😌', 'schema:color' => '#ebfe0f', 'jdc:ordreCrible' => '05', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'satisfaction', 'plmk:hasIcon' => '🙂', 'schema:color' => '#fbfd00', 'jdc:ordreCrible' => '04', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'joie', 'plmk:hasIcon' => '😀', 'schema:color' => '#f8881a', 'jdc:ordreCrible' => '03', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'allégresse', 'plmk:hasIcon' => '😇', 'schema:color' => '#e38700', 'jdc:ordreCrible' => '02', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'fierté', 'plmk:hasIcon' => '😊', 'schema:color' => '#fa0204', 'jdc:ordreCrible' => '01', 'skos:inScheme' => ['Emotions Geneva']],
    ['dcterms:title' => 'avec tous', 'skos:inScheme' => ['Partage'], 'jdc:ordreCrible' => '2'],
    ['dcterms:title' => 'aucun', 'skos:inScheme' => ['Partage', 'Contrôle'], 'jdc:ordreCrible' => '1'],
    ['dcterms:title' => 'moi', 'skos:inScheme' => ['Concernement'], 'jdc:ordreCrible' => '2'],
    ['dcterms:title' => 'les autres', 'skos:inScheme' => ['Concernement'], 'jdc:ordreCrible' => '1'],
    ['dcterms:title' => 'plaisant', 'skos:inScheme' => ['Valence'], 'jdc:ordreCrible' => '2'],
    ['dcterms:title' => 'déplaisant', 'skos:inScheme' => ['Valence'], 'jdc:ordreCrible' => '1'],
    ['dcterms:title' => 'très', 'skos:inScheme' => ['Contrôle', 'Pertinence'], 'jdc:ordreCrible' => '2'],
    ['dcterms:title' => 'pas', 'skos:inScheme' => ['Pertinence'], 'jdc:ordreCrible' => '1'],
];
foreach ($concepts as $v) {
    $oItem = [];
    $oItem['o:resource_class'] = ['o:id' => $rs['skos:Concept']->id()];
    $oItem['o:resource_template'] = ['o:id' => $rs['Concept dans crible']->id()];
    $oItem['o:item_set'] = [['o:id' => $rs['Concepts pour les émotions']]];
    foreach ($v as $p => $d) {
        if ($p == 'skos:inScheme') {
            foreach ($d as $s) {
                $valueObject = [];
                $valueObject['value_resource_id'] = $rs[$s];
                $valueObject['property_id'] = $rs[$p]->id();
                $valueObject['type'] = 'resource';
                $oItem[$rs[$p]->term()][] = $valueObject;
            }
        } else {
            $valueObject = [];
            $valueObject['property_id'] = $rs[$p]->id();
            $valueObject['@value'] = $d;
            $valueObject['type'] = 'literal';
            $oItem[$rs[$p]->term()][] = $valueObject;
        }
    }
    $result = $api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
}

// Création des actions.
$actions = [
    [
        'dcterms:title' => ['Rapports aux émotions'],
        'dcterms:description' => [
            'Pour commencer : positionnez votre état émotionnel présent.',
            'Evaluez vos émotions face aux informations.',
            'Corrigez les émotions calculées.',
            'Observez les résultats.',
            'Merci pour votre attention',
        ],
        'schema:targetCollection' => '4',
        'schema:repeatCount' => ['1', '4', '1', '1', '1'],
        'jdc:hasCrible' => ['Rapports aux émotions'],
        'schema:actionApplication' => ['none', 'getItemForEval', 'setCorItemEval', 'showResult', 'showEnd'],
        'media' => [
            'url' => 'https://gallica.bnf.fr/iiif/ark:/12148/bpt6k1352510/f31/465.5044357416868,666.2148337595909,2179.808184143222,2793.4271099744246/373,478/0/native.jpg',
            'dcterms:title' => 'rire',
        ],
    ],
];
foreach ($actions as $v) {
    $oItem = [];
    $oItem['o:resource_class'] = ['o:id' => $rs['schema:Action']->id()];
    foreach ($v as $p => $d) {
        if ($p == 'jdc:hasCrible') {
            foreach ($d as $s) {
                $valueObject = [];
                $valueObject['value_resource_id'] = $rs[$s];
                $valueObject['property_id'] = $rs[$p]->id();
                $valueObject['type'] = 'resource';
                $oItem[$rs[$p]->term()][] = $valueObject;
            }
        } elseif ($p == 'schema:targetCollection') {
            $valueObject = [];
            $valueObject['value_resource_id'] = $d;
            $valueObject['property_id'] = $rs[$p]->id();
            $valueObject['type'] = 'resource';
            $oItem[$rs[$p]->term()][] = $valueObject;
        } elseif ($p == 'media') {
            $oItem['o:media'][] = [
                'o:ingester' => 'url',
                'o:source' => $d['url'],
                'ingest_url' => $d['url'],
                $rs['dcterms:title']->term() => [
                    [
                        '@value' => $d['dcterms:title'],
                        'property_id' => $rs['dcterms:title']->id(),
                        'type' => 'literal',
                    ],
                ],
            ];
        } else {
            foreach ($d as $s) {
                $valueObject = [];
                $valueObject['property_id'] = $rs[$p]->id();
                $valueObject['@value'] = $s;
                $valueObject['type'] = 'literal';
                $oItem[$rs[$p]->term()][] = $valueObject;
            }
        }
    }
    $result = $api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
}
