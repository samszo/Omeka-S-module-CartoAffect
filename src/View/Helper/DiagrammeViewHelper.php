<?php declare(strict_types=1);
namespace CartoAffect\View\Helper;

use DateTime;
use Laminas\View\Helper\AbstractHelper;

class DiagrammeViewHelper extends AbstractHelper
{
    protected $api;
    protected $props;
    protected $rcs;
    protected $rts;
    protected $rs;
    protected $view;
    protected $doublons;
    protected $nodes;
    protected $tags;
    protected $links;
    protected $url;
    protected $user;
    protected $styleNodeSrcDefault = "border-color:#ff0000;background-color:#000000;color:#ffffff;border-width:3px;font-weight:none;font-style:none ";
    protected $styleNodeDstDefault = "border-color:green;background-color:#ffffff;color:#000000;border-width:3px;font-weight:none;font-style:none";
    protected $styleLinkDefault = '{"from-pos":"center","to-pos":"center","from-pos":"center","to-pos":"center","color":"#000000","lineStyle":""}';

    public function __construct($api, $serverUrlHelper, $acl, $em)
    {
        $this->api = $api;
        $this->url = $serverUrlHelper(true);
        $this->acl = $acl;
        $this->em = $em;
    }

    /**
     * Gestion de l'éditeur de cartographie
     *
     * @param array     $params paramètre de l'action
     * @return array
     */
    public function __invoke($params = [])
    {
        if ($params == []) {
            return[];
        }
        $this->props = $params['props'];
        switch ($params['query']['action']) {
            case 'getArchetype':
                $oItem = $this->api->read('items', $params['query']['id'])->getContent();
                $this->rs[] = $this->getArchetype($oItem, $params['query']['idDiagram']);
                break;
            case 'getArchetypes':
                $this->getArchetypes($params);
                break;
            case 'changeDiagramme':
                $this->rs = $this->changeDiagramme($params);
                break;
            case 'deleteDiagramme':
                $this->rs = $this->deleteDiagramme($params['query']['id']);
                break;
            case 'renameDiagramme':
                $this->rs = $this->renameDiagramme($params['query']['id'], $params['query']['label']);
                break;
            case 'getDiagrammes':
                $this->getDiagrammes();
                break;
            case 'getDiagramme':
                $this->getDiagramme($params);
                break;
            case 'newDiagramme':
                $this->createDiagramme($params['query']);
                break;
            case 'saveArchetype':
                $this->saveArchetype($params);
                break;
        }

        return $this->rs;
    }

    /**
     * renomme un diagramme dans omeka
     *
     * @param int       $id
     * @param string    $label
     * @return array
     */
    public function renameDiagramme($id, $label)
    {
        $result = [];
        $oItem = $this->api->read('items', $id)->getContent();
        $rs = $oItem->userIsAllowed('update');
        if ($rs) {
            //récupère les données de l'item
            $data = json_decode(json_encode($oItem), true);
            $d = new DateTime('NOW');
            if (isset($data['dcterms:modified'])) {
                $data['dcterms:modified'][0]['@value'] = $d->format('c');
            } else {
                $data['dcterms:modified'][] = ['@value' => $d->format('c'),'property_id' => $this->getProp('dcterms:modified')->id(),'type' => 'literal'];
            }
            $data['dcterms:title'][0]['@value'] = $label;
            $this->api->update('items', $id, $data, [], ['continueOnError' => true,'isPartial' => 1, 'collectionAction' => 'replace']);
            $result = 1;
        } else {
            $result = ['error' => "droits insuffisants",'message' => "Vous n'avez pas le droit de modifier ce diagramme."];
        }
        return $result;
    }

