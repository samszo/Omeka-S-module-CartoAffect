<?php
namespace CartoAffect\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Exception\RuntimeException;
use DateTime;

class ScenarioViewHelper extends AbstractHelper
{
    protected $api;
    protected $acl;
    protected $config;
    protected $doublons;
    protected $props;
    protected $rcs;
    protected $rts;
    protected $propsValueRessource=['oa:hasSource', 'oa:hasTarget', 'oa:hasBody', 'dcterms:creator','oa:hasScope'
        ,'genstory:hasActant','genstory:hasAffect','genstory:hasEvenement','genstory:hasLieu','genstory:hasObjet'
        ,'genstory:hasFonction','genstory:hasParam','schema:category','jdc:hasPhysique','jdc:hasActant','jdc:hasConcept'];
    protected $temp;
    protected $tempPath;
    protected $tempUrl;

    public function __construct($arrS)
    {
      error_reporting(E_ERROR | E_WARNING | E_PARSE);

      $this->api = $arrS['api'];
      $this->acl = $arrS['acl'];
      $this->config = $arrS['config'];
        $this->tempPath = $arrS['basePath'].'/tmp';
        $this->tempUrl = isset($_SERVER['HTTPS']) ? 'https' :'http';
        $this->tempUrl .='://' . $_SERVER['HTTP_HOST'] . $_SERVER['BASE'].'/files/tmp';
        //$this->tempUrl ='https://edisem.arcanes.ca/omk/files/tmp';
        //$this->tempUrl ='https://genstory.jardindesconnaissances.fr/files/tmp';
        //$this->tempUrl ='http://192.168.30.232/genstory/files/tmp';
        //$this->tempUrl ='http://192.168.30.208/omk/files/tmp';

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
        if(is_array($params))$query = $params;
        else{
            $query = $params->fromQuery();
            $post = $params->fromPost();    
        }
        switch ($query['type']) {
            case 'deleteScenario':
                $result = $this->deleteScenario($query['item_id']);
                break;            
            case 'genereScenario':
                $result = $this->genereScenario($query,$post);
                break;            
            case 'getListeFromItem':
                $result = $this->getScenarios($query['item_id']);
                break;            
            case 'getIndexFromScenario':
                $result = $this->getIndex($query['item_id']);
                break;
            case 'deleteTrack':
                $result = $this->deleteTrack($post);
                break;
            case 'deleteLayer':
                $result = $this->deleteLayer($post);
                break;
            case 'saveTrack':
            case 'saveIndex':
            case 'createTrack':
                $result = $this->createTrack($post);
                break;
            case 'addCategory':                
                $result = $this->addCategory($post);
                break;
            case 'addLayers':                
                $result = $this->addLayers($query['idScenario'],$post);
                break;
            case 'createRelations':                                
                $result = $this->createRelations($post);
                break;
            case 'scenarioTxtToObj':
                $this->scenarioTxtToObj($post['idScenario']);
                $result = [];
                break;
            case 'getRtSuggestion':
                $result = $this->getRtSuggestion($query['label'],$query['urlApi']);
                break;
            case 'getTimelinerTrack':
                $result = $this->getTimelinerTrack($query,null);
                break;
            case 'showChoix':
                $result = $this->showChoix($post);
                break;
            case 'getWebVTT':
                $result = $this->getWebVTT($query['numPos'],$query['idSrc']);
                break;
            case 'setAleaFrag':
                $result = $this->setAleaFrag($query);
                break;
            case 'getAleaFrag':
                $result = $this->getAleaFrag($query);
            break;
                default:
                $result = [];
                break;
        }
        return $result;
    }

    /**
     * création d'un fragment aléatoire
     * 
     * @param   array   $query
     *
     * @return array
     */
    function setAleaFrag($query){
        //récupère un média 
        $query = array();
        $query['media_type']= "video/mp4";
        $medias = $this->api->search('media', $query)->getContent();
        $media = $medias[array_rand($medias)];
        $itemMedia = $media->item();
        $itemFrag = ['ma:isFragmentOf'=>$itemMedia->id(),'dcterms:title'=>'fragment aléatoire de : '.$itemMedia->displayTitle()];
        return ['media'=>$media,'item'=>$this->saveTrack($itemFrag,'Fragment aléatoire')];
    }

    /**
     * récupère un fragment aléatoire
     * 
     * @param   array   $query
     *
     * @return array
     */
    function getAleaFrag($query){
        //récupère un média 
        $query = array();
        $query['resource_class_id']= $this->getRc('oa:FragmentSelector')->id();
        $query['sort_by']="random";
        $query['limit']="1";
        $query['offset']="1";
        $query['per_page']="1";
        $item = $this->api->search('items', $query)->getContent()[0];
        $media = $item->media()[0];
        //récupère le magic
        $query = array();
        $query['resource_class_id']= $this->getRc('lexinfo:PartOfSpeech')->id();
        $query['property'][0]['property']= $this->getProp('oa:hasSource')->id();
        $query['property'][0]['type']='res';
        $query['property'][0]['text']=$item->id(); 
        $magic = $this->api->search('items', $query)->getContent()[0];                
        return ['media'=>$media,'item'=>$item,'magic'=>$magic];
    }

