<?php
namespace CartoAffect\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use DateTime;

class ScenarioViewHelper extends AbstractHelper
{
    protected $api;
    protected $acl;
    protected $doublons;
    protected $props;
    protected $rcs;
    protected $rts;

    public function __construct($api,$acl)
    {
      $this->api = $api;
      $this->acl = $acl;
    }

    /**
     * gestion des scenarios
     * 
     * @param Omeka\View\Helper\Params     $params
     *
     * @return json
     */
    public function __invoke($params)
    {
        $query = $params->fromQuery();
        $post = $params->fromPost();
        switch ($query['type']) {
            case 'deleteScenario':
                $result = $this->deleteScenario($query['item_id']);
                break;            
            case 'genereScenario':
                $result = $this->createScenario($this->genScenario($query,$post));
                break;            
            case 'getListeFromItem':
                $result = $this->getScenarios($query['item_id']);
                break;            
            case 'getIndexFromScenario':
                $result = $this->getIndex($query['item_id']);
                break;
            case 'saveIndex':
                $i = $this->saveIndex($post,$post['rt'] ? $post['rt'] : 'Indexation vidéo');
                $result[] = $this->createTimelinerEntry($post['oa:hasSource'], $post['idGroup'], $post['category'], $i);
                $result[] = [
                    "time"=>$post["oa:end"],
                    "value"=> 1,
                ];
                break;
            case 'createRelations':                                
                $result = $this->createRelations($post);
                break;
            default:
                $result = [];
                break;
        }
        return $result;
    }


    /**
     * Suppression d'un scenario et de toutes les tracks
     * 
     * @param int $id
     *
     * @return string
     */
    function deleteScenario($id){
        $result = [];
        $oItem = $this->api->read('items',$id)->getContent();
        $rs = $oItem->userIsAllowed('delete');
        if($rs){
            //récupère les identifiants des tracks
            $ids = [];
            $ids[]=$id;
            $tracks = $oItem->value('oa:hasBody', ['all' => true]);
            foreach ($tracks as $t) {
                $ids[]=$t->valueResource()->id();
            }
            $response = $this->api->batchDelete('items',$ids, [], ['continueOnError' => true]);
            $result= ['message'=>"Scenario supprimé."];           
        }else{
            $result=['error'=>"droits insuffisants",'message'=>"Vous n'avez pas le droit de supprimer ce scenario."];    
        }
        return $result;
    }
        
    /**
     * Création des relations à partir d'une valeur textuelle transformé en item
     * 
     * @param array $params
     *
     * @return o:item
     */
    function createRelations($params){
        //récupère l'item
        $item =  $this->api->read('items', $params['idItem'])->getContent();
        //récupère la définition du resource_template
        $rt =  $item->resourceTemplate();

        $dataItem = json_decode(json_encode($item), true);
        $update=false;
        //boucle sur les propriétés à prendre en compte
        foreach ($params['props'] as $p) {
            //vérifie les valeurs de la propriété pour l'item
            $vals = $item->value($p,['all'=>true]);
            foreach ($vals as $i=>$v) {
                //si la valeur n'est pas une ressource on la crée
                if($v->type()=='literal'){
                    $data = [];
                    $term = 'relation';
                    $pVal =  $this->getProp($p);
                    $comment = $rt->resourceTemplateProperty($pVal->id())->alternateComment();
                    if(substr($comment,0,6)=='class='){
                        $term = substr($comment,6); 
                        $rc =  $this->getRc($term);
                        $data['o:resource_class'] = ['o:id' => $rc->id()];
                    }
                    //vérifie l'existence de l'item
                    $pRef =  $this->getProp('dcterms:isReferencedBy');
                    $query['property'][0]['property']= $pRef->id();
                    $query['property'][0]['type']='eq';
                    $query['property'][0]['text']=md5($term.$v->__toString()); 
                    $result = $this->api->search('items',$query)->getContent();
                    if(!count($result)){
                        $pTitle = $this->getProp('dcterms:title');
                        $data['dcterms:title'][]=[
                            '@value' => $v->__toString(),'type' => 'literal', 'property_id'=>$pTitle->id()
                        ];
                        $itemRef = $this->api->create('items',$data)->getContent();                                      
                    }else $itemRef = $result[0];
                    //on modifie la valeur de l'item
                   $dataItem[$p][$i]=[
                       'value_resource_id'=>$itemRef->id(),'property_id'=>$pVal->id(),'type'=>'resource'              
                   ];
                   $update=true;
                }
            }
        }
        if($update){
            $this->api->update('items', $item->id(), $dataItem, [], ['continueOnError' => true,'isPartial'=>1, 'collectionAction' => 'replace']);    
        }
        return $item;
    }

