<?php
namespace CartoAffect\View\Helper;

use Zend\View\Helper\AbstractHelper;

class CartoAffectViewHelper extends AbstractHelper
{

    protected $api;
    protected $customVocab;

    public function __construct($api)
    {
      $this->api = $api;
    }

    public function __invoke($data)
    {
      //récupère le concept
      if(isset($data['itemCpt']))$concept=$data['itemCpt'];
      else $concept = $this->api->read('items', $data['idCpt'])->getContent();

      //récupère l'actant
      //TODO:gérer la création d'un utilisateur anonyme
      if(!$data['user'])$data['user']=$this->api->read('users', ['email'=>'anonyme.cartoaffect@univ-paris8.fr'])->getContent();
      $userUri = 'http://'.$_SERVER['SERVER_NAME'].str_replace(['item',$concept->id()],['user',$data['user']->getId()],$concept->adminUrl());
      $actant = $this->ajouteActant($data['user'],$userUri);

      //récupère la position sémantique
      $position = false;
      if(isset($data['rapports'])){
          //traitement suivant le ressource template
          switch ($data['idRt']) {
            case $data['themeSetting']['rt_idRapport']:
              $position = $this->ajouteSemanticPosition($actant, $concept, $data);        
              break;
            case $data['themeSetting']['rt_idSonar']:
              $position = $this->ajouteSonarPosition($actant, $concept, $data);        
            break;
          }        
      }
      //$anno = $this->ajouteAnnotation($concept, $this->actant, $dst);
      return ['actant'=>$actant, 'position'=>$position];
    }


     /** Ajoute une position sémantique SONAR
     *
     * @param o:item  $this->actant
     * @param o:item  $concept
     * @param array   $data
     * @return o:item
     */
    protected function ajouteSonarPosition ($actant, $concept, $data)
    {

      $ref = "SonarPosition:".$concept->id()."-".$actant->id();
      $rt =  $this->api->read('resource_templates', $data['idRt'])->getContent();
      $rc =  $this->api->search('resource_classes', ['term' => 'jdc:SemanticPosition'])->getContent()[0];
      //pas de mise à jour des positions
      $oItem = [];
      $oItem['o:resource_class'] = ['o:id' => $rc->id()];
      $oItem['o:resource_template'] = ['o:id' => $rt->id()];
      foreach ($rt->resourceTemplateProperties() as $p) {
        $oP = $p->property();
        if(isset($data['rapports'][$oP->term()])){
          $val = $data['rapports'][$oP->term()];
          if(is_string($val))$val=[$val];
          foreach ($val as $v) {
            if(!is_string($v) && $v['type']=='resource'){
              $valueObject = [];
              $valueObject['value_resource_id']=$v['value'];        
              $valueObject['property_id']=$oP->id();
              $valueObject['type']='resource';    
              $oItem[$oP->term()][] = $valueObject;    
            }else{
              $valueObject = [];
              $valueObject['@value'] = $v;
              $valueObject['type'] = 'literal';
              $valueObject['property_id']=$oP->id();
              $oItem[$oP->term()][] = $valueObject; 
            } 
          }  
        }
      }
      $result = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
      
      return $result;      

    }