    /**
     * supprime un diagramme dans omeka
     *
     * @param int       $id
     * @param string    $label
     * @return array
     */
    public function deleteDiagramme($id)
    {
        $result = [];
        $oItem = $this->api->read('items', $id)->getContent();
        $rs = $oItem->userIsAllowed('delete');
        if ($rs) {
            //récupère les identifiants des géométries et des archétypes liés
            $ids = ['geo' => [],'arc' => []];
            $geos = $oItem->value('geom:geometry', ['all' => true]);
            foreach ($geos as $geo) {
                $g = $geo->valueResource();
                if ($g->value('jdc:hasArchetype')) {
                    $arc = $g->value('jdc:hasArchetype')->valueResource();
                    $ids['arc'][$arc->id()] = 1;
                }
                $ids['geo'][] = $g->id();
            }
            //supression dans l'ordre item -> linked ressource
            $ids = array_merge([$id], array_keys($ids['arc']), $ids['geo']);
            $response = $this->api->batchDelete('items', $ids, [], ['continueOnError' => true]);
            $result = 1;
        } else {
            $result = ['error' => "droits insuffisants",'message' => "Vous n'avez pas le droit de supprimer ce diagramme."];
        }
        return $result;
    }

    /**
     * applique les changements du diagramme dans omeka
     *
     * @param array     $params
     * @return array
     */
    public function changeDiagramme($params)
    {
        $result = [];
        $this->user = $params['user'];
        $rs = $this->acl->userIsAllowed(null, 'update');
        if ($rs) {
            foreach ($params['post'] as $k => $vs) {
                foreach ($vs as $v) {
                    switch ($k . $v['kind']) {
                        case 'updatedarchetype':
                            $result[] = $this->saveArchetype($v);
                            break;
                        case 'updatednode':
                            $result[] = $this->saveNode($v);
                            break;
                        case 'creatednode':
                            $result[] = $this->createNode($v);
                            break;
                        case 'createdlink':
                            $result[] = $this->createLink($v);
                            break;
                        case 'deletednode':
                        case 'deletedlink':
                            $result[] = $this->deleteGeo($v);
                            break;
                    }
                }
            }
        } else {
            $result = ['error' => "droits insuffisants",'message' => "Vous n'avez pas le droit de modifier ce diagramme."];
        }

        return $result;
    }

    public function deleteGeo($params)
    {
        $rs = $this->api->delete('items', $params['id']);
        return $rs;
    }

    public function createDiagramme($params)
    {
        if ($this->acl->userIsAllowed(null, 'create')) {
            //creation de la carte générale
            $data = [];
            $rt = $this->getRt('Cartographie des expressions');
            $rc = $this->getRc('plmk:CarteExpression');
            $d = new DateTime('NOW');
            $data['o:resource_class'] = ['o:id' => $rc->id()];
            $data['o:resource_template'] = ['o:id' => $rt->id()];
            $data['dcterms:title'][] = ['type' => 'literal',
                '@value' => $params['kind'] . ' : ' . $params['label'],
                'property_id' => $this->getProp('dcterms:title')->id()];
            $data['dcterms:created'][] = ['@value' => $d->format('c'),'property_id' => $this->getProp('dcterms:created')->id(),'type' => 'literal'];
            $oItem = $this->api->create('items', $data, [], ['continueOnError' => true])->getContent();
            //ajoute un noeud source
            $src = $this->createNode(['idDiagram' => $oItem->id(),'kind' => 'node','label' => 'source','x' => '200','y' => '200','cssStyle' => $this->styleNodeSrcDefault]);
            //ajoute un noeud destination
            $dst = $this->createNode(['idDiagram' => $oItem->id(),'kind' => 'node','label' => 'destination','x' => '400','y' => '200','cssStyle' => $this->styleNodeDstDefault]);
            //ajoute un lien source->destination
            $this->createLink(['idDiagram' => $oItem->id(),'kind' => 'link','src' => $src->id(),'dst' => $dst->id(),'x' => '400','y' => '200','style' => $this->styleLinkDefault]);
            return $this->getDiagramme(false, $oItem);
        } else {
            return ['error' => "droits insuffisants",'message' => "Vous n'avez pas le droit d'ajouter un diagramme."];
        }
    }