    /**
     * Enregistre une indexation dans la base
     * 
     * @param   array   $params
     * @param   string  $rtLabel
     *
     * @return o:item
     */
    function saveIndex($params, $rtLabel){
        //enregistre une indexation dans la base
        $rt =  $this->getRt($rtLabel);
        $oItem = [];
        $oItem['o:resource_class'] = ['o:id' => $rt->resourceClass()->id()];
        $oItem['o:resource_template'] = ['o:id' => $rt->id()];
        $rtp = $rt->resourceTemplateProperties();
        foreach ($rtp as $p) {
          $oP = $p->property();
          switch ($oP->term()) {
            case "oa:hasSource":
            case "oa:hasTarget":
            case "schema:category":
            case "dcterms:creator":
            case "genstory:hasActant":
            case "genstory:hasAffect":
            case "genstory:hasEvenement":
            case "genstory:hasHistoire":
            case "genstory:hasLieu":
            case "genstory:hasMonde":
            case "genstory:hasObjet":
                if(isset($params[$oP->term()])){
                    $oItem = $this->setValeur([['id'=>$params[$oP->term()]]],$oP,$oItem); 
                }
                break;                    
            case "dcterms:created":
            case "dcterms:modified":
                $oItem = $this->setValeur(date(DATE_ATOM),$oP,$oItem); 
                break;                                                            
            default:
                if(isset($params[$oP->term()])){
                    $oItem = $this->setValeur($params[$oP->term()],$oP,$oItem); 
                }
                break;
            }
        }
        //vérifie la mise à jour
        if(isset($params['idIndex'])){
            //$oItem
            $result = $this->api->read('items', $params['idIndex'])->getContent();
            //conserve la date de création
            $oItem['dcterms:created'][0]['@value']=$result->value('dcterms:created')->__toString();
            $this->api->update('items', $result->id(), $oItem, [], ['isPartial'=>1,'continueOnError' => true, 'collectionAction' => 'replace']);
            $result = $this->api->read('items',$result->id())->getContent();
        }else{
          $result = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
        }              
        return $result;        
    }

    /**
     * Renvoie les indexations pour un scenario
     * 
     * @param int       $idItem
     *
     * @return json
     */
    function getIndex($idItem){
        //récupère tous les indexations d'un scénario
        $item = $this->api->read('items',$idItem)->getContent();
        $results = ['oa:hasBody'=>[],'oa:hasSource'=>[],'oa:hasTarget'=>[]];
        foreach ($results as $k => $v) {
            $vals = $item->value($k,['all'=>true]);
            foreach ($vals as $v) {
                $results[$k][]=$v->valueResource();
            }
        }
        return $results;
    }

