<?php declare(strict_types=1);
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
    public function __invoke($params = [])
    {
        if ($params == []) {
            return[];
        }
        switch ($params['query']['action']) {
            case 'getAllConcept':
                $result = $this->getAllConcept($params);
                break;
        }

        return $result;
    }

    public function getAllConcept($params)
    {
        //récupère tous les concepts
        $data = $params['view']->QuerySqlFactory([
            'action' => 'statValueResourceClass','class' => 'skos:Concept'
            ,'minVal' => $params['query']['minVal'] ? $params['query']['minVal'] : 0
            ,'maxVal' => $params['query']['maxVal'] ? $params['query']['maxVal'] : 0,
        ]);
        $rs = ["o:id" => 1,"o:title" => "Carte des concepts", "o:resource_class" => "jdc:Concept", "children" => []];
        foreach ($data as $d) {
            $rs["children"][] = ["o:id" => $d['id'],"o:title" => $d['title'],"value" => $d['nbValue'], "children" => []];
        }
        return $rs;
    }
}