    public function createNode($params)
    {
        $rt = $this->getRt('Espace sémantique');
        $rc = $this->getRc('geom:Envelope');

        //création de l'enveloppe
        $d = new DateTime('NOW');
        $data = [];
        $data['o:resource_class'] = ['o:id' => $rc->id()];
        $data['o:resource_template'] = ['o:id' => $rt->id()];
        $data['dcterms:title'][] = ['type' => 'literal',
            '@value' => $params['kind'] . ' : ' . $params['label'],
            'property_id' => $this->getProp('dcterms:title')->id()];
        $data['dcterms:created'][] = ['@value' => $d->format('c'),'property_id' => $this->getProp('dcterms:created')->id(),'type' => 'literal'];
        $data['geom:coordX'][] = ['@value' => $params['x'],'property_id' => $this->getProp('geom:coordX')->id(),'type' => 'literal'];
        $data['geom:coordY'][] = ['@value' => $params['y'],'property_id' => $this->getProp('geom:coordY')->id(),'type' => 'literal'];
        $data['dcterms:type'][] = ['@value' => $params['kind'],'property_id' => $this->getProp('dcterms:type')->id(),'type' => 'literal'];
        if (isset($params['cssStyle'])) {
            $data['oa:styleClass'][] = ['@value' => $params['cssStyle'],'property_id' => $this->getProp('oa:styleClass')->id(),'type' => 'literal'];
        }
        if (isset($params['idArchetype'])) {
            $data['jdc:hasArchetype'][] = ['property_id' => $this->getProp('jdc:hasArchetype')->id(),'value_resource_id' => $params['idArchetype'],'type' => 'resource'];
        }
        //récupère le tag
        $tag = $this->getTag($params['label']);
        $data['skos:semanticRelation'][] = ['property_id' => $this->getProp('skos:semanticRelation')->id(),'value_resource_id' => $tag->id(),'type' => 'resource'];
        //création de l'item
        $oItem = $this->api->create('items', $data, [], ['continueOnError' => true])->getContent();
        if (isset($params['id'])) {
            $this->nodes[$params['id']] = $oItem->id();
        }

        //ajoute la relation à la carte
        $data = [];
        $data['geom:geometry'][] = ['property_id' => $this->getProp('geom:geometry')->id(),'value_resource_id' => $oItem->id(),'type' => 'resource'];
        $this->api->update('items', $params['idDiagram'], $data, [], ['isPartial' => true, 'continueOnError' => true, 'collectionAction' => 'append']);

        return $oItem;
    }

    public function createLink($params)
    {
        //$rt =  $this->getRt('Espace sémantique');
        $rc = $this->getRc('geom:Line');

        //création de la ligne
        $d = new DateTime('NOW');
        $data = [];
        $data['o:resource_class'] = ['o:id' => $rc->id()];
        //$data['o:resource_template'] = ['o:id' => $rt->id()];
        $data['dcterms:created'][] = ['@value' => $d->format('c'),'property_id' => $this->getProp('dcterms:created')->id(),'type' => 'literal'];
        $data['oa:styleClass'][] = ['@value' => json_encode($params['style']),'property_id' => $this->getProp('oa:styleClass')->id(),'type' => 'literal'];
        $data['dcterms:type'][] = ['@value' => $params['kind'],'property_id' => $this->getProp('dcterms:type')->id(),'type' => 'literal'];
        if (isset($params['idArchetype'])) {
            $data['jdc:hasArchetype'][] = ['property_id' => $this->getProp('jdc:hasArchetype')->id(),'value_resource_id' => $params['idArchetype'],'type' => 'resource'];
        }
        $data['ma:isSourceOf'][] = ['property_id' => $this->getProp('ma:isSourceOf')->id(),
            'value_resource_id' => $params['dst'] < 0 ? $this->nodes[$params['dst']] : $params['dst'],
            'type' => 'resource'];
        $data['ma:hasSource'][] = ['property_id' => $this->getProp('ma:hasSource')->id(),
            'value_resource_id' => $params['src'] < 0 ? $this->nodes[$params['src']] : $params['src'],
            'type' => 'resource'];
        $data['dcterms:title'][] = ['type' => 'literal',
            '@value' => $params['kind'] . ' : ' . $data['ma:hasSource'][0]['value_resource_id'] . ' -> ' . $data['ma:isSourceOf'][0]['value_resource_id'],
            'property_id' => $this->getProp('dcterms:title')->id()];
        //création de l'item
        $oItem = $this->api->create('items', $data, [], ['continueOnError' => true])->getContent();

        //ajoute la relation à la carte
        $data = [];
        $data['geom:geometry'][] = ['property_id' => $this->getProp('geom:geometry')->id(),'value_resource_id' => $oItem->id(),'type' => 'resource'];
        $this->api->update('items', $params['idDiagram'], $data, [], ['isPartial' => true, 'continueOnError' => true, 'collectionAction' => 'append']);

        return $oItem;
    }

