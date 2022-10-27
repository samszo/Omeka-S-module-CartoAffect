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
            case 'tagUses':
                $result = $this->cooccurrenceValueResource($params);
                break;                
            }

        return $result;

    }

    /**
     * renvoie les usages d'un tag 
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function tagUses($params){
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
        r.title like '%?%' 
         AND (p.local_name = 'hasConcept' OR p.local_name = 'semanticRelation') 
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
