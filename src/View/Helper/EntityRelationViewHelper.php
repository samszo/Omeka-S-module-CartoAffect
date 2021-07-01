<?php
namespace CartoAffect\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Exception;
use Omeka\Stdlib\ErrorStore;

class EntityRelationViewHelper extends AbstractHelper
{
    protected $api;
    protected $conn;

    public function __construct($api, $conn)
    {
      $this->api = $api;
      $this->conn = $conn;
    }

    /**
     * Gestion des diagrammes entités relation
     *
     * @param string    $action nom de l'action
     * @param array     $params paramètre de l'action
     * @return array
     */
    public function __invoke($action="",$params=[])
    {
        if(!$action || !$params)return [];

        switch ($action) {
            case 'updatePosition':
                $result = $this->updatePosition($params);
                break;            
        }

        return $result;

    }

    /**
     * mis à jour des positions de l'entité
     *
     * @param array     $params paramètre de l'action
     * @return array
     */
    function updatePosition($params){
        $errorStore = new ErrorStore;

        $result = [];
        foreach ($params as $key => $value) {
            if (is_array($value) && count($value) > 2) {
                $resource = $this->api->read('items', $value[0])->getContent();
                $currentData = json_decode(json_encode($resource), true);
                //construstion des valeurs
                $currentData['geom:coordX'][0]['@value']=$value[1];
                $currentData['geom:coordY'][0]['@value']=$value[2];

                // mise a jour x & y de id item
                try {
                    $this->api->update('items', $value[0], $currentData, []
                        , ['isPartial'=>true, 'continueOnError' => true, 'collectionAction' => 'replace']);
                    $result['success'][] = json_encode($currentData);
                } catch (Exception\ValidationException $e) {
                    $errorStore->mergeErrors($e->getErrorStore(), json_encode($currentData));
                }
            }
        }

        return $result;       
    }

}