    /**
     * Ajoute un tag au format skos
     *
     * @param array $tag
     * @return object
     */
    protected function getTag($tag)
    {
        if (isset($this->tags[$tag])) {
            $oTag = $this->tags[$tag];
        } else {
            //vérifie la présence de l'item pour gérer la création
            $param = [];
            $param['property'][0]['property'] = $this->getProp('skos:prefLabel')->id() . "";
            $param['property'][0]['type'] = 'eq';
            $param['property'][0]['text'] = $tag;
            //$this->logger->info("RECHERCHE PARAM = ".json_encode($param));
            $result = $this->api->search('items', $param)->getContent();
            //$this->logger->info("RECHERCHE ITEM = ".json_encode($result));
            //$this->logger->info("RECHERCHE COUNT = ".count($result));
            if (count($result)) {
                $oTag = $result[0];
            //$this->logger->info("ID TAG EXISTE".$result[0]->id()." = ".json_encode($result[0]));
            } else {
                $param = [];
                $param['o:resource_class'] = ['o:id' => $this->getRc('skos:Concept')->id()];
                $valueObject = [];
                $valueObject['property_id'] = $this->getProp('dcterms:title')->id();
                $valueObject['@value'] = $tag;
                $valueObject['type'] = 'literal';
                $param['dcterms:title'][] = $valueObject;
                $valueObject = [];
                $valueObject['property_id'] = $this->getProp('skos:prefLabel')->id();
                $valueObject['@value'] = $tag;
                $valueObject['type'] = 'literal';
                $param['skos:prefLabel'][] = $valueObject;
                //création du tag
                $oTag = $this->api->create('items', $param, [], ['continueOnError' => true])->getContent();
            }
            $this->tags[$tag] = $oTag;
        }
        return $oTag;
    }

    public function saveNode($params)
    {
        //récupère les données du noeud
        $oItem = $this->api->read('items', $params['id'])->getContent();
        $data = json_decode(json_encode($oItem), true);

        //récupère le concept
        $oConcept = $this->api->read('items', $params['idConcept'])->getContent();
        //vérifie s'il faut changer le concept
        if ($oConcept->displayTitle() != $params['label']) {
            $tag = $this->getTag($params['label']);
            $data['skos:semanticRelation'][0]['value_resource_id'] = $tag->id();
        }
        //vérifie s'il faut changer l'archétype
        if (isset($params['cssStyle'])) {
            $arc = $this->setArchetype($oItem->displayResourceClassLabel(), $params['cssStyle'], $params['idDiagram']);
            if (isset($data['oa:styleClass'])) {
                $data['oa:styleClass'][0]['@value'] = $params['cssStyle'];
            } else {
                $data['oa:styleClass'][] = ['@value' => $params['cssStyle'],'property_id' => $this->getProp('oa:styleClass')->id(),'type' => 'literal'];
            }
            if (isset($data['jdc:hasArchetype'])) {
                $data['jdc:hasArchetype'][0]['value_resource_id'] = $arc->id();
            } else {
                $data['jdc:hasArchetype'][] = ['value_resource_id' => $arc->id(),'property_id' => $this->getProp('jdc:hasArchetype')->id(),'type' => 'resource'];
            }
        }

        $data['dcterms:title'][0]['@value'] = 'node : ' . $params['label'];
        $data['geom:coordX'][0]['@value'] = $params['x'];
        $data['geom:coordY'][0]['@value'] = $params['y'];
        $d = new DateTime('NOW');
        if (isset($data['dcterms:modified'])) {
            $data['dcterms:modified'][0]['@value'] = $d->format('c');
        } else {
            $data['dcterms:modified'][] = ['@value' => $d->format('c'),'property_id' => $this->getProp('dcterms:modified')->id(),'type' => 'literal'];
        }

        return $this->api->update('items', $params['id'], $data, [], ['continueOnError' => true,'isPartial' => 1, 'collectionAction' => 'replace']);
    }