    /**
     * récupère les choix
     * 
     * @param   array   $post
     *
     * @return array
     */
    function showChoix($post){
        $query['resource_class_id']=$this->getRc('oa:Choice')->id();

        foreach ($post['qs'] as $q) {
            switch ($q['group']) {
                case "skos:Concept":
                    $p = 'jdc:hasConcept';
                    break;
                default:
                    $p = $q['group'];
                    break;
            }
            $query['property'][0]['property']= $this->getProp($p)->id();
            $query['property'][0]['type']='res';
            $query['property'][0]['text']=$q['id']; 
        }
        $results = $this->api->search('items',$query)->getContent();
        $rs = [];
        foreach ($results as $r) {
            $rs[] = $this->createTimelinerEntry(false, false, $r);
        }
        return $rs;

    }

    /**
     * création des sous-titre
     * 
     * @param   int   $numPos
     * @param   int   $idSrc
     *
     * @return array
     */
    function getWebVTT($numPos, $idSrc){
        //récupère la source de l'annotation pour accentuer les mots clefs
        //$src = $this->api->read('items',$idSrc)->getContent();
        //récupère les parties du discours
        $query['resource_class_id']=$this->getRc('lexinfo:PartOfSpeech')->id();
        $query['property'][0]['property']= $this->getProp('oa:hasSource')->id();
        $query['property'][0]['type']='res';
        $query['property'][0]['text']=$idSrc; 
        $results = $this->api->search('items',$query)->getContent();
        $pos = $results[$numPos];
        $vtt = "WEBVTT\n\n";//.PHP_EOL.PHP_EOL;
        $starts = $pos->value('oa:start',['all'=>true]);                                                
        $ends = $pos->value('oa:end',['all'=>true]);
        $cpt = $pos->value('jdc:hasConcept',['all'=>true]);
        $nb = count($cpt);
        for ($i=0; $i < $nb; $i++) { 
            $vtt .= $this->formatWebVTTtimestamp($starts[$i]->__toString())
                ." --> ".$this->formatWebVTTtimestamp($ends[$i]->__toString())."\n";
            $vtt .= $cpt[$i]->valueResource()->displayTitle()."\n\n";
        }
        return $vtt;
    }

    /**
     * format les timestamp de google pour un fichier WebVTT
     * 
     * @param   string  $ts
     *
     * @return string  
     */
    function formatWebVTTtimestamp($ts){        
        $arr = explode(':',$ts);        
        if(count($arr)==2)return "00:".str_pad($arr[0], 2, "0", STR_PAD_LEFT).".".str_pad(substr($arr[1],0,3), 3, "0", STR_PAD_LEFT);
        else return str_pad($arr[0], 2, "0", STR_PAD_LEFT).":".str_pad($arr[1], 2, "0", STR_PAD_LEFT).".".str_pad(substr($arr[1],0,3), 3, "0", STR_PAD_LEFT);
    }


