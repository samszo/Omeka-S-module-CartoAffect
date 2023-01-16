<?php declare(strict_types=1);

namespace CartoAffect\View\Helper;

use Laminas\Authentication\AuthenticationService;
use Laminas\Log\Logger;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Manager as ApiManager;

class CartoHexa extends AbstractHelper
{
    /**
     * @var ApiManager
     */
    protected $api;

    /**
     * @var AuthenticationService
     */
    protected $auth;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(
        ApiManager $api,
        AuthenticationService $auth,
        Logger $logger
    ) {
        $this->api = $api;
        $this->auth = $auth;
        $this->logger = $logger;
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
        $data = $params['view']->querySql([
            'action' => 'statValueResourceClass',
            'class' => 'skos:Concept',
            'minVal' => $params['query']['minVal'] ? $params['query']['minVal'] : 0,
            'maxVal' => $params['query']['maxVal'] ? $params['query']['maxVal'] : 0,
        ]);
        $rs = [
            "o:id" => 1,
            'o:title' => 'Carte des concepts',
            'o:resource_class' => 'jdc:Concept',
            'children' => [],
        ];
        foreach ($data as $d) {
            $rs['children'][] = [
                'o:id' => $d['id'],
                'o:title' => $d['title'],
                'value' => $d['nbValue'],
                'children' => [],
            ];
        }
        return $rs;
    }
}