    public function saveArchetype($params)
    {
        $oItem = [];
        $valueObject = [];
        $valueObject['@value'] = $params['cssStyle'];
        $valueObject['property_id'] = $this->getProp('dcterms:description')->id();
        $valueObject['type'] = 'literal';
        $oItem['dcterms:description'][] = $valueObject;
        $valueObject = [];
        $valueObject['@value'] = $params['name'];
        $valueObject['property_id'] = $this->getProp('dcterms:title')->id();
        $valueObject['type'] = 'literal';
        $oItem['dcterms:title'][] = $valueObject;
        $valueObject = [];
        $valueObject['@value'] = $params['type'];
        $valueObject['property_id'] = $this->getProp('dcterms:type')->id();
        $valueObject['type'] = 'literal';
        $oItem['dcterms:type'][] = $valueObject;
        return $this->api->update('items', $params['id'], $oItem, [], ['continueOnError' => true,'isPartial' => 1, 'collectionAction' => 'replace']);
    }

    public function getDiagrammes(): void
    {
        //récupère toute les carte d'expression
        $query = [
            'resource_class_id' => $this->props['plmk:CarteExpression']->id(),
        ];
        $items = $this->api->search('items', $query, ['limit' => 0])->getContent();
        //construction des résultats
        foreach ($items as $i) {
            $this->rs[] = $this->getDiagrammeInfo($i);
        }
    }

    public function getDiagramme($p, $oItem = false): void
    {
        if (!$oItem) {
            $oItem = $this->api->read('items', $p['query']['id'])->getContent();
        }
        $this->rs = $this->getDiagrammeInfo($oItem);
        $geos = $oItem->value('geom:geometry', ['all' => true]);
        foreach ($geos as $geo) {
            $this->getGeoInfo($geo->valueResource(), $oItem->id());
        }
    }

    public function getArchetypes($p): void
    {
        $oItem = $this->api->read('items', $p['query']['id'])->getContent();
        $geos = $oItem->value('geom:geometry', ['all' => true]);
        foreach ($geos as $geo) {
            $arc = $this->getArchetype($geo->valueResource(), $p['query']['id']);
            if (!isset($this->doublons[$arc['id']])) {
                $this->rs[] = $arc;
                $this->doublons[$arc['id']] = true;
            }
        }
    }

    /**
     * création d'un archétype en relation avec une géomatrie d'un diagramme
     *
     * @param object     $oItem
     * @param int        $idDiagram
     * @return array
     */
    public function getArchetype($item, $idDiagram)
    {
        if ($item->value('jdc:hasArchetype')) {
            return $this->getArchetypeForEditor($item->value('jdc:hasArchetype')->valueResource());
        }

        $rc = $item->displayResourceClassLabel() ;
        $style = $item->value('oa:styleClass')->__toString();
        $arc = $this->setArchetype($rc, $style, $idDiagram);

        //ajoute la relation à la géométrie
        $oItem = [];
        $valueObject = [];
        $valueObject['value_resource_id'] = $arc->id();
        $valueObject['property_id'] = $this->getProp('jdc:hasArchetype')->id();
        $valueObject['type'] = 'resource';
        $oItem['jdc:hasArchetype'][] = $valueObject;
        $rslt = $this->api->update('items', $item->id(), $oItem, [], ['continueOnError' => true,'isPartial' => 1, 'collectionAction' => 'append']);

        return $this->getArchetypeForEditor($arc);
    }

