<?php
namespace CartoAffect\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class CartoHexaViewHelper extends AbstractHelper
{
    protected $api;
    protected $logger;
    protected $auth;

    public function __construct($arrS)
    {
      $this->api = $arrS['api'];
      $this->logger = $arrS['logger'];
      $this->auth = $arrS['auth'];
    }

    /**
     * gestion des cartographies hexagonales
     *
     * @param array     $params paramètres de la demande
     * @return array
     */
    public function __invoke($params=[])
    {
        if($params==[])return[];
        switch ($params['query']['action']) {
            case 'getAllConcept':
                $result = $this->getAllConcept($params);
                break;            
            case 'getCarte':
                $result = $this->getCarte($params['query']);
                break;            
            }

        return $result;

    }

    function getCarte($params){
        //récupère les données de la carte
        $carte = $this->api->read('items',$params['id'])->getContent();
        $tree=array("o:id"=>$carte->id(),"o:title"=>$carte->displayTitle()
            , "o:resource_class"=>$carte->resourceClass()->label(), "children"=>[]);

        $tree['children']=$this->getCrible($carte);
        return $tree;
    }

    function getCrible($r){

        //récupère les cribles de la ressource
        $cribles=[];
        $relations = $r->subjectValues();
        if(count($relations)){
            foreach ($relations as $k => $r) {
                foreach ($r as $v) {
                    $vr = $v['val']->resource();
                    if($vr->resourceClass()->label()=="Crible"){
                        $cpt = $vr->value('dcterms:type')->valueResource();
                        $crible = $vr->jsonSerialize();
                        $crible["value"]=1;
                        $crible["children"]=[];
                        $crible["concept"]=["o:id"=>$cpt->id(),"o:title"=>$cpt->displayTitle()];
                        $linkCrible = $vr->value('jdc:hasCrible',['all'=>true]);
                        foreach ($linkCrible as $lk) {
                            $rv = $lk->valueResource();
                            if($rv){
                                $crible['children']=$this->getCrible($rv);
                            }
                        }
                        $cribles[]=$crible;
                    }
                }
            }
        }
        return $cribles;
    }

    
    function getAllConcept($params){
        //récupère tous les concepts
        $data = $params['view']->QuerySqlFactory([
            'action'=>'statValueResourceClass','class'=>'skos:Concept'
            ,'minVal'=>$params['query']['minVal'] ? $params['query']['minVal'] : 0
            ,'maxVal'=>$params['query']['maxVal'] ? $params['query']['maxVal'] : 0
        ]);
        $rs=array("o:id"=>1,"o:title"=>"Carte des concepts", "o:resource_class"=>"jdc:Concept", "children"=>[]);
        foreach ($data as $d) {
            $rs["children"][]= array("o:id"=>$d['id'],"o:title"=>$d['title'],"value"=>$d['nbValue'], "children"=>[]);
        }
        return $rs;
    }

}
