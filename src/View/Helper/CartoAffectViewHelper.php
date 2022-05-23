<?php
namespace CartoAffect\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\Identity;
use DateTime;

class CartoAffectViewHelper extends AbstractHelper
{

    protected $api;
    protected $auth;
    protected $config;
    protected $customVocab;
    protected $p;
    protected $actant;

    public function __construct($api, $auth, $config)
    {
      $this->api = $api;
      $this->auth = $auth;
      $this->config = $config;
    }

    public function __invoke($data)
    {

      if(isset($data['logout'])){
        $this->auth->clearIdentity();
        return true;
      }

      if(isset($data['getActantAnonyme'])){
        $actant = $this->getActantAnonyme();       
        $this->auth->clearIdentity();
        return $actant;
      }

      //récupère l'actant
      $this->actant = $this->ajouteActant($data['user']);

      if(isset($data['getActant'])){
        return $this->actant;
      }


      //remplace l'identifiant de l'utilisateur par l'actant
      $data['post']['rapports']['ma:hasCreator'][0]['value']=$this->actant->id();
      $data['post']['rapports']['jdc:hasActant'][0]['value']=$this->actant->id();

      $position = false;

      switch (isset($data['query']) && $data['query']['type']) {
        case 'getPosis':
          $position = $this->getPositions($data['query']['idDoc'], $this->actant->id(), $data['query']['idCrible']);        
          break;
      }


      //récupère la position sémantique suivant le ressource template
      if(isset($data['post']['rt'])){
        switch ($data['post']['rt']) {
          case 'Position sémantique : sonar':
            $position = $this->ajouteSonarPosition($data['post'], $data['post']['rt']);
            $position = $this->getPositions($position->value('jdc:hasDoc')->valueResource()->id(), $this->actant->id(), $position->value('ma:hasRatingSystem')->valueResource()->id());           
          break;
          case 'Position sémantique : Geneva Emotion':
            $position = $this->ajouteSonarPosition($data['post'], $data['post']['rt']);        
          break;
          case 'Position sémantique : Geneva Emotion corrections':
            $position = $this->ajoutePositionCorrections($data['post'], $data['post']['rt']);        
          break;
          case 'Processus CartoAffect':
            $position = $this->ajouteProcessus($data['post']);        
          break;
          case 'menu-qualification':
            $position = $this->ajouteSonarPosition($data['post'],$data['post']['rt']);        
          case 'changeItemItemSet':
            $position = $this->changeItemItemSet($data['post']);        
          break;        
            default:
              $position = $this->ajouteSonarPosition($data['post'], $data['post']['rt']);        
            break;
          }
      }
      //vérifie s'il faut déconnecter annonyme
      if(isset($data['user']) && $data['user']->getEmail()==$this->config['CartoAffect']['config']['cartoaffect_mail'])$this->auth->clearIdentity();

      return $position;
    }

    /**
     * Récupère le position sonar pour un doc, un actant et un crible
     *
     * @param integer               $idDoc      
     * @param integer               $idActant
     * @param integer               $idCrible
     * 
     * @return array
     */
    protected function getPositions($idDoc, $idActant, $idCrible)
    {
        //requête pour récupèrer les positions pour un crible et un document      
        $pHasDoc = $this->api->search('properties', ['term' => 'jdc:hasDoc'])->getContent()[0];
        $pHasRatingSystem = $this->api->search('properties', ['term' => 'ma:hasRatingSystem'])->getContent()[0];
        $pHasActant = $this->api->search('properties', ['term' => 'jdc:hasActant'])->getContent()[0];

        $query = array();
        $query['property'][0]['property']= $pHasDoc->id();
        $query['property'][0]['type']='res';
        $query['property'][0]['text']=$idDoc; 
        $query['property'][0]['joiner']="and"; 
        $query['property'][1]['property']= $pHasRatingSystem->id();
        $query['property'][1]['type']='res';
        $query['property'][1]['text']=$idCrible; 
        $query['property'][1]['joiner']="and"; 
        $query['property'][1]['property']= $pHasActant->id();
        $query['property'][1]['type']='res';
        $query['property'][1]['text']=$idActant; 
        $query['property'][1]['joiner']="and"; 

        $result = $this->api->search('items', $query)->getContent();

        return $result;
    }