     /** Ajoute une position sémantique
     *
     * @param o:item  $this->actant
     * @param o:item  $concept
     * @param array   $rapports
     * @return o:item
     */
    protected function ajouteSemanticPosition($actant, $concept, $data)
    {

      $ref = "SemanticPosition:".$concept->id()."-".$actant->id();
      $rt =  $this->api->read('resource_templates', $data['idRt'])->getContent();
      $rc =  $this->api->search('resource_classes', ['term' => 'jdc:SemanticPosition'])->getContent()[0];
      $pIRB =  $this->api->search('properties', ['term' => 'dcterms:isReferencedBy',])->getContent()[0];
      //création ou mise à jour
      $param = array();
      $param['property'][0]['property']= $pIRB->id()."";
      $param['property'][0]['type']='eq';
      $param['property'][0]['text']=$ref; 
      $existe = $this->api->search('items',$param)->getContent();
      $bExiste = count($existe);
        $oItem = [];
        if(!$bExiste){
          $oItem['o:resource_class'] = ['o:id' => $rc->id()];
          $oItem['o:resource_template'] = ['o:id' => $rt->id()];
        foreach ($rt->resourceTemplateProperties() as $p) {
          $oP = $p->property();
          switch ($oP->term()) {
            case 'dcterms:isReferencedBy':
              $valueObject = [];
              $valueObject['@value'] = $ref;
              $valueObject['type'] = 'literal';
              $valueObject['property_id']=$oP->id();
              $oItem[$oP->term()][] = $valueObject; 
              break;            
            case 'dcterms:title':
              $valueObject = [];
              $valueObject['@value'] = "Position sémantique de « ".$concept->displayTitle()." » par ".$actant->displayTitle();
              $valueObject['type'] = 'literal';
              $valueObject['property_id']=$oP->id();
              $oItem[$oP->term()][] = $valueObject;    
              break;            
            case 'dcterms:creator':
              $valueObject = [];
              $valueObject['value_resource_id']=$actant->id();        
              $valueObject['property_id']=$oP->id();
              $valueObject['type']='resource';    
              $oItem[$oP->term()][] = $valueObject;    
              break;            
            case 'oa:hasSource':
              $valueObject = [];
              $valueObject['value_resource_id']=$concept->id();        
              $valueObject['property_id']=$oP->id();
              $valueObject['type']='resource';    
              $oItem[$oP->term()][] = $valueObject;    
              break;     
            default:

          }
        }
        $result = $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
      }
      //ajoute ou modifie les rapports au propriété
      foreach ($data['rapports'] as $r) {
        $oItem = [];
        $oP =  $this->api->search('properties', ['term' => $r['rapport']['term']])->getContent()[0];
        $valueObject = [];
        $valueObject['value_resource_id']=$r['id'];        
        $valueObject['property_id']=$oP->id();
        $valueObject['type']='resource';    
        $oItem[$oP->term()][] = $valueObject; 
        $itemId = $bExiste ? $existe[0]->id() : $result->id();
        $rslt = $this->api->update('items', $itemId, $oItem, [], ['continueOnError' => true,'isPartial'=>1, 'collectionAction' => $r['rapport']['action']]);
        unset($oItem[$oP->term()]);
      }

      return $rslt;

    }

