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
            case 'statResUsed':
                $result = $this->statResUsed($params);
                break;
            case 'tagUses':
                $result = $this->tagUses($params);
                break;     
            case 'propValueResource':
                $result = $this->propValueResource($params);
                break;               
            case 'complexityNbValue':
                $result = $this->complexityNbValue($params);
                break;           
            case 'complexityUpdateValue':
                $result = $this->complexityUpdateValue($params);
                break;           
            case 'complexityInsertValue':
                $result = $this->complexityInsertValue($params);
                break;           
                        
        }

        return $result;

    }

    /**
     * mise à jour de la complexité 
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function complexityInsertValue($params){

        //création de l'annotation
        $query ="INSERT INTO `resource` (`owner_id`, `is_public`, `created`,`resource_type`) VALUES
        (?,1,NOW(),?)";
        $rs = $this->conn->executeStatement($query,[$params['vals']['owner'],'Omeka\Entity\ValueAnnotation']);
        $aId = $this->conn->lastInsertId();
        $query ="INSERT INTO `value_annotation` (`id`) VALUES (".$aId.")";
        $rs = $this->conn->executeStatement($query);

        //mise à jour de la resource
        $query ="UPDATE `resource` SET `modified`=NOW() WHERE id =".$params['vals']['id'];
        $rs = $this->conn->executeStatement($query);

        //création des nouvelles valeurs d'annotation
        $this->complexityInsertAnnotationValues($aId,$params['vals']);

        //création de la valeur de la ressource
        $query = "INSERT INTO `value` (`value`,`property_id`, `type`,`resource_id`,`is_public`,`value_annotation_id`)  VALUES
            (?, ?, 'literal', ?, 1, ?)";
        $rs = $this->conn->executeStatement($query,[$params['vals']['value'],$params['vals']['property_id'],$params['vals']['id'],$aId]);
        return $rs;       
    }
    /**
     * ajout de la complexité 
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function complexityUpdateValue($params){
        //récupère les identifiants
        $query ="SELECT id, value_annotation_id FROM value v WHERE v.property_id = ? AND v.resource_id = ?";
        $rs = $this->conn->fetchAll($query,[$params['vals']['property_id'],$params['vals']['id']]);
        $aId = $rs[0]['value_annotation_id'];
        $vId = $rs[0]['id'];

        //mise à jour les resources
        $query ="UPDATE `resource` SET `modified`=NOW() WHERE id IN (".$params['vals']['id'].",".$aId.")";
        $rs = $this->conn->executeStatement($query);

        //supression des valeurs de l'annotation
        $query ="DELETE FROM `value` WHERE resource_id = ".$aId;
        $rs = $this->conn->executeStatement($query);

        //création des nouvelles valeurs
        $this->complexityInsertAnnotationValues($aId,$params['vals']);

        //mise à jour de la valeur de la ressource
        $query ="UPDATE value v SET v.value = ? WHERE v.id = ?";
        $rs = $this->conn->executeStatement($query,[$params['vals']['value'],$vId]);
        return $rs;       
    }

    /**
     * ajout des annotation de la complexité 
     *
     * @param array    $vals paramètre de la requête
     * @return array
     */
    function complexityInsertAnnotationValues($id,$vals){

        $query = "INSERT INTO `value` (`value`, `property_id`, `type`, `resource_id`,`is_public`) VALUES (?, ?, ?, ?, 1)";
        foreach ($vals['@annotation'] as $a) {
            foreach ($a as $v) {
                $rs = $this->conn->executeStatement($query,
                    [$v['@value'],$v['property_id'],$v['type'],$id]);
            }
        }
    }


    /**
     * renvoie le nombre de ressource par complexité
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function complexityNbValue($params){
        $query ="    SELECT 
                CAST(v.value AS INTEGER) val, COUNT(*) nb
            FROM
                property p
                    INNER JOIN
                value v ON v.property_id = p.id
            WHERE
                p.local_name = 'complexity'
            GROUP BY v.value
            ORDER BY val DESC";
        $rs = $this->conn->fetchAll($query);
        return $rs;       
    }


    /**
     * renvoie les propriété utilisées pour les valeurs de ressource
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function propValueResource($params){
        $query ="SELECT 
                v.property_id,
                COUNT(v.id),
                CONCAT(vo.prefix, ':', p.local_name) prop
            FROM
                value v
                    INNER JOIN
                property p ON p.id = v.property_id
                    INNER JOIN
                vocabulary vo ON vo.id = p.vocabulary_id
            WHERE
                v.value_resource_id IS NOT NULL
            GROUP BY v.property_id
                ";
        $rs = $this->conn->fetchAll($query);
        return $rs;       
    }

    /**
     * renvoie les statistiques d'utilisation d'une ressource
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function statResUsed($params){
        //if(!$this->conn->isConnected())$this->conn->connect();

        //ATTENTION: on ne prend pas en compte toutes les ressources mais uniquement certains types
        $resourceTypes = ["Annotate\Entity\Annotation","Omeka\Entity\Item","Omeka\Entity\Media","Omeka\Entity\ItemSet"];
        $query ="SELECT 
                r.id,
                COUNT(v.id) nbVal,
                COUNT(v.property_id) nbProp,
                COUNT(DISTINCT r.owner_id) nbOwner,
                GROUP_CONCAT(DISTINCT r.owner_id) idsOwner,
                COUNT(v.uri) nbUri,
                GROUP_CONCAT(v.uri) uris,
                COUNT(v.value_resource_id) nbRes,
                GROUP_CONCAT(v.value_resource_id) idsRes,
                GROUP_CONCAT(CONCAT(vo.prefix, ':', p.local_name)) propsRes,
                COUNT(v.value_annotation_id) nbAno,
                COUNT(DISTINCT m.id) nbMedia,
                GROUP_CONCAT(DISTINCT m.id) idsMedia,
                r.resource_type,
                rc.label 'class label',
                rc.id 'idClass'
            FROM
                value v
                    INNER JOIN
                resource r ON r.id = v.resource_id
                    LEFT JOIN
                media m ON m.item_id = r.id
                    LEFT JOIN
                resource_class rc ON rc.id = r.resource_class_id
                    LEFT JOIN
                value vl ON vl.resource_id = v.resource_id
                    AND vl.value_resource_id = v.value_resource_id
                    LEFT JOIN
                property p ON p.id = vl.property_id
                    LEFT JOIN
                vocabulary vo ON vo.id = p.vocabulary_id
                ";
        if($params["id"]){
            $query .= " WHERE r.id = ?";
            $rs = $this->conn->fetchAll($query,[$params["id"]]);
        }elseif ($params["ids"]) {
            $query .= " WHERE r.id IN (";
            $query .= implode(',', array_fill(0, count($params['ids']), '?'));
            $query .= ")   
                GROUP BY r.id ";
            $rs = $this->conn->fetchAll($query,$params["ids"]);
        }elseif ($params['resource_types']){
            ini_set('memory_limit', '2048M');
            $query .= " WHERE r.resource_type IN (";
            $query .= implode(',', array_fill(0, count($params['resource_types']), '?'));
            $query .= ")  
                GROUP BY r.id ";
            $rs = $this->conn->fetchAll($query,$params['resource_types']);
        }elseif($params['vrid']){
            $query .= " WHERE v.value_resource_id = ? AND r.resource_type IN (";
            $query .= implode(',', array_fill(0, count($resourceTypes), '?'));
            $query .= ")  GROUP BY r.id";
            $rs = $this->conn->fetchAll($query,array_merge([$params["vrid"]], $resourceTypes));
        }else{
            ini_set('memory_limit', '2048M');
            $query .= " WHERE r.resource_type IN (?,?,?,?)  
                GROUP BY r.id ";
            $rs = $this->conn->fetchAll($query,$resourceTypes);
        }
        //$this->conn->close();
        return $rs;       
    }

    /**
     * renvoie les statistiques d'utilisation des class
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function statClassUsed($params){
        $query ='SELECT 
            COUNT(v.id) nbVal,
            COUNT(DISTINCT v.resource_id) nbItem,
            COUNT(DISTINCT v.property_id) nbProp,
            COUNT(DISTINCT r.owner_id) nbOwner,
            COUNT(DISTINCT v.uri) nbUri,
            COUNT(DISTINCT v.value_resource_id) nbRes,
            COUNT(DISTINCT v.value_annotation_id) nbAno,
            r.resource_type,
            rc.label "class label",
            rc.id
        FROM
            value v
                INNER JOIN
            resource r ON r.id = v.resource_id
                LEFT JOIN
            resource_class rc ON rc.id = r.resource_class_id
        WHERE
            r.resource_type IN (?,?,?,?)
        GROUP BY r.resource_type, rc.id, rc.id
        ';
        $rs = $this->conn->fetchAll($query,["Annotate\Entity\Annotation","Omeka\Entity\Item","Omeka\Entity\Media","Omeka\Entity\ItemSet"]);
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
