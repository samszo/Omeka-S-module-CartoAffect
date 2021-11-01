<?php
namespace CartoAffect\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class CribleViewHelper extends AbstractHelper
{
    protected $api;
    protected $conn;

    public function __construct($api, $conn)
    {
      $this->api = $api;
      $this->conn = $conn;
    }

    /**
     * Construction des cribles et des concepts.
     *
     * @param string    $nom nom du crible à récupérer
     * @param oItem     $crible item du crible
     * @param string    $action action à faire avec le crible
     * @param array     $params paramètre de l'action
     * @return array
     */
    public function __invoke($nom="", $crible=false, $action='infos', $params=[])
    {
        //récupère les propriétés
        $idClassCrible =  $this->api->search('resource_classes', ['term' => 'jdc:Crible'])->getContent()[0];        
        $this->inScheme = $this->api->search('properties', ['term' => 'skos:inScheme'])->getContent()[0];        
        $titre = $this->api->search('properties', ['term' => 'dcterms:title'])->getContent()[0];        
        if($nom){
            //récupère un crible
            $param = array();
            $param['resource_classe_id']= $idClassCrible;                               
            $param['property'][0]['property']= $titre->id()."";
            $param['property'][0]['type']='eq';
            $param['property'][0]['text']=$nom; 
            $cribles = $this->api->search('items',$param)->getContent();
        }elseif($crible){
            $cribles = [$crible];
        }else{
            //récupère la liste des cribles
            $cribles = $this->api->search('items',['resource_classe_id' => $idClassCrible])->getContent();
        }
        switch ($action) {
            case 'getProcessCribleValue':
                $result = $this->getProcessCribleValue($params);
                break;            
            case 'getCorCribleValue':
                $result = $this->getCorCribleValue($params);
                break;                                
            default:
                $result = $this->getInfosCribles($cribles);
                break;
        }

        return $result;

    }

    /**
     * renvoie les valeurs d'un crible par process pour un ressource template donnée
     *
     * @param array     $params paramètre de l'action
     * @return array
     */
    function getProcessCribleValue($params){
           $query ="SELECT 
            actionApplication,
            GROUP_CONCAT(cribleNom) cribles,
            GROUP_CONCAT(crible) criblesId,
            GROUP_CONCAT(rates) valeurs,
            GROUP_CONCAT(DISTINCT actant) actants,
            GROUP_CONCAT(DISTINCT doc) docs
        FROM
            (SELECT 
            r.id,
            vProcess.value_resource_id actionApplication,
            vCrible.value_resource_id crible,
            vCribleNom.value cribleNom,
            vRate.value rates,
            vActant.value_resource_id actant,
            GROUP_CONCAT(DISTINCT vDoc.value_resource_id) doc
        FROM
            resource r
                INNER JOIN
            value vProcess ON r.id = vProcess.resource_id
                AND vProcess.property_id = ?
                INNER JOIN
            value vCrible ON r.id = vCrible.resource_id
                AND vCrible.property_id = ?
                INNER JOIN
            value vCribleNom ON vCribleNom.resource_id = vCrible.value_resource_id
                AND vCribleNom.property_id = ?
                INNER JOIN
            value vRate ON r.id = vRate.resource_id
                AND vRate.property_id = ?
                INNER JOIN
            value vActant ON r.id = vActant.resource_id
                AND vActant.property_id = ?
                INNER JOIN
            value vDoc ON r.id = vDoc.resource_id
                AND vDoc.property_id = ?
                INNER JOIN
            resource rDoc ON rDoc.id = vDoc.value_resource_id
                AND rDoc.resource_type LIKE ? AND rDoc.id != ?
        WHERE
            r.resource_template_id = ?
        GROUP BY actionApplication , crible) cribleVals
        GROUP BY actionApplication";
        $rs = $this->conn->fetchAll($query,$params);
        //formate les données
        $result = [];
        foreach ($rs as $r) {
            $cribles = explode(",", $r['cribles']);
            $criblesId = explode(",", $r['criblesId']);
            $vals = explode(",", $r['valeurs']);
            $actants = explode(",", $r['actants']);
            $docs = explode(",", $r['docs']);
            $row = ['process'=>$r['actionApplication'],'docs'=>$docs,'actants'=>$actants];
            for ($i=0; $i < count($cribles); $i++) { 
                $row[$cribles[$i]]=$vals[$i];
                $row[$cribles[$i].'_id']=$criblesId[$i];
            }
            $result[]=$row;
        }
        return $result;       
    }