     /** Ajoute un actant
     *
     * @param object $user
     * @param string $urlAdmin
     * @return o:item
     */
    protected function ajouteActant($user, $urlAdmin)
    {

        //vérifie la présence de l'item pour gérer les mises à jour
        $foafAN =  $this->api->search('properties', ['term' => 'foaf:accountName'])->getContent()[0];

        $rt =  $this->api->search('resource_templates', ['label' => 'Actant',])->getContent()[0];
        $rc =  $this->api->search('resource_classes', ['term' => 'jdc:Actant',])->getContent()[0];
        $foafA =  $this->api->search('properties', ['term' => 'foaf:account',])->getContent()[0];
        $ident =  $this->api->search('properties', ['term' => 'schema:identifier',])->getContent()[0];

        //création de l'item
        $oItem = [];
        $valueObject = [];
        $valueObject['property_id'] = $foafA->id();
        $valueObject['@value'] = "CartoAffect";
        $valueObject['type'] = 'literal';
        $oItem[$foafA->term()][] = $valueObject;    
        $valueObject = [];
        $valueObject['property_id'] = $ident->id();
        $valueObject['@id'] = $urlAdmin;
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
     * @param  o:item $src
     * @param  o:item $actant
     * @param  o:item $dst
     * @return array
     */
    protected function ajouteAnnotation($src, $actant, $dst)
    {
        $ref = "idSrc:".$src->id()
        ."_idActant:".$actant->id()
        ."_idDst:".$dst->id();

        //récupère les propriétés
        $this->cacheCustomVocab();      
        $rtA =  $this->api->search('resource_templates', ['label' => 'Annotation'])->getContent()[0];
        $rcA =  $this->api->search('resource_classes', ['term' => 'oa:Annotation'])->getContent()[0];
        $p = [
          'isReferencedBy' => $this->api->search('properties', ['term' => 'dcterms:isReferencedBy'])->getContent()[0],
          'creator' => $this->api->search('properties', ['term' => 'dcterms:creator'])->getContent()[0],
          'value' =>  $this->api->search('properties', ['term' => 'rdf:value'])->getContent()[0],
          'type' =>  $this->api->search('properties', ['term' => 'rdf:type'])->getContent()[0],
          'motivatedBy' =>  $this->api->search('properties', ['term' => 'oa:motivatedBy'])->getContent()[0],
          'hasSource' =>  $this->api->search('properties', ['term' => 'oa:hasSource'])->getContent()[0],
          'hasPurpose' =>  $this->api->search('properties', ['term' => 'oa:hasPurpose'])->getContent()[0],
          'semanticRelation' =>  $this->api->search('properties', ['term' => 'skos:semanticRelation'])->getContent()[0],          
        ];
        
        //vérifie la présence de l'item pour gérer la création ou la mise à jour
        $param = array();
        $param['property'][0]['property']= $p['isReferencedBy']->id()."";
        $param['property'][0]['type']='eq';
        $param['property'][0]['text']=$ref; 
        $result = $this->api->search('annotations',$param)->getContent();

        $update = false;
        if(count($result)){
            $update = true;
            $idAno = $result[0]->id();
        }            

        //création de l'annotation       
        $oItem = [];

        //référence
        $valueObject = [];
        $valueObject['property_id'] = $p['isReferencedBy']->id();
        $valueObject['@value'] = $ref;
        $valueObject['type'] = 'literal';
        $oItem[$p['isReferencedBy']->term()][] = $valueObject;    

        //motivation
        $valueObject = [];
        $valueObject['property_id'] = $p['motivatedBy']->id();
        $valueObject['@value'] = 'tagging';
        $valueObject['type'] = 'customvocab:'.$this->customVocab['Annotation oa:motivatedBy'];
        $oItem[$p['motivatedBy']->term()][] = $valueObject;    

        //annotator = actant
        $valueObject = [];                
        $valueObject['value_resource_id']=$actant->id();        
        $valueObject['property_id']=$p['creator']->id();
        $valueObject['type']='resource';    
        $oItem[$p['creator']->term()][] = $valueObject;    

        //source = doc 
        $valueObject = [];                
        $valueObject['property_id']=$p['hasSource']->id();
        $valueObject['type']='resource';
        $valueObject['value_resource_id']=$src->id();
        $oItem[$p['hasSource']->term()][] = $valueObject;    

        //body = texte explicatif
        $valueObject = [];                
        $valueObject['rdf:value'][0]['@value']=$actant->displayTitle()
            .' a mis en rapport le concept '.$src->displayTitle()
            .' avec le rapport sémantique '.$dst->displayTitle();        
        $valueObject['rdf:value'][0]['property_id']=$p['value']->id();
        $valueObject['rdf:value'][0]['type']='literal';    
        $valueObject['oa:hasPurpose'][0]['@value']='classifying';
        $valueObject['oa:hasPurpose'][0]['property_id']=$this->properties["oa"]["hasPurpose"]->id();
        $valueObject['oa:hasPurpose'][0]['type']='customvocab:'.$this->customVocab['Annotation Body oa:hasPurpose'];
        $oItem['oa:hasBody'][] = $valueObject;    

        //target = tag 
        $valueObject = [];                
        $valueObject['rdf:value'][0]['value_resource_id']=$dst->id();        
        $valueObject['rdf:value'][0]['property_id']=$p['value']->id();
        $valueObject['rdf:value'][0]['type']='resource';    
        $valueObject['rdf:type'][0]['@value']='o:Item';        
        $valueObject['rdf:type'][0]['property_id']=$p["type"]->id();
        $valueObject['rdf:type'][0]['type']='customvocab:'.$this->customVocab['Annotation Target rdf:type'];            
        $oItem['oa:hasTarget'][] = $valueObject;    

        //type omeka
        $oItem['o:resource_class'] = ['o:id' => $rcA->id()];
        $oItem['o:resource_template'] = ['o:id' => $rtA->id()];

        if($update){
            $result = $this->api->update('annotations', $idAno, $oItem, []
                , ['isPartial'=>true, 'continueOnError' => true]);
        }else{
            //création de l'annotation
            $result = $this->api->create('annotations', $oItem, [], ['continueOnError' => true])->getContent();        

        }        
        //met à jour l'item avec la relation
        $param = [];
        $valueObject = [];
        $valueObject['property_id'] = $p["semanticRelation"]->id();
        $valueObject['value_resource_id'] = $dst->id();
        $valueObject['type'] = 'resource';
        $param[$p["semanticRelation"]->term()][] = $valueObject;
        $this->api->update('items', $src->id(), $param, []
            , ['isPartial'=>true, 'continueOnError' => true, 'collectionAction' => 'append']);

        return $result;

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


}