    /**
     * ajoute des couches au scénario
     * 
     * @param   int     $idScenario
     * @param   array   $post
     *
     * @return array
     */
    function addLayers($idScenario, $post){
        $rs = $this->acl->userIsAllowed(null,'create');
        if($rs){
            $rs = [];
            $s = $this->api->read('items', $idScenario)->getContent();
            //boucle sur les relations
            foreach ($post['layers'] as $k => $vals) {
                if(isset($vals['rela'])){
                    foreach($vals['rela'] as $v){
                        if(is_string($v)){
                            //creation de la catégorie
                            $oItem = [];
                            $oItem['o:resource_class'] = ['o:id' => $this->getRc($vals['c'])->id()];
                            $this->setValeur(date(DATE_ATOM),$this->getProp("dcterms:created"),$oItem);
                            $this->setValeur($v,$this->getProp("dcterms:title"),$oItem);
                            $cat = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
                        }else $cat = $this->api->read('items', $v['id'])->getContent();
                        //vérifie si une track existe avec pour source cette catégorie et ce scénario
                        $query['property'][0]['property']= $this->getProp('genstory:hasScenario')->id();
                        $query['property'][0]['type']='res';
                        $query['property'][0]['text']=$idScenario; 
                        $query['property'][1]['property']= $this->getProp('oa:hasSource')->id();
                        $query['property'][1]['type']='res';
                        $query['property'][1]['text']=$cat->id(); 
                        $result = $this->api->search('items',$query)->getContent();
                        if($result){
                            $rs[]=[
                            'message'=>'la couche "'.$cat->displayTitle().'" ('.$cat->id().') existe déjà dans ce scénario'
                            ];
                        }else{
                            $inScheme = $s->value('skos:inScheme') ? $s->value('skos:inScheme')->__toString() : '';
                            $rc = $cat->resourceClass();
                            switch ($inScheme) {
                                case "groupByScopeSourceClass":
                                    $idScope = isset($post['track']['idScope']) ? $post['track']['idScope'] : $this->getScope($post['track']['scope'])->id(); 
                                    $post['track']['oa:hasScope']=$idScope;
                                    $post['track']['idGroup']='groupByScopeSourceClass:'.$idScope.':'.$rc->id().':'.$cat->id();
                                    $post['track']['category']=$rc->label().' : '.$cat->displayTitle();
                                    break;
                                case "groupBySourceClassTarget":
                                    $post['track']['idGroup']=$cat->id().' : '.$rc->id().' : 0';
                                    $post['track']['category']=$rc->label().' : no';
                                    break;
                                case "groupBySourceClassBody":
                                    $post['track']['idGroup']=$cat->id().' : '.$rc->id().' : 0';
                                    $post['track']['category']=$rc->label().' : no';
                                    break;                            
                                case "groupByCategoryCreator":
                                default:
                                    $crea = $this->api->read('items', $post['track']['dcterms:creator'])->getContent();
                                    $post['track']['idGroup']=$cat->id().'_'.$crea->id();
                                    $post['track']['category']=$cat->displayTitle().' : '.$crea->displayTitle();
                                    break;
                            }        
                            //création de la couche                        
                            $post['track']['dcterms:title']=$this->getRc($vals['c'])->label().' : '.$cat->displayTitle();
                            $post['track']['oa:start']=0;
                            $post['track']['oa:end']=5;
                            $post['track']['schema:color']=$this->aleaColor();
                            $post['track']['oa:hasSource']=$cat->id();
                            $post['track'][$k]=$cat->id();
                            $rs[]=[
                                'message'=>'La couche '.$cat->displayTitle().' ('.$cat->id().') est crée dans ce scénario'
                                ,'track'=>$this->createTrack($post['track'])
                                ,'cat'=>$cat
                            ];
                        }
                    }
                }
            }
            return $rs;               
        }else return ['error'=>"droits insuffisants",'message'=>"Vous n'avez pas le droit de créer une couche."];

    }

    /**
     * récupère le scope à partir du titre
     * 
     * @param   string   $titre
     *
     * @return o:Item
     */
    function getScope($titre){

        //vérifie si le scope existe
        $query['property'][0]['property']= $this->getProp('dcterms:title')->id();
        $query['property'][0]['type']='eq';
        $query['property'][0]['text']=$titre; 
        $query['resource_class_id']=$this->getRc('genstory:histoire')->id();
        $result = $this->api->search('items',$query)->getContent();
        if(!$result){
            $oItem = [];
            $oItem['o:resource_class'] = ['o:id' => $this->getRc('genstory:histoire')->id()];
            $this->setValeur(date(DATE_ATOM),$this->getProp("dcterms:created"),$oItem);
            $this->setValeur($titre,$this->getProp("dcterms:title"),$oItem);
            $item = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
        }else $item = $result[0];
        return $item;
    }


    /**
     * récupère le resource template pour avoir la définition des suggestions
     * 
     * @param   string   $label
     * @param   string   $urlApi
     *
     * @return array
     */
    function getRtSuggestion($label, $urlApi){

        $props=[];
        $rt = $this->api->read('resource_templates', ['label' => $label])->getContent();
        $rtp = $rt->resourceTemplateProperties();
        foreach ($rtp as $p) {
            $ac = $p->alternateComment();
            if(substr($ac,0,10)=="suggestion"){
                $class = $this->api->search('resource_classes', ['term' => substr($ac,11)])->getContent()[0];
                $url = $urlApi
                    .'/items?resource_class_id='.$class->id()
                    .'&property[0][property]=1&property[0][type]=in&property[0][text]=%QUERY&sort_by=title'
                ;
                $props[]=['p'=>$p->property(),'url'=>$url,'c'=>$class];
            }
            $dt = $p->dataTypes();
            if($dt && substr($dt[0],0,11)=="customvocab"){
                $cv = $this->api->read('custom_vocabs', substr($dt[0],12))->getContent();
                $url = $urlApi
                    .'/items?item_set_id='.$cv->itemSet()->id()
                    .'&property[0][property]=1&property[0][type]=in&property[0][text]=%QUERY&sort_by=title'
                ;
                $props[]=['p'=>$p->property(),'url'=>$url];
            }
        }
        return $props;
    }