    /** Modifie le rapport entre item et itemSet
     *
     * @param array   $data
     * @return object
     */
    protected function changeItemItemSet ($data)
    {
      $item = $this->api->read('items', $data['id'])->getContent();
      if($data['checked']=='true'){
        $params['o:item_set'] = [
          ['o:id' => $data['isId']]
        ];  
        $result = $this->api->update('items', $data['id'], $params, [], ['isPartial'=>1, 'collectionAction' => 'append'])->getContent();
        $result = $this->api->read('item_sets', $data['isId'])->getContent();         
      }else{
        $itemSets = $item->itemSets();
        $params['o:item_set'] = [];
        foreach ($itemSets as $is) {
          if($is->id()!=$data['isId'])$params['o:item_set'][] = ['o:id' => $is->id()];
        }        
        $result = $this->api->update('items', $data['id'], $params, [], ['isPartial'=>1, 'collectionAction' => 'replace'])->getContent();  
      }

      return $result;
    }

     /** Ajoute un processus de cartographie
     *
     * @param array   $data
     * @return o:item
     */
    protected function ajouteProcessus ($data)
    {
      $rt =  $this->api->search('resource_templates', ['label' => 'Processus CartoAffect'])->getContent()[0];
      $rc =  $this->api->search('resource_classes', ['term' => 'schema:Action'])->getContent()[0];
      //création de l'action
      $d = new DateTime('NOW');
      $dt = [
        'dcterms:title'=>$data['actionApplication']->displayTitle().' - '.$this->actant->displayTitle().' : '.$d->format('Y-m-d')
        ,'dcterms:isReferencedBy'=>$data['actionApplication']->id().'_'.$this->actant->id().'_'.$d->format('U')
        ,'schema:actionApplication'=>[0=>['value'=>$data['actionApplication']->id()]]
        ,'dcterms:created'=>$d->format('c')
        ,'jdc:hasActant'=>[0=>['value'=>$this->actant->id()]]
      ];
      $oItem = $this->setData($dt,$rt, $rc);
      $result = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
      
      return $result;      

    }



     /** Ajoute les correction à une position sémantique
     *
     * @param array   $data
     * @param string  $rtName
     * @return o:item
     */
    protected function ajoutePositionCorrections ($data, $rtName)
    {
      $rt =  $this->api->search('resource_templates', ['label' => $rtName])->getContent()[0];
      $rc =  $this->api->search('resource_classes', ['term' => 'jdc:SemanticPosition'])->getContent()[0];
      //pas de mise à jour des positions
      foreach ($data['rapports'] as $k => $r) {
        if(is_int($k)){
          $r['jdc:hasActant']=$data['rapports']['jdc:hasActant'];
          $r['ma:hasCreator']=$data['rapports']['ma:hasCreator'];
          $oItem = $this->setData($r,$rt, $rc);
          $result[] = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();  
        }
      }
      
      return $result;      

    }
    


     /** Ajoute une position sémantique SONAR
     *
     * @param array   $data
     * @param string  $rtName
     * @return o:item
     */
    protected function ajouteSonarPosition ($data, $rtName)
    {

      $rt =  $this->api->search('resource_templates', ['label' => $rtName])->getContent()[0];
      $rc =  $this->api->search('resource_classes', ['term' => 'jdc:SemanticPosition'])->getContent()[0];
      //pas de mise à jour des positions
      $oItem = $this->setData($data['rapports'],$rt, $rc);
      $result = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
      $data['item']=$result;
      $this->ajouteAnnotation($data);
      
      return $result;      

    }

