<?php
namespace CartoAffect\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class DiagrammeViewHelper extends AbstractHelper
{
    protected $api;
    protected $props;
    protected $rcs;
    protected $rts;
    var $rs;
    var $view;
    var $doublons;

    public function __construct($api)
    {
      $this->api = $api;
    }

    /**
     * Gestion de l'éditeur de cartographie
     *
     * @param array     $params paramètre de l'action
     * @return array
     */
    public function __invoke($params=[])
    {
        if($params==[])return[];
        $this->props = $params['props'];
        switch ($params['query']['action']) {
            case 'getArchetype':
                $oItem = $this->api->read('items',$params['query']['id'])->getContent();
                $this->rs=$this->getArchetype($oItem);
                break;            
            case 'getArchetypes':
                $this->getArchetypes($params);
                break;            
                case 'getDiagrammes':
                $this->getDiagrammes();
                break;            
            case 'getDiagramme':
                $this->getDiagramme($params);
                break;            
            case 'saveArchetype':
                $this->saveArchetype($params);
                break;            
            }

        return $this->rs;

    }

    function saveArchetype($params){

        $oItem=[];
        $valueObject = [];
        $valueObject['@value']=$params['post']['cssStyle'];        
        $valueObject['property_id']=$this->getProp('dcterms:description')->id();
        $valueObject['type']='literal';    
        $oItem['dcterms:description'][]=$valueObject;
        return $this->api->update('items', $params['post']['id'], $oItem, [], ['continueOnError' => true,'isPartial'=>1, 'collectionAction' => 'replace']);
    }

    function getDiagrammes(){
        //récupère toute les carte d'expression        
        $query = [
            'resource_class_id'=>$this->props['plmk:CarteExpression']->id(),
        ];
        $items = $this->api->search('items',$query,['limit'=>0])->getContent();
        //construction des résultats
        foreach ($items as $i) {
            $this->rs[] = $this->getDiagrammeInfo($i);
        }
    }

    function getDiagramme($p){
        $oItem = $this->api->read('items',$p['query']['id'])->getContent();
        $this->rs = $this->getDiagrammeInfo($oItem);
        $geos = $oItem->value('geom:geometry', ['all' => true]);
        foreach ($geos as $geo) {                
            $this->getGeoInfo($geo->valueResource());
        }
    }

    function getArchetypes($p){
        $oItem = $this->api->read('items',$p['query']['id'])->getContent();
        $geos = $oItem->value('geom:geometry', ['all' => true]);
        foreach ($geos as $geo) {
            $arc=$this->getArchetype($geo->valueResource());                 
            if(!isset($this->doublons[$arc['id']])){
                $this->rs[] = $arc;
                $this->doublons[$arc['id']] = true;
            }
        }
    }

    /**
     * création d'un archétype en relation avec un item
     *
     * @param object     $oItem
     * @return array
     */
    function getArchetype($item){


        if($item->value('jdc:hasArchetype'))
            $this->getArchetypeForEditor($item->value('jdc:hasArchetype')->valueResource());

        $rc = $item->displayResourceClassLabel() ;
        $style = $item->value('oa:styleClass')->__toString();

        //recherche si l'archétype existe
        $ref = md5($rc.$style);
        $param = array();
        $param['property'][0]['property']= $this->getProp('dcterms:isReferencedBy')->id();
        $param['property'][0]['type']='eq';
        $param['property'][0]['text']=$ref;                 
        $arcs = $this->api->search('items',$param)->getContent();
        if(count($arcs) > 0){
            $arc = $arcs[0];
        }else{
            $title=uniqid($rc.' ');
            //ajoute l'archétype
            $oItem=[];
            $oItem['o:resource_class'] = ['o:id' => $this->getRc('jdc:Archetype')->id()];
            $oItem['o:resource_template'] = ['o:id' => $this->getRt('Archétype')->id()];
            $valueObject = [];
            $valueObject['@value'] = $title;
            $valueObject['type'] = 'literal';
            $valueObject['property_id']=$this->getProp('dcterms:title')->id();
            $oItem['dcterms:title'][] = $valueObject;    
            $valueObject = [];
            $valueObject['@value'] = $ref;
            $valueObject['type'] = 'literal';
            $valueObject['property_id']=$this->getProp('dcterms:isReferencedBy')->id();
            $oItem['dcterms:isReferencedBy'][] = $valueObject;    
            $valueObject = [];
            $valueObject['@value'] = $style;
            $valueObject['type'] = 'literal';
            $valueObject['property_id']=$this->getProp('dcterms:description')->id();
            $oItem['dcterms:description'][] = $valueObject;    
            $valueObject = [];
            $valueObject['@value'] = $rc;
            $valueObject['type'] = 'literal';
            $valueObject['property_id']=$this->getProp('dcterms:type')->id();
            $oItem['dcterms:type'][] = $valueObject;
            $arc = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
            //ajoute la relation à la géométrie
            $oItem=[];
            $valueObject = [];
            $valueObject['value_resource_id']=$arc->id();        
            $valueObject['property_id']=$this->getProp('jdc:hasArchetype')->id();
            $valueObject['type']='resource';    
            $rslt = $this->api->update('items', $item->id(), $oItem, [], ['continueOnError' => true,'isPartial'=>1, 'collectionAction' => 'append']);
        } 
        return $this->getArchetypeForEditor($arc);    
    }