    public function setArchetype($rc, $style, $idDiagram)
    {
        //recherche si l'archétype existe pour ce diagramme
        $ref = md5($rc . $style . $idDiagram);
        $param = [];
        $param['property'][0]['property'] = $this->getProp('dcterms:isReferencedBy')->id();
        $param['property'][0]['type'] = 'eq';
        $param['property'][0]['text'] = $ref;
        $arcs = $this->api->search('items', $param)->getContent();
        if (count($arcs) > 0) {
            $arc = $arcs[0];
        } else {
            $title = uniqid($rc . ' ');
            //ajoute l'archétype
            $oItem = [];
            $oItem['o:resource_class'] = ['o:id' => $this->getRc('jdc:Archetype')->id()];
            $oItem['o:resource_template'] = ['o:id' => $this->getRt('Archétype')->id()];
            $valueObject = [];
            $valueObject['@value'] = $title;
            $valueObject['type'] = 'literal';
            $valueObject['property_id'] = $this->getProp('dcterms:title')->id();
            $oItem['dcterms:title'][] = $valueObject;
            $valueObject = [];
            $valueObject['@value'] = $ref;
            $valueObject['type'] = 'literal';
            $valueObject['property_id'] = $this->getProp('dcterms:isReferencedBy')->id();
            $oItem['dcterms:isReferencedBy'][] = $valueObject;
            $valueObject = [];
            $valueObject['@value'] = $style;
            $valueObject['type'] = 'literal';
            $valueObject['property_id'] = $this->getProp('dcterms:description')->id();
            $oItem['dcterms:description'][] = $valueObject;
            $valueObject = [];
            $valueObject['@value'] = $rc;
            $valueObject['type'] = 'literal';
            $valueObject['property_id'] = $this->getProp('dcterms:type')->id();
            $oItem['dcterms:type'][] = $valueObject;
            $arc = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
        }
        return $arc;
    }

    public function getArchetypeForEditor($arc)
    {
        $type = $arc->value('dcterms:type')->__toString();
        if ($type == 'Ligne') {
            $type = 'link';
        }
        if ($type == 'Envelope') {
            $type = 'node';
        }
        return [
            "id" => $arc->id(),
            "type" => $type,
            "name" => $arc->value('dcterms:title')->__toString(),
            "cssStyle" => $arc->value('dcterms:description')->__toString(),
        ];
    }

    public function getDiagrammeInfo($oItem)
    {
        $dC = $oItem->value('dcterms:created')->asHtml();
        $c = $oItem->value('dcterms:creator') ? $oItem->value('dcterms:creator')->asHtml() : '';
        /*
        $w = $oItem->value('ma:frameWidth') ? $oItem->value('ma:frameWidth')->asHtml() : 300;
        $h = $oItem->value('ma:frameHeight') ? $oItem->value('ma:frameHeight')->asHtml() : 300;
        $styles = $oItem->value('oa:styleClass') ? json_decode($oItem->value('oa:styleClass')->__toString()) : "";
        $result = ['label'=>$oItem->displayTitle()." (".$c." - ".$dC.")"
          ,'id'=>$oItem->id()
          ,'title'=>$oItem->displayTitle()
          ,'w'=>$w
          ,'h'=>$h
          ,'urlAdmin'=>$oItem->adminUrl('edit')
          ,'styles'=>$styles
        ];
        */
        //print_r($_SERVER);
        $url = explode('?', $this->url)[0];
        $result = ['name' => $oItem->displayTitle() . " (" . $c . " - " . $dC . ")"
          ,'id' => $oItem->id()
          ,'title' => $oItem->displayTitle()
          ,'urlData' => $url . '?json=1&type=diagramme&action=getDiagramme&id=' . $oItem->id()
          ,'urlArchetypes' => $url . '?json=1&type=diagramme&action=getArchetypes&id=' . $oItem->id()
          ,'type' => 'Diagram_argument_force',
        ];
        return $result;
    }