    /**
     * Renvoie les scénarios pour une item
     * 
     * @param int       $idItem
     *
     * @return json
     */
    function getScenarios($idItem){
        //mis à jour du scenario global de l'item = toutes les annnotations
        $this->createScenario($this->genScenario(['idItem'=>$idItem,'type'=>'global']));
        //récupère tous les scénario pour l'item
        $rt =  $this->api->search('resource_templates', ['label' => 'Scénario Timeliner',])->getContent()[0];
        $query["resource_template_id"]=$rt->id();
        $p =  $this->api->search('properties', ['term'=>'oa:hasSource'])->getContent()[0];
        $query['property'][0]['property']= $p->id();
        $query['property'][0]['type']='res';
        $query['property'][0]['text']=$idItem; 
        return $this->api->search('items',$query)->getContent();
    }
    /**
     * Génération d'un scénario à partir de toutes les indexations d'un item
     * 
     * @param array     $query
     * @param array     $post
     *
     * @return array
     */
    function genScenario($query, $post=false){
        $gen = $query['gen'];
        $rt = 'Scenario';
        switch ($gen) {
            case 'global':
                $idItem = $query['item_id'];
                $rt =  $this->api->search('resource_templates', ['label' => 'Indexation vidéo',])->getContent()[0];
                $item = $this->api->read('items',$idItem)->getContent();
                $query["resource_template_id"]=$rt->id();//indexation vidéo
                $p =  $this->api->search('properties', ['term'=>'oa:hasSource'])->getContent()[0];
                $query['property'][0]['property']= $p->id();
                $query['property'][0]['type']='res';
                $query['property'][0]['text']=$idItem; 
                $items = $this->api->search('items',$query)->getContent();
                $titre = $item->value('bibo:shortTitle')->__toString();
                //groupe les items par category et creator pour visualiser les point de vue
                $gItems = $this->groupByCategoryCreator($items);
                $rt = 'Scénario Timeliner';
                break;            
            case 'fromStories':
                //récupère les propriétés nécessaires des histoires
                $items = [];
                $titre = " - ";
                foreach ($post['genstory:hasHistoire'] as $hId) {
                    $item = $this->api->read('items',$hId)->getContent();
                    $titre .= $item->displayTitle().' - ';
                    foreach ($post['props'] as $p) {
                        $vals = $item->value($p,['all'=>true]);
                        $op = $this->getProp($p);
                        foreach ($vals as $i=>$v) {
                            //si la valeur est une ressource on crée l'index
                            if($v->type()=='resource'){
                                $itemVal = $v->valueResource();
                                $params = [
                                    'dcterms:title'=> $op->label().' - '.$itemVal->value('dcterms:title')->__toString().' : 1',
                                    'dcterms:description'=> $itemVal->value('dcterms:description') ? $itemVal->value('dcterms:description')->__toString() : '',
                                    'oa:start'=>0,
                                    'oa:end'=>10,                                    
                                    'oa:hasSource'=>$item->id(),
                                    'oa:hasTarget'=>$itemVal->id(),
                                    'dcterms:creator'=>$post['dcterms:creator'],
                                ];
                                $params[$p]=$itemVal->id();                            
                                $items[] = $this->saveIndex($params,'Scenario track');
                            }
                        }            
                    }
                }
                //groupe les items par titre
                $gItems = $this->groupBySourceClassTarget($items);
                $titre = $post['dcterms:title'] ? $post['dcterms:title'] : $titre;
                break;            
            default:
                $gItems = []; 
                break;
        }
        
        $scenario = [
            "version"=>"1.2.0",
            "modified"=>date(DATE_ATOM),
            "title"=>"Scenario ".$gen." : ".$titre,
            "layers"=>[],
            "ui"=> [
                "currentTime"=>0,
                "totalTime"=>0,
                "scrollTime"=>0,
                "timeScale"=>0.05
                ]    
            ];
        $bodies=[];$targets = [];$sources=[];$creators=[];$categories=[];$doublons=['sources'=>[],'creators'=>[],'targets'=>[]];
        $totalTime = 0;$debTime=100000000000000000;
        $idLayer=0;
        foreach ($gItems as $k => $groupe) {
            $categories[]=$groupe['id'];
            $layer = [
                "id"=>$groupe['id'],
                "idLayer"=>$idLayer,
                "name"=>$k,
                "_color"=> $groupe['color'],
                "_value"=> 0,
                "desc"=> $groupe['desc'],
                "values"=> []
            ];
            //ajoute les entrées enregistrées
            foreach ($groupe['items'] as $i) {
                $e = $this->createTimelinerEntry($item->id(), $groupe['id'], $k, $i);
                if($debTime>$e["time"])$debTime=$e["time"];            
                $layer['values'][]=$e;
                //création des facettes
                if(!isset($doublons['sources'][$e["idSource"]]))$sources[]=$e["idSource"];
                if(!isset($doublons['creators'][$e["idCreator"]]))$creators[]=$e["idCreator"];
                if(!isset($doublons['targets'][$e["idTarget"]]))$targets[]=$e["idTarget"];
                $doublons['sources'][$e["idSource"]]=1;
                $doublons['creators'][$e["idCreator"]]=1;
                $doublons['targets'][$e["idTarget"]]=1;
                $bodies[]=$e;
            }
            $scenario['layers'][]=$layer;
            $idLayer++;
        }
        //IMPORTANT triage des entrées pour bien afficher le détail des tracks
        $nb = count($scenario['layers']);
        for ($i=0; $i < $nb; $i++) { 
            $l = $scenario['layers'][$i];
            $trivals = $l['values'];
            usort($trivals,function ($a, $b) {
                if ($a["time"] == $b["time"]) {
                    return 0;
                }
                return ($a["time"] < $b["time"]) ? -1 : 1;
            });
            //ajoute les layers de fin après le tri
            $vals = [];
            foreach($trivals as $v){
                $vals[]=$v;
                $vals[]= [
                    "time"=>$v["timeEnd"],
                    "value"=> 1,
                ];
                if($totalTime<$v["timeEnd"])$totalTime=$v["timeEnd"];
            };
            //met à jour le scénario
            $scenario['layers'][$i]['values']=$vals;
        }

        //mise à jour des temporalités globales        
        $scenario['ui']['currentTime']=$debTime;
        $scenario['ui']['totalTime']=$totalTime;
        $scenario['ui']['scrollTime']=$debTime;
        return ["rt"=>$rt,"type"=>$gen,"scenario"=>$scenario,"bodies"=>$bodies,"targets"=>$targets,"sources"=>$sources,"creators"=>$creators,"categories"=>$categories];
    }