    function getArchetypeForEditor($arc){
        $type = $arc->value('dcterms:type')->__toString();
        if($type=='Ligne')$type='link';
        if($type=='Envelope')$type='node';
        return [
			"id"=>$arc->id(),
			"type"=> $type,
			"name"=>$arc->value('dcterms:title')->__toString(),
			"cssStyle"=>$arc->value('dcterms:description')->__toString()
        ];    
    }

    function getDiagrammeInfo($oItem){
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
        $result = ['name'=>$oItem->displayTitle()." (".$c." - ".$dC.")"
          ,'id'=>$oItem->id()
          ,'title'=>$oItem->displayTitle()
          ,'urlData'=>$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['REDIRECT_URL'].'?type=diagramme&action=getDiagramme&id='.$oItem->id()
          ,'urlArchetypes'=>$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['REDIRECT_URL'].'?type=diagramme&action=getArchetype&id='.$oItem->id()
          ,'type'=>'Diagram_argument'
        ];
        return $result;
    }

    function getGeoInfo($oItem){
        $rc = $oItem->displayResourceClassLabel() ;
        //récupère l'archétype
        $style = $oItem->value('oa:styleClass')->__toString();
        if(!$oItem->value('jdc:hasArchetype'))
            $arc = $this->getArchetype($oItem);
        else
            $arc = $this->getArchetypeForEditor($oItem->value('jdc:hasArchetype')->valueResource());
        if(!isset($this->doublons[$arc['id']])){
            $this->rs['archetypes'][]=$arc;
            $this->doublons[$arc['id']] = true;
        }

        switch ($rc) {
            case 'Ligne':
                $this->rs['links'][] = ['label'=>$oItem->displayTitle()
                        ,'id'=>$oItem->id()
                        ,'idArchetype'=>$arc['id']
                        ,'src'=>$oItem->value('ma:hasSource') ? $oItem->value('ma:hasSource')->valueResource()->id() : false
                        ,'dst'=>$oItem->value('ma:isSourceOf') ? $oItem->value('ma:isSourceOf')->valueResource()->id() : false
                        ,'urlAdmin'=>$oItem->adminUrl('edit')
                    ];      
                break;
            case 'Envelope':
                $this->rs['nodes'][] = ['label'=>$oItem->value('skos:semanticRelation')->valueResource()->displayTitle()
                        ,'id'=>$oItem->id()
                        ,'idArchetype'=>$arc['id']
                        ,'idConcept'=>$oItem->value('skos:semanticRelation')->valueResource()->id()
                        ,'x'=>$oItem->value('geom:coordX')->__toString()
                        ,'y'=>$oItem->value('geom:coordY')->__toString()
                        ,'type'=>$oItem->value('dcterms:type')->__toString()
                        ,'urlAdmin'=>$oItem->adminUrl('edit')
                    ];      
                break;
        }

        return $this->rs;
    }


    function getProp($p){
        if(!isset($this->props[$p]))
          $this->props[$p]=$this->api->search('properties', ['term' => $p])->getContent()[0];
        return $this->props[$p];
      }
  
    function getRc($t){
        if(!isset($this->rcs[$t]))
            $this->rcs[$t] = $this->api->search('resource_classes', ['term' => $t])->getContent()[0];
        return $this->rcs[$t];
    }
    function getRt($l){
        if(!isset($this->rts[$l]))
            $this->rts[$l] = $this->api->read('resource_templates', ['label' => $l])->getContent();
        return $this->rts[$l];
    }
  
}