    /**
     * génère des objets à partir des textes du scenario
     * 
     * @param   int   $idItem
     *
     * @return array
     */
    function scenarioTxtToObj($idItem){
        $rs = $this->acl->userIsAllowed(null,'create');
        if($rs){
            $item = $this->api->read('items', $idItem)->getContent();
            //récupère toutes les tracks
            $tracks = $this->getScenarioTracks($idItem);
            foreach ($tracks as $t) {
                //vérifie si le titre de l'item est une ressource de la bonne class
                $existe = false;
                $titreTrack = $t->value('dcterms:title')->__toString();
                $cat = $t->value('schema:category')->valueResource();
                $catClass = $cat->value('skos:narrower') ? $cat->value('skos:narrower')->__toString() : 'skos:Concept';
                $catProp = $cat->value('skos:hasTopConcept') ? $cat->value('skos:hasTopConcept')->__toString() : 'jdc:hasPhysique';
                $vals = $t->value($catProp,['all'=>true]);                                                
                foreach ($vals as $v) {
                    $titreRL = $v->valueResource()->displayTitle();
                    if($titreRL==$titreTrack)$existe = true;
                }
                if(!$existe){
                    $dataTrack = json_decode(json_encode($t), true);
                    //vérifie si la ressource existe
                    $query['property'][0]['property']= $this->getProp('dcterms:title')->id();
                    $query['property'][0]['type']='eq';
                    $query['property'][0]['text']=$titreTrack; 
                    $query['resource_class_id']=$this->getRc($catClass)->id();
                    $result = $this->api->search('items',$query)->getContent();
                    if(!count($result)){
                        //création de la resource
                        $data = [
                            'o:resource_class'=>['o:id' => $query['resource_class_id']],
                            'dcterms:title'=>[['@value' => $titreTrack,'type' => 'literal', 'property_id'=>$query['property'][0]['property']]]
                        ];
                        $itemRef = $this->api->create('items',$data)->getContent();                          
                    }else $itemRef=$result[0];
                    $iProp = isset($dataTrack[$catProp]) ? count($dataTrack[$catProp]) : 0;
                    $dataTrack[$catProp][$iProp]=[
                        'value_resource_id'=>$itemRef->id(),'property_id'=>$this->getProp($catProp)->id(),'type'=>'resource'              
                    ];
                    $this->api->update('items', $t->id(),$dataTrack, [], ['continueOnError' => true,'isPartial'=>1, 'collectionAction' => 'replace']);    
                }

            }
        }
    }


    /**
     * génère un scénario
     * 
     * @param   array   $query
     * @param   array   $post
     *
     * @return array
     */
    function genereScenario($query, $post){
        if(!is_writable($this->tempPath)){
            throw new RuntimeException("Le dossier '".$this->tmpPath."' n'est pas accessible en écriture.");			
        }        
        $result = $this->createScenario($this->genScenario($query,$post));
        $medias  = $result->media();
        foreach ($medias as $m) {
            switch ($m->displayTitle()) {
                case 'json file for timeliner':
                    $jsonUrl = $m->originalUrl();
                    break;                
            }
        }

        return [
            "o:id"=>$result->id(),
            "o:title"=>$result->displayTitle(),
            'json'=>$jsonUrl,
        ];

    }

    /**
     * supression d'un track
     * 
     * @param   array   $post
     *
     * @return array
     */
    function deleteLayer($post){
        $rs = $this->acl->userIsAllowed(null,'delete');
        if($rs){
            //supprime toutes les tracks de la couche
            foreach ($post['ids'] as $id) {
                $this->api->delete('items', $id);
            }
            return ['message'=>"Couche(s) supprimée(s)."];               
        }else return ['error'=>"droits insuffisants",'message'=>"Vous n'avez pas le droit de supprimer."];

    }

    /**
     * supression d'un track
     * 
     * @param   array   $post
     *
     * @return array
     */
    function deleteTrack($post){
        $rs = $this->acl->userIsAllowed(null,'delete');
        if($rs){
            $this->api->delete('items', $post['id']);
            return ['message'=>"Track supprimé."];               
        }else return ['error'=>"droits insuffisants",'message'=>"Vous n'avez pas le droit de supprimer."];

    }
    
    /**
     * creation d'un track
     * 
     * @param   array   $post
     *
     * @return o:item
     */
    function createTrack($post){
        $rs = $this->acl->userIsAllowed(null,'create');
        if($rs){
            $i = $this->saveTrack($post,isset($post['rt']) ? $post['rt'] : 'Indexation vidéo');
            return isset($post['getItem']) ? $i : $this->getTimelinerTrack($post, $i);               
        }else return ['error'=>"droits insuffisants",'message'=>"Vous n'avez pas le droit de créer."];

    }
    /**
     * met en forme un track
     * 
     * @param   array       $post
     * @param   o:Item      $i
     *
     * @return array
     */
    function getTimelinerTrack($post, $i){
        if(!$i)$i = $this->api->read('items', $post['oItem']->id())->getContent();
        $result[] = $this->createTimelinerEntry($post['idGroup'], $post['category'], $i);
        $result[] = [
            "time"=> (float)$post["oa:end"],
            "value"=> 1,
            "idObj"=>isset($post["idObj"]) ? $post["idObj"] : $i->id()
        ];
        return $result;               
    }