    /**
     * Création d'une entrée timeliner 
     * 
     * @param int       $idSource
     * @param int       $idGroup
     * @param string    $category
     * @param o:Item    $i
     * 
     * @return json
     */
    function createTimelinerEntry($idSource, $idGroup, $category, $i){
        if($i->value('oa:start')){
            $s = explode(':',$i->value('oa:start')->__toString());
            $ts = count($s) > 1 ? $s[0]*3600+$s[1]*60+$s[2] : trim($s[0])+0;//pour que la chaine soit un nombre   
        }else $ts = 0;
        if($i->value('oa:end')){
            $e = explode(':',$i->value('oa:end')->__toString());
            $te = count($e) > 1 ? $e[0]*3600+$e[1]*60+$e[2] : trim($e[0])+0;//pour que la chaine soit un nombre 
        }else $te=5;
        if($te==$ts)$te+=5;//ajoute des secondes pour voir la track

        if(!$idGroup){
            $category = $i->value('schema:category')->valueResource()->displayTitle().' : '.$i->value('dcterms:creator')->valueResource()->displayTitle();
            $idGroup = $i->value('schema:category')->valueResource()->id().'_'.$i->value('dcterms:creator')->valueResource()->id();
        }
        $resourceClass = $i->resourceClass();
        $prop = $resourceClass->label();

        $target = $i->value('oa:hasTarget')->valueResource();
        $l = [
            "time"=>$ts,
            "timeEnd"=>$te,
            "value"=> 0,
            "start"=> $i->value('oa:start') ? $i->value('oa:start')->__toString() : $ts,
            "end"=> $i->value('oa:end') ? $i->value('oa:end')->__toString() : $te,
            "_color"=> $i->value('schema:color') ? $i->value('schema:color')->__toString() : $this->aleaColor(),
            "idIndex"=> $i->id(),
            "idObj"=> $i->id(),
            "idTarget"=> $target->id(),
            "nameTarget"=> $target->displayTitle().' - '.$target->id(),                    
            "idSource"=> $idSource,
            "idGroup"=>$idGroup,
            "category"=>$category,
            "idCreator"=>$i->value('dcterms:creator')->valueResource()->id(),
            "creator"=>$i->value('dcterms:creator')->valueResource()->displayTitle(),
            "prop"=> $prop,
            "text"=> $i->displayTitle(),
            "tween"=> "linear"
        ];
        //ajoute la catégorie
        if($i->value('schema:category')){
            $l["idCat"]=$i->value('schema:category')->valueResource()->id();
        }
        //ajoute les infos du media
        if($target->resourceName()=='media'){
            $l["typeTarget"]= $target->mediaType();
            $l["urlTarget"]= $target->originalUrl();
        }else{                        
            $l["typeTarget"]= $target->resourceName();
            $l["urlTarget"]= $target->url();
            $l["mediaTarget"]= $target->media();
        }  
        return $l;
    }