    public function getGeoInfo($oItem, $idDiagram)
    {
        $rc = $oItem->displayResourceClassLabel() ;
        //récupère l'archétype
        if (!$oItem->value('jdc:hasArchetype')) {
            $arc = $this->getArchetype($oItem, $idDiagram);
        } else {
            $arc = $this->getArchetypeForEditor($oItem->value('jdc:hasArchetype')->valueResource());
        }
        if (!isset($this->doublons[$arc['id']])) {
            $this->rs['archetypes'][] = $arc;
            $this->doublons[$arc['id']] = true;
        }

        switch ($rc) {
            case 'Ligne':
                $this->rs['links'][] = ['label' => $oItem->displayTitle()
                        ,'id' => $oItem->id()
                        ,'idDiagram' => $idDiagram
                        ,'idArchetype' => $arc['id']
                        ,'src' => $oItem->value('ma:hasSource') ? $oItem->value('ma:hasSource')->valueResource()->id() : false
                        ,'dst' => $oItem->value('ma:isSourceOf') ? $oItem->value('ma:isSourceOf')->valueResource()->id() : false
                        ,'urlAdmin' => $oItem->adminUrl('edit'),
                    ];
                break;
            case 'Envelope':
                $this->rs['nodes'][] = ['label' => $oItem->value('skos:semanticRelation')->valueResource()->displayTitle()
                        ,'id' => $oItem->id()
                        ,'idDiagram' => $idDiagram
                        ,'idArchetype' => $arc['id']
                        ,'idConcept' => $oItem->value('skos:semanticRelation')->valueResource()->id()
                        ,'x' => $oItem->value('geom:coordX')->__toString()
                        ,'y' => $oItem->value('geom:coordY')->__toString()
                        ,'type' => $oItem->value('dcterms:type')->__toString()
                        ,'urlAdmin' => $oItem->adminUrl('edit'),
                    ];
                break;
        }

        return $this->rs;
    }

    public function getProp($p)
    {
        if (!isset($this->props[$p])) {
            $this->props[$p] = $this->api->search('properties', ['term' => $p])->getContent()[0];
        }
        return $this->props[$p];
    }

    public function getRc($t)
    {
        if (!isset($this->rcs[$t])) {
            $this->rcs[$t] = $this->api->search('resource_classes', ['term' => $t])->getContent()[0];
        }
        return $this->rcs[$t];
    }
    public function getRt($l)
    {
        if (!isset($this->rts[$l])) {
            $this->rts[$l] = $this->api->read('resource_templates', ['label' => $l])->getContent();
        }
        return $this->rts[$l];
    }

    /**
     * Ajoute les items d'une requête
     *
     * @param array $data
     * @return oItem
     */
    protected function ajouteCarte($data)
    {
        //vérifie la présence de l'item pour ne pas écraser les données
        $param = [];
        $param['property'][0]['property'] = $this->properties['dcterms']['title']->id() . "";
        $param['property'][0]['type'] = 'eq';
        $param['property'][0]['text'] = $this->getArg('name');
        $param['resource_template_id'] = $this->resourceTemplate['Cartographie des expressions']->id() . "";

        $result = $this->api->search('items', $param)->getContent();
        //$this->logger->info("RECHERCHE ITEM = ".json_encode($result));
        //$this->logger->info("RECHERCHE COUNT = ".count($result));

        if (count($result)) {
            $oItem = $result[0]->getContent();
            throw new RuntimeException("La carte existe déjà : '" . $oItem->displayTitle() . "' (" . $oItem->id() . ").");
        } else {
            //creation de la carte générale
            $oItem = [];
            $oItem['o:item_set'] = [['o:id' => $this->itemSet->id()]];
            $oItem['o:resource_class'] = ['o:id' => $this->resourceClasses['plmk']['CarteExpression']->id()];
            $oItem['o:resource_templates'] = ['o:id' => $this->resourceTemplate['Cartographie des expressions']->id()];

            $d = $data['ivml:docinfo'];
            $d['width'] = $data['ivml:appdata']['map']['width'];
            $d['height'] = $data['ivml:appdata']['map']['height'];

            //récupération du style de la carte
            $d['style'] = json_encode($data['ivml:appdata']['map']['style-sheet-list']['style-sheet']);
            $oItem = $this->mapValues($d, $oItem);

            $response = $this->api->create('items', $oItem, [], ['continueOnError' => true]);
        }
        //$this->logger->info("UPDATE ITEM".$result[0]->id()." = ".json_encode($result[0]));
        $oItem = $response->getContent();
        //enregistre la progression du traitement
        $importItem = [
            'o:item' => ['o:id' => $oItem->id()],
            'o-module-cmap_import:import' => ['o:id' => $this->idImport],
            'o-module-cmap_import:action' => "Création carte",
        ];
        $this->api->create('cmap_import_items', $importItem, [], ['continueOnError' => true]);

        return $oItem;
    }