    /**
     * ajout d'une catégorie pour les layer
     * 
     * @param array $params
     *
     * @return o:item
     */
    function addCategory($params){
        $rs = $this->acl->userIsAllowed(null,'create');
        if($rs){
            //enregistre une indexation dans la base
            $rt =  $this->getRt($params['rt']);
            //vérifie l'existence de la catégorie
            $query['property'][0]['property']= $this->getProp('dcterms:title')->id();
            $query['property'][0]['type']='eq';
            $query['property'][0]['text']=$params['dcterms:title']; 
            $query['o:resource_class_id']=$rt->resourceClass()->id(); 
            $query['o:resource_template_id']=$rt->id();                         
            $result = $this->api->search('items',$query)->getContent();
            if(count($result)){
                return $result[0];
            } else {
                $oItem = [];
                $oItem['o:resource_class'] = ['o:id' => $rt->resourceClass()->id()];
                $oItem['o:resource_template'] = ['o:id' => $rt->id()];
                $rtp = $rt->resourceTemplateProperties();
                foreach ($rtp as $p) {
                    $oP = $p->property();
                    switch ($oP->term()) {
                    case "dcterms:created":
                    case "dcterms:modified":
                        $this->setValeur(date(DATE_ATOM),$oP,$oItem); 
                        break;                                                            
                    default:
                        if(isset($params[$oP->term()])){
                            $this->setValeur($params[$oP->term()],$oP,$oItem); 
                        }
                        break;
                    }
                }
                //vérifie la mise à jour
                if(isset($params['id'])){
                    //$oItem
                    $result = $this->api->read('items', $params['id'])->getContent();
                    //conserve la date de création
                    $oItem['dcterms:created'][0]['@value']=$result->value('dcterms:created')->__toString();
                    $this->api->update('items', $result->id(), $oItem, [], ['isPartial'=>1,'continueOnError' => true, 'collectionAction' => 'replace']);
                    $result = $this->api->read('items',$result->id())->getContent();
                }else{
                    $result = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
                }
            }              
            return $result;               
        }else return ['error'=>"droits insuffisants",'message'=>"Vous n'avez pas le droit de créer une catégorie."];

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
            $tracks = $this->getScenarioTracks($id);
            foreach ($tracks as $t) {
                $ids[]=$t->id();
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
                $term = 'relation';
                $data = [];
                $pVal =  $this->getProp($p);
                $comment = $rt->resourceTemplateProperty($pVal->id())->alternateComment();
                if(substr($comment,0,6)=='class='){
                    $term = substr($comment,6); 
                    $rc =  $this->getRc($term);
                    $data['o:resource_class'] = ['o:id' => $rc->id()];
                }
                //si la valeur n'est pas une ressource on la crée
                if($v->type()=='literal'){
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
                }else{
                    //met à jour l'item avec la bonne classe
                    $vr = $v->valueResource();
                    if(!$vr->resourceClass() && $rc){
                        $data = json_decode(json_encode($vr), true);
                        $data['o:resource_class'] = ['o:id' => $rc->id()];
                        $this->api->update('items', $vr->id(),$data, [], ['continueOnError' => true,'isPartial'=>1, 'collectionAction' => 'replace']);    
                    }
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
    function saveTrack($params, $rtLabel){
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
            case "oa:hasBody":
            case "oa:hasTarget":
            case "oa:hasScope":
            case "schema:category":
            case "dcterms:creator":
            case "genstory:hasActant":
            case "genstory:hasAffect":
            case "genstory:hasEvenement":
            case "genstory:hasHistoire":
            case "genstory:hasLieu":
            case "genstory:hasMonde":
            case "genstory:hasObjet":
            case "ma:isFragmentOf":
                if(isset($params[$oP->term()]) && $params[$oP->term()]){
                    $val = $params[$oP->term()];
                    if(!is_array($val)) $val= [['id'=>$val]];
                    $this->setValeur($val,$oP,$oItem); 
                }
                break;                    
            case "genstory:hasScenario":
                if(isset($params[$oP->term()]))
                    $this->setValeur([['id'=>$params['idScenario']]],$oP,$oItem); 
                break;                    
            case "dcterms:created":
            case "dcterms:modified":
                $this->setValeur(date(DATE_ATOM),$oP,$oItem); 
                break;                                                            
            default:
                if(isset($params[$oP->term()])){
                    $this->setValeur($params[$oP->term()],$oP,$oItem); 
                }
                break;
        }
        }
        //vérifie la mise à jour
        if(isset($params['idObj'])){
            //$oItem
            $result = $this->api->read('items', $params['idObj'])->getContent();
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
                $this->api->update('items', $item->id(), $dataItem, [], ['continueOnError' => true,'isPartial'=>1, 'collectionAction' => 'replace']);    
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
     * récupération des traks d'un scénario
     * 
     * @param array     $id
     *
     * @return array
     */
    function getScenarioTracks($id){
        $param = array();
        $param['property'][0]['property']= $this->getProp('genstory:hasScenario')->id();
        $param['property'][0]['type']='res';
        $param['property'][0]['text']=$id; 
        return $this->api->search('items',$param)->getContent();
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
        $gen = isset($query['gen']) ? $query['gen'] : "";
        $inScheme = isset($query['inScheme']) ? $query['inScheme'] : 'no';
        $titre = isset($post['dcterms:title']) ? $post['dcterms:title'] : "new scenario";
        $rt = 'Scenario';
        $IRB = "";
        $dateCreation=date(DATE_ATOM);
        switch ($gen) {
            case 'global':
                $rt =  $this->api->search('resource_templates', ['label' => 'Indexation vidéo',])->getContent()[0];
                $ids = $query['item_id'] ? [$query['item_id']] : $post['item_id'];
                $items = [];
                $titre = "";
                $p =  $this->api->search('properties', ['term'=>'oa:hasSource'])->getContent()[0];
                foreach ($ids as $idItem) {
                    $item = $this->api->read('items',$idItem)->getContent();
                    $params = [];
                    $params["resource_template_id"]=$rt->id();//indexation vidéo
                    $params['property'][0]['property']= $p->id();
                    $params['property'][0]['type']='res';
                    $params['property'][0]['text']=$idItem; 
                    $items = array_merge($items, $this->api->search('items',$params)->getContent());
                    $titre .= $item->value('bibo:shortTitle') ? $item->value('bibo:shortTitle')->__toString().' - ' : $item->id().' - ';
                }
                //groupe les items par category et creator pour visualiser les point de vue
                $gItems = $this->groupByCategoryCreator($items);
                $inScheme = "groupByCategoryCreator";
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
                            //si la valeur est une ressource on récupère l'index
                            if($v->type()=='resource'){
                                $itemVal = $v->valueResource();
                                $target = $itemVal->primaryMedia();
                                $params = [
                                    'dcterms:title'=> $op->label().' - '.$itemVal->value('dcterms:title')->__toString().' : 1',
                                    'dcterms:description'=> $itemVal->value('dcterms:description') ? $itemVal->value('dcterms:description')->__toString() : '',
                                    'oa:start'=>0,
                                    'oa:end'=>10,
                                    'oa:hasScope'=>$item->id(),                                    
                                    'oa:hasSource'=>$itemVal->id(),
                                    'dcterms:creator'=>$post['dcterms:creator'],
                                ];
                                if($target)$params['oa:hasTarget']= $target->id();
                                $params[$p]=$itemVal->id();                            
                                $items[] = $this->saveTrack($params,'Scenario track');
                            }
                        }            
                    }
                }
                //groupe les items par titre
                $gItems = $this->groupByScopeSourceClass($items);
                $IRB = $gen.'-'.$item->id();
                $inScheme = "groupByScopeSourceClass";
                break;            
            case 'fromUti':
                //récupère les tracks du scenario pour mettre à jour les infos des sources
                $items = [];
                $item = $this->api->read('items',$post['idScenario'])->getContent();
                $titre = $item->displayTitle();
                $tracks = $this->getScenarioTracks($post['idScenario']);
                $inScheme = $item->value('skos:inScheme') ? $item->value('skos:inScheme')->__toString() : '';
                $IRB = $item->value('dcterms:isReferencedBy')->__toString();
                $dateCreation=$item->value('dcterms:created')->__toString();
                if(substr($IRB,0,7)!='fromUti')$IRB='fromUti-'.$post['idActant'].':'.$IRB;
                switch ($inScheme) {
                    case "groupByScopeSourceClass":
                        $gItems = $this->groupByScopeSourceClass($tracks);
                        break;
                    case "groupBySourceClassTarget":
                        $gItems = $this->groupBySourceClassTarget($tracks);
                        break;
                    case "groupBySourceClassBody":
                        $gItems = $this->groupBySourceClassBody($tracks);
                        break;                            
                    case "groupByCategoryCreator":
                    default:
                        $gItems = $this->groupByCategoryCreator($tracks);
                        break;
                }
                break;
            default:
                $gItems = []; 
                break;
        }
        
        $scenario = [
            "version"=>"1.2.0",
            "modified"=>date(DATE_ATOM),
            "title"=>$titre,
            "groupBy"=>$inScheme,
            "isReferencedBy"=>$IRB,
            "dateCreation"=>$dateCreation,
            "idScenario"=>isset($post['idScenario']) ? $post['idScenario'] : 0,
            "layers"=>[],
            "ui"=> [
                "currentTime"=>0,
                "totalTime"=>0,
                "scrollTime"=>0,
                "timeScale"=>60
                ]    
            ];
        $bodies=[];$facettes = [];$categories=[];$doublons=[];
        $totalTime = 0;$debTime=100000000000000000;
        $idLayer=0;
        foreach ($gItems as $k => $groupe) {
            $categories[]=$groupe['id'];
            $layer = [
                "id"=>$groupe['id'],
                "idLayer"=>$idLayer,
                "class"=>isset($groupe['class']) ? $groupe['class'] : 'item',
                "source"=>isset($groupe['source']) ? $groupe['source'] : 'item',
                "name"=>$k,
                "_color"=> $groupe['color'],
                "_value"=> 0,
                "desc"=> $groupe['desc'],
                "values"=> []
            ];
            //ajoute les entrées enregistrées
            foreach ($groupe['items'] as $i) {
                $e = $this->createTimelinerEntry($groupe['id'], $k, $i);
                $bodies[]=$e;
                if($debTime>$e["time"])$debTime=$e["time"];            
                $layer['values'][]=$e;
                //création des facettes
                foreach ($this->propsValueRessource as $p) {
                    if(!isset($doublons[$p])){
                        $doublons[$p]=[];
                        $facettes[$p]=[];
                    }
                    if(isset($e[$p])){
                        foreach ($e[$p] as $v) {
                            if(is_object($v) && !isset($doublons[$p][$v->id()])){
                                $facettes[$p][]=$v->id();
                                $doublons[$p][$v->id()]=1;
                            }
                        }    
                    }
                } 
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
                    "idObj"=>$v["idObj"],
                ];
                if($totalTime<$v["timeEnd"])$totalTime=$v["timeEnd"];
            };
            //met à jour le scénario
            $scenario['layers'][$i]['values']=$vals;
        }

