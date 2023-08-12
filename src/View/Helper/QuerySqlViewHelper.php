<?php
namespace CartoAffect\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class QuerySqlViewHelper extends AbstractHelper
{
    protected $api;
    protected $conn;

    public function __construct($api, $conn)
    {
      $this->api = $api;
      $this->conn = $conn;
    }

    /**
     * Execution de requêtes sql directement dans la base sql
     *
     * @param array     $params paramètre de l'action
     * @return array
     */
    public function __invoke($params=[])
    {
        if($params==[])return[];
        switch ($params['action']) {
            case 'statResourceTemplate':
                $result = $this->statResourceTemplate($params['id']);
                break;            
            case 'getDistinctPropertyVal':
                $result = $this->getDistinctPropertyVal($params['idRT'], $params['idP']);
                break;
            case 'statValueResourceClass':
                $result = $this->statValueResourceClass($params);
                break;
            case 'cooccurrenceValueResource':
                $result = $this->cooccurrenceValueResource($params);
                break;
            case 'statClassUsed':
                $result = $this->statClassUsed($params);
                break;
            case 'tagUses':
                $result = $this->tagUses($params);
                break;                
        }

        return $result;

    }

    /**
     * renvoie les statistiques d'utilisation des class
     * renvoie les usages d'un tag 
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function statClassUsed($params){
        $query ="SELECT 
                COUNT(v.id) nbVal,
                COUNT(DISTINCT v.resource_id) nbItem,
                COUNT(DISTINCT v.property_id) nbProp,
                rc.label
            FROM
                value v
                    LEFT JOIN
                resource r ON r.id = v.resource_id
                    LEFT JOIN
                resource_class rc ON rc.id = r.resource_class_id
            GROUP BY rc.label";
        $rs = $this->conn->fetchAll($query);
        return $rs;       
    }

    function tagUses($params){
        //récupère le descriptif des usages = le tag utilisé comme valueResource
        $query ="SELECT 
                r.id tagId,
                r.title tagTitle,
                p.local_name relation,
                v.resource_id useId,                
                rc.label useClass
            FROM
                resource r
                    INNER JOIN
                value v ON v.value_resource_id = r.id
                    INNER JOIN
                property p ON p.id = v.property_id
                    INNER JOIN
                resource rU ON rU.id = v.resource_id
                    INNER JOIN
                resource_class rc ON rc.id = rU.resource_class_id
            WHERE
                        r.title like '%".$params['search']."%' 
                        AND (p.local_name = 'hasConcept' OR p.local_name = 'semanticRelation') 
                    ";
                    //GROUP BY r.id, p.local_name ";
        $query .= " ORDER BY r.created";
        $rs = $this->conn->fetchAll($query);
        //détails les usages
        $tags = [];
        foreach ($rs as $i=>$r) {
            //récupère le détail de l'usage suivant sa class
            if(!isset($tags[$r['tagId']]))
                $tags[$r['tagId']]=['tagId'=>$r['tagId'],'tagTitle'=>$r['tagTitle'],'relations'=>[]];
            if(!isset($tags[$r['tagId']]['relations'][$r['useClass']]))
                $tags[$r['tagId']]['relations'][$r['useClass']]=[];
            switch ($r['useClass']) {
                case 'part of speech':
                    $tags[$r['tagId']]['relations'][$r['useClass']][]=$this->getDetailUsagePartOfSpeech($r['tagId'], $r['useId']);
                    break;
            }
        }
        return $tags;       
    }

    /**
     * renvoie le detail des usages pour part of speech
     *
     * @param int    $idT identifiant du tag
     * @param int    $idR identifiant de la ressource
     * @return array
     */
    function getDetailUsagePartOfSpeech($idT, $idR){
        $query ="SELECT idMin, idMax, nb, pId, pLabel, numVal
        , vStart.id, vStart.value start
        , vEnd.id, vEnd.value end
        , vConf.id, vConf.value confidence
        , vSpeak.id, vSpeak.value speaker
        FROM (
        SELECT 
            count(v.value_resource_id),
            count(v.value),
            min(v.id) idMin,
            max(v.id) idMax,
            max(v.id)-min(v.id) nb,
            v.property_id pId,
            p.label pLabel,
            min(vC.id) - min(v.id) numVal 
        FROM        
            value v 
            inner join property p on p.id = v.property_id
            left join value vC on vC.id = v.id and v.property_id = 2068 and v.value_resource_id = ".$idT."
        WHERE
            v.resource_id = ".$idR."
         group by v.resource_id,  v.property_id
         having nb > 1 and numVal > 0
         ) trans,
         (select id, value, property_id from value WHERE resource_id = ".$idR." and property_id = 208) vStart,
         (select id, value, property_id from value WHERE resource_id = ".$idR." and property_id = 189) vEnd,
         (select id, value, property_id from value WHERE resource_id = ".$idR." and property_id = 2043) vConf,
         (select id, value, property_id from value WHERE resource_id = ".$idR." and property_id = 2082) vSpeak
        WHERE 
        vStart.id = trans.idMin+(nb+1)+numVal
        AND vEnd.id = trans.idMin+((nb+1)*2)+numVal
        AND vConf.id = trans.idMin+((nb+1)*3)+numVal
        AND vSpeak.id = trans.idMin+((nb+1)*4)+numVal"; 
        $rs = $this->conn->fetchAll($query);
        return $rs;
    }

    
    