    /**
     * Ajoute les liens d'une carte
     *
     * @param array $data
     * @param oItem $oItemCarte
     * @param array $arrEntities
     * @return array
     */
    protected function ajouteLinks($data, $oItemCarte, $arrEntities)
    {
        $arrLinks = [];
        foreach ($data['ivml:links']['ivml:link'] as $k => $d) {
            if ($this->shouldStop()) {
                return;
            }
            //création du lien
            $oItem = [];
            $oItem['o:resource_class'] = ['o:id' => $this->resourceClasses['geom']['Line']->id()];
            $oItem['o:resource_templates'] = ['o:id' => $this->resourceTemplate['Relation sémantique']->id()];

            //création du titre
            $d['titre'] = $d['label'] ? $d['label'] . ' : ' . $d['id'] : $d['from'] . ' -> ' . $d['to'];

            //construction du style
            $d['style'] = '{';
            for ($i = 0; $i < count($d['ivml:appdata']['connection']); $i++) {
                $d['style'] .= $d['ivml:appdata']['connection-appearance'][0]['from-pos'] ? '"from-pos":"' . $d['ivml:appdata']['connection-appearance'][0]['from-pos'] . '",' : '';
                $d['style'] .= $d['ivml:appdata']['connection-appearance'][0]['to-pos'] ? '"to-pos":"' . $d['ivml:appdata']['connection-appearance'][0]['to-pos'] . '",' : '';
                $d['style'] .= $d['ivml:appdata']['connection-appearance'][0]['arrowhead'] ? '"arrowhead":"' . $d['ivml:appdata']['connection-appearance'][0]['arrowhead'] . '",' : '';
                $d['style'] .= $d['ivml:appdata']['concept-appearance'][0]['border-color'] ? '"border-color":"' . $d['ivml:appdata']['concept-appearance'][0]['border-color'] . '",' : "";
            }
            if ($i == 0) {
                $d['style'] .= $d['ivml:appdata']['connection-appearance']['from-pos'] ? '"from-pos":"' . $d['ivml:appdata']['connection-appearance']['from-pos'] . '",' : '';
                $d['style'] .= $d['ivml:appdata']['connection-appearance']['to-pos'] ? '"to-pos":"' . $d['ivml:appdata']['connection-appearance']['to-pos'] . '",' : '",';
                $d['style'] .= $d['ivml:appdata']['connection-appearance']['arrowhead'] ? '"arrowhead":"' . $d['ivml:appdata']['connection-appearance']['arrowhead'] . '",' : '';
                $d['style'] .= $d['ivml:appdata']['concept-appearance']['border-color'] ? '"border-color":"' . $d['ivml:appdata']['concept-appearance']['border-color'] . '",' : "";
            }
            $d['style'] .= '"color":"' . $d['color'] . '",';
            $d['style'] .= '"lineStyle":"' . $d['lineStyle'] . '"';
            $d['style'] .= '}';

            //ajoute les références au from et au to
            $d['from'] = $arrEntities[$d['from']];
            $d['to'] = $arrEntities[$d['to']];

            $oItem = $this->mapValues($d, $oItem);
            $response = $this->api->create('items', $oItem, [], ['continueOnError' => true]);
            $oItem = $response->getContent();

            //création le concept
            if ($d['label']) {
                $this->getTag($d['label'], $oItem);
            }

            //ajoute la relation à la carte
            $param = [];
            $valueObject = [];
            $valueObject['property_id'] = $this->properties["geom"]["geometry"]->id();
            $valueObject['value_resource_id'] = $oItem->id();
            $valueObject['type'] = 'resource';
            $param[$this->properties["geom"]["geometry"]->term()][] = $valueObject;
            $this->api->update('items', $oItemCarte->id(), $param, [], ['isPartial' => true, 'continueOnError' => true, 'collectionAction' => 'append']);

            //enregistre la progression du traitement
            $importItem = [
                'o:item' => ['o:id' => $oItem->id()],
                'o-module-cmap_import:import' => ['o:id' => $this->idImport],
                'o-module-cmap_import:action' => 'ajout link',
            ];
            $this->api->create('cmap_import_items', $importItem, [], ['continueOnError' => true]);

            $arrLinks[] = $oItem;
        }
    }
}
