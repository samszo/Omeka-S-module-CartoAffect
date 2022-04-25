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
        }

        return $result;

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