    /**
     * renvoie les statistiques d'une class comme valeur de ressource
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function statValueResourceClass($params){
        $oClass = $this->api->search('resource_classes', ['term' => $params['class']])->getContent()[0];
        $query ="SELECT 
            r.id,
            r.title,
            COUNT(v.id) nbValue,
            GROUP_CONCAT(DISTINCT p.local_name) props
        FROM
            resource r
                INNER JOIN
            value v ON v.value_resource_id = r.id
                INNER JOIN
            property p ON p.id = v.property_id
        WHERE
            resource_class_id = ?
        GROUP BY r.id ";
        $having = "";
        if($params['minVal'] && $params['maxVal'])$having = " HAVING nbValue BETWEEN ".$params['minVal']." AND ".$params['maxVal'];
        elseif($params['maxVal'])$having = " HAVING nbValue <= ".$params['maxVal'];
        elseif($params['minVal'])$having = " HAVING nbValue >= ".$params['minVal'];
        $query .= $having." ORDER BY r.created";
        $rs = $this->conn->fetchAll($query,[$oClass->id()]);
        return $rs;       
    }

    /**
     * renvoie les coocurrences de relation d'une ressource
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function cooccurrenceValueResource($params){
        $query ="SELECT 
                rlr.title, rlr.id, COUNT(vl.id) nbValue
                ,GROUP_CONCAT(DISTINCT v.resource_id) idsR
            FROM
                resource r
                    INNER JOIN
                value v ON v.value_resource_id = r.id
                    INNER JOIN
                property p ON p.id = v.property_id
                    INNER JOIN
                resource rl ON rl.id = v.resource_id
                    INNER JOIN
                value vl ON vl.resource_id = rl.id
                    INNER JOIN
                resource rlr ON rlr.id = vl.value_resource_id
            WHERE
                r.id = ?
            GROUP BY rlr.id
            ";
        $rs = $this->conn->fetchAll($query,[$params['id']]);
        return $rs;       
    }

    /**
     * renvoie les statistiques d'une resource template
     *
     * @param int     $id identifiant du resurce template
     * @return array
     */
    function statResourceTemplate($id){
           $query ="SELECT 
                    COUNT(DISTINCT r.id) nbRes,
                    p.local_name, p.label,
                    p.id pId,
                    COUNT(DISTINCT v.value) nbVal,
                    COUNT(DISTINCT v.value_resource_id) nbValRes
                FROM
                    resource r
                        INNER JOIN
                    value v ON v.resource_id = r.id
                        INNER JOIN
                    property p ON p.id = v.property_id
                WHERE
                    r.resource_template_id = ?
                GROUP BY v.property_id
                ORDER BY v.value
                ";
        $rs = $this->conn->fetchAll($query,[$id]);
        return $rs;       
    }

    /**
     * renvoie les valeurs distincts d'uen propriété d'un resource template
     *
     * @param int     $idRT identifiant du resurce template
     * @param int     $idP identifiant de le propriété
     * @return array
     */
    function getDistinctPropertyVal($idRT, $idP){
        $query ="SELECT 
                p.local_name,
                p.id,
                COUNT(DISTINCT r.id) nb,
                v.value,
                v.value_resource_id,
                rt.title
            FROM
                resource r
                    INNER JOIN
                value v ON v.resource_id = r.id
                    INNER JOIN
                property p ON p.id = v.property_id
                    LEFT JOIN
                resource rt ON rt.id = v.value_resource_id
            WHERE
                r.resource_template_id = ?
                    AND p.id = ?
            GROUP BY v.value , value_resource_id    
             ";
     $rs = $this->conn->fetchAll($query,[$idRT, $idP]);
     return $rs;       
    }

    /**
     * recherche sur le titre des resources liées à une propriété
     *
     * @param int     $idP identifiant de le propriété
     * @param string  $txt texte à rechercher dans le titre
     * @return array
     */
    /*
    function searchLinkedResourcesByTitle($idRT, $idP){
        $query ="SELECT 
                p.local_name,
                p.id,
                COUNT(DISTINCT r.id) nb,
                v.value,
                v.value_resource_id,
                rt.title
            FROM
                resource r
                    INNER JOIN
                value v ON v.resource_id = r.id
                    INNER JOIN
                property p ON p.id = v.property_id
                    LEFT JOIN
                resource rt ON rt.id = v.value_resource_id
            WHERE
                r.resource_template_id = ?
                    AND p.id = ?
            GROUP BY v.value , value_resource_id    
             ";
     $rs = $this->conn->fetchAll($query,[$idRT, $idP]);
     return $rs;       
    }
    */

}