    /**
     * Création d'un scénario 
     * 
     * @param array     $data
     *
     * @return json
     */
    function createScenario($data){
        $rt =  $this->api->search('resource_templates', ['label' => $data['rt'],])->getContent()[0];
        $oItem = [];
        $oItem['o:resource_class'] = ['o:id' => $rt->resourceClass()->id()];
        $oItem['o:resource_template'] = ['o:id' => $rt->id()];
        foreach ($rt->resourceTemplateProperties() as $p) {
          $oP = $p->property();
          switch ($oP->term()) {
              case "dcterms:title":
                $oItem = $this->setValeur($data['scenario']['title'],$oP,$oItem); 
                break;
            case "dcterms:isReferencedBy":
                $pIRB = $oP;
                $IRB = $data['type']."-".implode('_',$data['sources']);
                $oItem = $this->setValeur($IRB,$oP,$oItem); 
                break;
            case "oa:hasSource":
                foreach ($data['sources'] as $s) {
                    $oItem = $this->setValeur([['id'=>$s]],$oP,$oItem); 
                }
                break;                    
            case "schema:category":
                foreach ($data['categories'] as $s) {
                    $oItem = $this->setValeur([['id'=>$s]],$oP,$oItem); 
                }
                break;                    
            case "oa:hasTarget":
                foreach ($data['targets'] as $s) {
                    $oItem = $this->setValeur([['id'=>$s]],$oP,$oItem); 
                }
                break;                    
            case "oa:hasBody":
                foreach ($data['bodies'] as $s) {
                    $oItem = $this->setValeur([['id'=>$s['idObj']]],$oP,$oItem); 
                }
                break;                                                
            case "dcterms:creator":
                foreach ($data['creators'] as $s) {
                    $oItem = $this->setValeur([['id'=>$s]],$oP,$oItem); 
                }
                break;        
            case "dcterms:created":
            case "dcterms:modified":
                $oItem = $this->setValeur(date(DATE_ATOM),$oP,$oItem); 
                break;                                                            
            case "schema:object":
                $oItem = $this->setValeur(json_encode($data['scenario']),$oP,$oItem); 
                break;                                                                                
            }
        }
        //vérifie la mise à jour
        $param = array();
        $param['property'][0]['property']= $pIRB->id()."";
        $param['property'][0]['type']='eq';
        $param['property'][0]['text']=$IRB; 
        $result = $this->api->search('items',$param)->getContent();
        if(count($result)){
            //$oItem
            $result = $result[0];
            //conserve la date de création
            $oItem['dcterms:created'][0]['@value']=$result->value('dcterms:created')->__toString();
            $this->api->update('items', $result->id(), $oItem, [], ['isPartial'=>1,'continueOnError' => true, 'collectionAction' => 'replace']);
            $result = $this->api->read('items',$result->id())->getContent();
        }else{
          $result = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
        }              
        return $result;
    }