        //mise à jour des temporalités globales        
        $scenario['ui']['currentTime']=$totalTime==0 ? 0 : $debTime;
        $scenario['ui']['totalTime']=$totalTime==0 ? 60 : $totalTime;
        $scenario['ui']['scrollTime']=$totalTime==0 ? 0 : $debTime;
        return ["rt"=>$rt,"type"=>$gen,"scenario"=>$scenario,"bodies"=>$bodies,"facettes"=>$facettes,"categories"=>$categories];
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
    function createTimelinerEntry($idGroup, $category, $i){
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

        $l = [
            "time"=>$ts,
            "timeEnd"=>$te,
            "value"=> 0,
            "start"=> $i->value('oa:start') ? $i->value('oa:start')->__toString() : $ts,
            "end"=> $i->value('oa:end') ? $i->value('oa:end')->__toString() : $te,
            "_color"=> $i->value('schema:color') ? $i->value('schema:color')->__toString() : $this->aleaColor(),
            "idObj"=> $i->id(),
            "idGroup"=>$idGroup,
            "category"=>$category,
            "prop"=> $prop,
            "text"=> $i->displayTitle(),
            "ordre"=> $i->value('genstory:ordre') ? $i->value('genstory:ordre')->__toString() : 1,
            "tween"=> "linear",
        ];
        //ajoute la catégorie
        if($i->value('schema:category')){
            $l["idCat"]=$i->value('schema:category')->valueResource()->id();
        }
        //ajoute les infos des ressources
        foreach ($this->propsValueRessource as $p) {
            $this->addRessourceInfos($p,$i,$l);
        }
        //ajoute les médias
        $medias = $i->media();
        if($medias){
            $l["medias"]=$medias;
        }
        return $l;
    }