    /**
     * renvoie les valeurs des corrections d'un crible par process pour un ressource template donnée
     *
     * @param array     $params paramètre de l'action
     * @return array
     */
    function getCorCribleValue($params){
        $query = "SELECT 
        DISTINCT
        r.id,
        vProcess.value_resource_id actionApplication,
        vCrible.value_resource_id crible,
        vCribleNom.value cribleNom,
        vActant.value_resource_id actant,
        vDistCpt.value_resource_id distCpt,
        vDistCptNom.value distCptNom,
        vRate.value rates,
        vDoc.value_resource_id doc,
        vCpt.value_resource_id cpt,
        vCptNom.value cptNom
    FROM
        resource r
            INNER JOIN
        value vProcess ON r.id = vProcess.resource_id
            AND vProcess.property_id = ?
            INNER JOIN
        value vCrible ON r.id = vCrible.resource_id
            AND vCrible.property_id = ?
            INNER JOIN
        value vCribleNom ON vCribleNom.resource_id = vCrible.value_resource_id
            AND vCribleNom.property_id = ?
            INNER JOIN
        value vCpt ON r.id = vCpt.resource_id
            AND vCpt.property_id = ?
            INNER JOIN
        value vCptNom ON vCptNom.resource_id = vCpt.value_resource_id
            AND vCptNom.property_id = ?
            INNER JOIN
        value vRate ON r.id = vRate.resource_id
            AND vRate.property_id = ?
            INNER JOIN
        value vActant ON r.id = vActant.resource_id
            AND vActant.property_id = ?
            INNER JOIN
        value vDoc ON r.id = vDoc.resource_id
            AND vDoc.property_id = ?
            INNER JOIN
        value vDistCpt ON r.id = vDistCpt.resource_id
            AND vDistCpt.property_id = ?
            INNER JOIN
        value vDistCptNom ON vDistCptNom.resource_id = vDistCpt.value_resource_id
            AND vDistCptNom.property_id = ?
    WHERE
        r.resource_template_id = ?";
        $rs = $this->conn->fetchAll($query,$params);
        //formate les données
        $result = [];
        foreach ($rs as $r) {
            $row = ['process'=>$r['actionApplication'],'docs'=>$r['doc'],'actants'=>$r['actant']
                ,'cptOriId'=>$r['cpt'],'cptOri'=>$r['cptNom'],'cptOriVal'=>1//par defaut la valeur calculée est à 1
                ,'cptId'=>$r['distCpt'], 'cpt'=>$r['distCptNom'], 'cptVal'=>$r['rates']
            ];
            $result[]=$row;
        }
        return $result;       
    }


    function getInfosCribles($cribles){
        $result = [];
        foreach ($cribles as $c) {
            //récupère la liste des concepts
            $cpts = array();
            $param = array();
            $param['property'][0]['property']= $this->inScheme->id()."";
            $param['property'][0]['type']='res';
            $param['property'][0]['text']=$c->id()."";
            $param['sort_by']="jdc:ordreCrible";
            $param['sort_order']="asc";   
            $concepts = $this->api->search('items',$param)->getContent();
            foreach ($concepts as $cpt) {
                //TODO: rendre accessible la propriété concepts qui disparait lors du json encode
                //$c->concepts[]=$cpt;
                $cpts[] = $cpt;                
            }

            //récupère la définition des cartos
            $cartos = [];
            $hasCribleCarto = $c->value('jdc:hasCribleCarto',['all'=>true]);
            if($hasCribleCarto){
                foreach ($hasCribleCarto as $carto) {
                    $cartos[]= $carto->valueResource();
                }    
            }
            //récupère les cribles liés au crible
            $linkCribles = array();
            $hasCrible = $c->value('jdc:hasCrible',['all'=>true]);
            if($hasCrible){
                foreach ($hasCrible as $hc) {
                    $linkCribles[]= $this->getInfosCribles([$hc->valueResource()]);
                }    
            }
            $result[]=['item'=>$c,'concepts'=>$cpts,'cartos'=>$cartos,'cribles'=>$linkCribles];             
        }
        return count($result) == 1 ? $result[0] : $result;

    }
}