     /** Construction de la valeur
     *
     * @param   array   $val
     * @param   object  $oP
     * @param   array   $oItem
     * @return  array
     */
    protected function setValeur($val, $oP, $oItem)
    {
      if(is_string($val))$val=[$val];
      foreach ($val as $v) {
        $valueObject = [];
        if(!is_string($v) && $v['id']){
          $valueObject['value_resource_id']=$v['id'];        
          $valueObject['property_id']=$oP->id();
          $valueObject['type']='resource';    
        }else{
          $valueObject['@value'] = $v;
          $valueObject['type'] = 'literal';
          $valueObject['property_id']=$oP->id();
        } 
        $oItem[$oP->term()][]=$valueObject;
      }
      return $oItem;  
    }

    /**
     * Function that groups an array of associative arrays by some key.
     * 
     * @param {Array} $data Array that stores multiple item.
     */
    function groupByTitre($data) {
        $result = array();
        foreach($data as $item) {
            //construction de la clef
            $titre = $item->value('dcterms:title');

            $color = "";
            $desc = "";
            $val = $titre;
            $id = $item->id();
            $color = $this->aleaColor();
            $desc = $item->value('dcterms:description');
            if(!array_key_exists($val, $result))$result[$val]=['items'=>[],'id'=> $id,'color'=> $color,'desc'=> $desc];
            $result[$val]['items'][] = $item;                    
        }
        ksort($result);
        return $result;
    }    

    /**
     * Function that groups an array of associative arrays by some key.
     * 
     * @param {Array} $data Array that stores multiple item.
     */
    function groupByClassTitre($data) {
        $result = array();
        foreach($data as $item) {
            //construction de la clef
            $class = 'resource';
            $resourceClass = $item->resourceClass();
            if ($resourceClass) $class = $resourceClass->label();
            $titre = $item->value('dcterms:title');

            $color = "";
            $desc = "";
            $val = $class.' : '.$titre;
            $id = $class.'_'.$item->id();
            $color = $this->aleaColor();
            $desc = $item->value('dcterms:description');
            if(!array_key_exists($val, $result))$result[$val]=['items'=>[],'id'=> $id,'color'=> $color,'desc'=> $desc];
            $result[$val]['items'][] = $item;                    
        }
        ksort($result);
        return $result;
    }    

    /**
     * Function that groups an array of associative arrays by some key.
     * 
     * @param {Array} $data Array that stores multiple item.
     */
    function groupByCategoryCreator($data) {
        $result = array();
        foreach($data as $item) {
            //construction de la clef
            $cate = $item->value('schema:category')->valueResource();
            $crea = $item->value('dcterms:creator')->valueResource();

            $color = "";
            $desc = "";
            $val = $cate->displayTitle().' : '.$crea->displayTitle();
            $id = $cate->id().'_'.$crea->id();
            $color = $cate->value('schema:color') ? $cate->value('schema:color')->__toString() : $this->aleaColor();
            $desc = $cate->value('dcterms:description') ? $cate->value('dcterms:description')->asHtml() : '';
            if(!array_key_exists($val, $result))$result[$val]=['items'=>[],'id'=> $id,'color'=> $color,'desc'=> $desc];
            $result[$val]['items'][] = $item;                    
        }
        ksort($result);
        return $result;
    }

    /**
     * Function that groups an array of associative arrays by some key.
     * 
     * @param {Array} $data Array that stores multiple item.
     */
    function groupBySourceClassTarget($data) {
        $result = array();
        foreach($data as $item) {
            //construction de la clef
            $source = $item->value('oa:hasSource')->valueResource();
            $target = $item->value('oa:hasTarget')->valueResource();
            $rc = $target->resourceClass();

            $color = "";
            $desc = "";
            $val = $source->id().' : '.$rc->label().' : '.$target->displayTitle();
            $id = $source->id().' : '.$rc->id().' : '.$target->id();
            $color = $this->aleaColor();
            $desc = '';
            if(!array_key_exists($val, $result))$result[$val]=['items'=>[],'id'=> $id,'color'=> $color,'desc'=> $desc];
            $result[$val]['items'][] = $item;                    
        }
        ksort($result);
        return $result;
    }    

    //fonctions utilitaires géénriques
    function aleaColor($alpha="0.5"){
        //return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
        return 'rgba('.random_int(0,255).','.random_int(0,255).','.random_int(0,255).','.$alpha.')';
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