    /**
     * ajoute les infos de la ressource valueResource  
     * 
     * @param string    $p
     * @param o:item    $i
     * @param array     $l
     *
     * @return array
     */
    function addRessourceInfos($p, $i, &$l){
        $vals = $i->value($p, ['all' => true]);
        foreach ($vals as $v) {
            if(!isset($l[$p]))$l[$p]=[];
            switch ($v->type()) {
                case "literal":
                    $l[$p][]= $v->__toString();
                    break;
                case "uri":
                    $l[$p][]= $v->__toString();
                    break;                    
                default://pour gérer les customvocabs
                    $l[$p][]= $v->valueResource(); 
                    break;
            }
        }
        /*
        $r = $i->value($p) ? $i->value($p)->valueResource() : false;
        if($r){
            $l[$p.":id"]= $r->id();
            $l[$p.":title"]= $r->displayTitle();
            if($r->resourceName()=='media'){
                $l[$p.":type"]= $r->mediaType();
                $l[$p.":url"]= $r->originalUrl();
            }else{                        
                $l[$p.":type"]= $r->resourceName();
                $l[$p.":url"]= $r->url();
                $l[$p.":medias"]= $r->media();
            }                  
        }
        */
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
        //set_time_limit(300);
        $rt =  $this->api->search('resource_templates', ['label' => $data['rt'],])->getContent()[0];
        $oItem = [];
        $oItem['o:resource_class'] = ['o:id' => $rt->resourceClass()->id()];
        $oItem['o:resource_template'] = ['o:id' => $rt->id()];
        $rtp = $rt->resourceTemplateProperties(); 
        foreach ($rtp as $p) {
          $oP = $p->property();
          switch ($oP->term()) {
            case "dcterms:title":
                $this->setValeur($data['scenario']['title'],$oP,$oItem); 
                break;
            case "skos:inScheme":
                $this->setValeur($data['scenario']['groupBy'],$oP,$oItem); 
                break;
            case "dcterms:isReferencedBy":
                $pIRB = $oP;
                $IRB = $data['scenario']['isReferencedBy'] ? $data['scenario']['isReferencedBy'] 
                    : $data['type']."-";
                //$IRB .= $data['facettes'] ? implode('_',$data['facettes']['oa:hasSource']) : ' - ';
                $this->setValeur($IRB,$oP,$oItem); 
                break;
            /*trop gourmand en ressource
            case "schema:object":
                $this->setValeur(json_encode($data['scenario']),$oP,$oItem); 
                break;                                                                                
            case "schema:category":
                foreach ($data['categories'] as $s) {
                    $this->setValeur([['id'=>$s]],$oP,$oItem); 
                }
                break;                    
            case "oa:hasBody":
                foreach ($data['bodies'] as $b) {
                    $this->setValeur([['id'=>$b['idObj']]],$oP,$oItem); 
                }
                break;                    
            case "oa:hasTarget":
            case "oa:hasSource":
            */
            case "dcterms:creator":
                if(isset($data['facettes']) && isset($data['facettes'][$oP->term()]))
                foreach ($data['facettes'][$oP->term()] as $s) {
                    $this->setValeur([['id'=>$s]],$oP,$oItem); 
                }
                break;        
            case "dcterms:created":
            case "dcterms:modified":
                $this->setValeur(date(DATE_ATOM),$oP,$oItem); 
                break;                                                            
            }
        }

        //attachement du fichier json
        $this->jsonAttachment($oItem, $data['scenario']);
        
        //vérifie la mise à jour
        if($data['scenario']['idScenario']){
            $oItem['dcterms:created'][0]['@value']=$data['scenario']['dateCreation'];
            //ajoute les médias existant sauf : 'json file for timeliner'
            $medias  = $this->api->read('items',$data['scenario']['idScenario'])->getContent()->media();
            foreach ($medias as $m) {
                if($m->displayTitle()!='json file for timeliner'){
                    $oItem['o:media'][]=json_decode(json_encode($m), true);
                }
            }
            $this->api->update('items', $data['scenario']['idScenario'], $oItem, [], ['isPartial'=>1,'continueOnError' => true, 'collectionAction' => 'replace']);
            $result = $this->api->read('items',$data['scenario']['idScenario'])->getContent();
        }else{
            $result = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
            //mise à jour des tracks avec l'identifiant du scenario
            $dt = [];
            $this->setValeur([['id'=>$result->id()]],$this->getProp('genstory:hasScenario'),$dt); 
            foreach ($data['bodies'] as $b) {
                $this->api->update('items', $b['idObj'], $dt, [], ['isPartial'=>1,'collectionAction' => 'append']);
            }
            //$response = $this->api->batchUpdate('items',$ids, $dt, [], ['isPartial'=>1,'collectionAction' => 'append']);
        }

        //suppression du fichier temporaire
        unlink($this->temp);
        return $result;
    }