     /** Construction des data pour l'API
     *
     * @param   array   $data
     * @param   object  $rt
     * @param   object  $rc
     * @return  array
     */
    protected function setData($data, $rt, $rc)
    {
      $oItem = [];
      $oItem['o:resource_class'] = ['o:id' => $rc->id()];
      $oItem['o:resource_template'] = ['o:id' => $rt->id()];
      foreach ($rt->resourceTemplateProperties() as $p) {
        $oP = $p->property();
        if(isset($data[$oP->term()])){
          $val = $data[$oP->term()];
          $oItem = $this->setValeur($val,$oP,$oItem); 
        }
      }
      return $oItem;

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
        if(!is_string($v) && $v['value']){
          $valueObject['value_resource_id']=$v['value'];        
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

     /** récupère l'actant anonyme
     *
     * @return array
     */
    protected function getActantAnonyme()
    {
      $adapter = $this->auth->getAdapter();
      $adapter->setIdentity($this->config['CartoAffect']['config']['cartoaffect_mail']);
      $adapter->setCredential($this->config['CartoAffect']['config']['cartoaffect_pwd']);
      $user = $this->auth->authenticate()->getIdentity();                      
      return $this->api->read('users', ['email'=>$this->config['CartoAffect']['config']['cartoaffect_mail']])->getContent();
  }


     /** Ajoute un actant
     *
     * @param object $user
     * @return o:item
     */
    protected function ajouteActant($user)
    {

      //vérifie la présence de l'item pour gérer les mises à jour
      $foafAN =  $this->api->search('properties', ['term' => 'foaf:accountName'])->getContent()[0];

      $rt =  $this->api->search('resource_templates', ['label' => 'Actant',])->getContent()[0];
      $rc =  $this->api->search('resource_classes', ['term' => 'jdc:Actant',])->getContent()[0];
      $foafA =  $this->api->search('properties', ['term' => 'foaf:account',])->getContent()[0];
      $ident =  $this->api->search('properties', ['term' => 'schema:identifier',])->getContent()[0];

      if(!$user)$user=$this->getActantAnonyme();
      $itemU=$this->api->read('users',  $user->getId())->getContent();

        //création de l'item
        $oItem = [];
        $valueObject = [];
        $valueObject['property_id'] = $foafA->id();
        $valueObject['@value'] = "CartoAffect";
        $valueObject['type'] = 'literal';
        $oItem[$foafA->term()][] = $valueObject;    
        $valueObject = [];
        $valueObject['property_id'] = $ident->id();
        $valueObject['@id'] = $itemU->adminUrl();
        $valueObject['o:label'] = 'omeka user';
        $valueObject['type'] = 'uri';
        $oItem[$ident->term()][] = $valueObject;    

        $param = array();
        $param['property'][0]['property']= $foafAN->id()."";
        $param['property'][0]['type']='eq';
        $param['property'][0]['text']=$user->getName(); 
        $result = $this->api->search('items',$param)->getContent();
        if(count($result)){
          $result = $result[0];
          //vérifie s'il faut ajouter le compte
          $comptes = $result->value($foafA->term(),['all'=>true]);
          foreach ($comptes as $c) {
            $v = $c->asHtml();
            if($v=="CartoAffect")return $result;
          }
          $this->api->update('items', $result->id(), $oItem, [], ['continueOnError' => true,'isPartial'=>1, 'collectionAction' => 'append']);
        }else{
          $valueObject = [];
          $valueObject['property_id'] = $foafAN->id();
          $valueObject['@value'] = $user->getName();
          $valueObject['type'] = 'literal';
          $oItem[$foafAN->term()][] = $valueObject;    
          $oItem['o:resource_class'] = ['o:id' => $rc->id()];
          $oItem['o:resource_template'] = ['o:id' => $rt->id()];  
          $result = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
        }              
        return $result;

    }


    /**
     * Ajoute une annotation au format open annotation
     *
     * @param  array $data
     * 
     * @return array
     */
    protected function ajouteAnnotation($data)
    {
        if(!$this->config['CartoAffect']['config']['ajouteAnnotation'])return;

        //récupère les propriétés
        $this->cacheCustomVocab();      
        $this->cacheProperties();
        $rtA =  $this->api->search('resource_templates', ['label' => 'Annotation'])->getContent()[0];
        $rcA =  $this->api->search('resource_classes', ['term' => 'oa:Annotation'])->getContent()[0];
        
        //création de l'annotation       
        $oItem = [];

        //motivation
        $valueObject = [];
        $valueObject['property_id'] = $this->p['motivatedBy']->id();
        $valueObject['@value'] = isset($data['motivation']) ? $data['motivation'] : 'oa:tagging';
        $valueObject['type'] = 'customvocab:'.$this->customVocab['Annotation oa:motivatedBy'];
        $oItem[$this->p['motivatedBy']->term()][] = $valueObject;    

        //annotator = actant
        if(isset($this->actant)){
          $valueObject = [];                
          $valueObject['value_resource_id']=$this->actant->id();        
          $valueObject['property_id']=$this->p['creator']->id();
          $valueObject['type']='resource';    
          $oItem[$this->p['creator']->term()][] = $valueObject;      
        }

        //Body = doc cf. https://www.w3.org/TR/annotation-vocab/#hasselector
        $oItem['oa:hasBody'][] = $this->getBody($data);    

        //target = tag 
        $oItem['oa:hasTarget'][] = $this->getTarget($data);    

        //type omeka
        $oItem['o:resource_class'] = ['o:id' => $rcA->id()];
        $oItem['o:resource_template'] = ['o:id' => $rtA->id()];

        //création de l'annotation
        $result = $this->api->create('annotations', $oItem, [], ['continueOnError' => true])->getContent();        

        return $result;

    }    


    /**
     * creation du body pour une annotation
     *
     * @param  array  $data
     * 
     * @return array
     */
    protected function getBody($data)
    {

      $body = [];                
      $val = '';               
      //body = texte explicatif      
      if(isset($this->actant))$val = $this->actant->displayTitle().' a mis en rapport : ';
      if(isset($data['rapports']['jdc:hasDoc'])){
        foreach ($data['rapports']['jdc:hasDoc'] as $d) {
          if(!isset($d['item']))$d['item'] = $this->api->read($d['type'], $d['value'])->getContent();
          $val .= '"'.$d['item']->displayTitle().'" ';

        }
      }
      if(isset($data['rapports']['jdc:hasConcept'])){
        $val .= ' avec : ';
        $n = 0;
        foreach ($data['rapports']['jdc:hasConcept'] as $d) {
          if(!isset($d['item']))$d['item'] = $this->api->read($d['type'], $d['value'])->getContent();
          if($d['type']=='properties' || $d['type']=='resource_classes')
            $val .= '"'.$d['item']->label().'" ('.$data['rapports']['ma:hasRating'][$n].') ';
          else
            $val .= '"'.$d['item']->displayTitle().'" ('.$data['rapports']['ma:hasRating'][$n].') ';
          $n++;
        }
      }
      if(isset($data['rapports']['ma:hasRatingSystem'])){
        $val .= ' du crible ';
        foreach ($data['rapports']['ma:hasRatingSystem'] as $d) {
          if(!isset($d['item']))$d['item'] = $this->api->read($d['type'], $d['value'])->getContent();
          if($d['type']=='properties' || $d['type']=='resource_classes')
            $val .= '"'.$d['item']->label().'".';
          else
            $val .= '"'.$d['item']->displayTitle().'".';
        }
      }
      if($val){
        $body['rdf:value'][]=['@value'=> $val,'property_id'=>$this->p['value']->id(),'type'=>'literal'];      
      }

      if(isset($data['item'])){
        $body['rdf:value'][] = ['value_resource_id'=> $data['item']->id(),'property_id'=>$this->p['value']->id(),'type'=>'resource'];    
      }

      $body['oa:hasPurpose'][0]['@value']= isset($data['objectif']) ? $data['objectif'] : 'oa:editing';
      $body['oa:hasPurpose'][0]['property_id']=$this->p["hasPurpose"]->id();
      $body['oa:hasPurpose'][0]['type']='customvocab:'.$this->customVocab['Annotation Body oa:hasPurpose'];
      
      return $body;
    }

    /**
     * creation de la target pour une annotation
     *
     * @param  array  $data
     * @return array
     */
    protected function getTarget($data)
    {
      $target = [];                
      foreach ($data['rapports']['jdc:hasDoc'] as $d) {
        $valueObject = [];                
        $valueObject['property_id']=$this->p['hasSource']->id();
        $valueObject['type']='resource';
        $valueObject['value_resource_id']=$d['value'];
        $target[$this->p['hasSource']->term()][] = $valueObject;    
      }
      foreach ($data['rapports']['jdc:hasDoc'] as $d) {
        $valueObject = []; 
        $valueObject['property_id']=$this->p['hasSource']->id();
        $valueObject['type']='resource';
        $valueObject['value_resource_id']=$d['value'];  
        $body[$this->p['hasSource']->term()][] = $valueObject;
      }

      return $target;
    }

    /**
     * Cache custom vocab.
     */
    protected function cacheCustomVocab()
    {
        $arrRT = ["Annotation Target rdf:type","Annotation oa:motivatedBy","Annotation Body oa:hasPurpose"];
        foreach ($arrRT as $label) {
            $customVocab = $this->api->read('custom_vocabs', [
                'label' => $label,
            ], [], ['responseContent' => 'reference'])->getContent();
            $this->customVocab[$label]=$customVocab->id();
        }
    }

    /**
     * Cache properties.
     */
    protected function cacheProperties()
    {

      $this->p = [
        'creator' => $this->api->search('properties', ['term' => 'dcterms:creator'])->getContent()[0],
        'value' =>  $this->api->search('properties', ['term' => 'rdf:value'])->getContent()[0],
        'type' =>  $this->api->search('properties', ['term' => 'rdf:type'])->getContent()[0],
        'motivatedBy' =>  $this->api->search('properties', ['term' => 'oa:motivatedBy'])->getContent()[0],
        'hasSource' =>  $this->api->search('properties', ['term' => 'oa:hasSource'])->getContent()[0],
        'hasPurpose' =>  $this->api->search('properties', ['term' => 'oa:hasPurpose'])->getContent()[0],
        'semanticRelation' =>  $this->api->search('properties', ['term' => 'skos:semanticRelation'])->getContent()[0],          
      ];
    }

}