    /**
     * json attachment.
     *
     * @param array $oItem
     * @param array $data
      */
      protected function jsonAttachment(&$oItem, $data)
      {
        //creation du fichier temporaire
        $p = uniqid().'.json';
        $this->temp = $this->tempPath.'/'.$p;
        $f = fopen($this->temp, 'w');
        fwrite($f, json_encode($data));
        fclose($f);
        $url =$this->tempUrl.'/'.$p;
        //ATTENTION sur JDC il faut utiliser l'IP
        $property = $this->getProp('dcterms:title');
        $oItem['o:media'][] = [
            'o:ingester' => 'url',
            'o:source'   => $url,
            'ingest_url' => $url,
            $property->term() => [
                [
                    '@value' => 'json file for timeliner',
                    'property_id' => $property->id(),
                    'type' => 'literal',
                ],
            ],
        ];
      }

     /** Construction de la valeur
     *
     * @param   array   $val
     * @param   object  $oP
     * @param   array   $oItem //par référence pour gagner de la mémoire
     */
    protected function setValeur($val, $oP, &$oItem)
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
            $val = $rc->label().' : '.$target->displayTitle();
            $id = $source->id().' : '.$rc->id().' : '.$target->id();
            $color = $this->aleaColor();
            $desc = '';
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
    function groupBySourceClassBody($data) {
        $result = array();
        foreach($data as $item) {
            //construction de la clef
            $source = $item->value('oa:hasSource')->valueResource();
            $body = $item->value('oa:hasBody')->valueResource();
            $rc = $body->resourceClass();

            $color = "";
            $desc = "";
            $val = $rc->label().' : '.$body->displayTitle();
            $id = $source->id().' : '.$rc->id().' : '.$body->id();
            $color = $this->aleaColor();
            $desc = '';
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
    function groupByScopeSourceClass($data) {
        $result = array();
        foreach($data as $item) {
            //construction de la clef
            $source = $item->value('oa:hasSource')->valueResource();
            $scopeId = $item->value('oa:hasScope') ? $item->value('oa:hasScope')->valueResource()->id() : 0;
            $rc = $source->resourceClass();

            $color = "";
            $desc = "";
            $val = $rc->label().' : '.$source->displayTitle();
            $id = 'groupByScopeSourceClass:'.$scopeId.':'.$rc->id().':'.$source->id();
            $color = $this->aleaColor();
            $desc = '';
            if(!array_key_exists($val, $result))$result[$val]=['items'=>[],'id'=> $id,'source'=> $source,'class'=> $rc,'color'=> $color,'desc'=> $desc];
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
