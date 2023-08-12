<?php
namespace CartoAffect\View\Helper;

use Google\Cloud\Speech\V1\RecognitionAudio;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\SpeakerDiarizationConfig;
use Google\Cloud\Speech\V1\RecognitionConfig\AudioEncoding;
use Google\Cloud\Speech\V1\SpeechClient;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Exception\RuntimeException;
use DateTime;
use ValueSuggest\DataType\Dc\Elements;

class GoogleViewHelper extends AbstractHelper
{
    protected $api;
    protected $acl;
    protected $config;
    protected $credentials;
    protected $props;
    protected $rcs;
    protected $rts;

    public function __construct($api,$acl,$config,$logger)
    {
      $this->api = $api;
      $this->acl = $acl;
      $this->config = $config;
      $this->credentials = json_decode(file_get_contents(OMEKA_PATH . '/modules/CartoAffect/config/code_secret_client.json'), true);
      $this->logger = $logger;

      require_once OMEKA_PATH . '/modules/CartoAffect/vendor/autoload.php';

    }

    /**
     * gestion des appels aux API de Google
     * 
     * @param Omeka\View\Helper\Params     $params
     *
     * @return json
     */
    public function __invoke($params)
    {
        if(is_array($params))$query = $params;
        else{
            $query = $params->fromQuery();
            $post = $params->fromPost();    
        }
        switch ($query['service']) {
            case 'speachToText':
                $result = $this->speachToText($query);
                break;            
            case 'getHistoTags':
                $result = $this->getHistoTags($query);
                break;            
            default:
                $result = [];
                break;
        }
        return $result;
    }

    /**
     * Récupère l'historique des tags d'une transcription
     *
     * @param array $params
     * @return array
     */
    protected function getHistoTags($params)
    {   
        $result = [];
        $item = !is_object($params['item']) ? $this->api->read('items',$params['item'])->getContent() : $params['item']; 
        //récupère les transcription de l'item
        $param = array();
        $param['resource_classe_id']= $this->getRc('lexinfo:PartOfSpeech');                               
        $param['property'][0]['property']= $this->getProp("oa:hasSource")->id()."";
        $param['property'][0]['type']='res';
        $param['property'][0]['text']=$item->id().""; 
        $trans = $this->api->search('items',$param)->getContent();
        foreach ($trans as $t) {
            //récupère les infos
            $arrMC = $t->value('jdc:hasConcept', ['all' => true]);
            $arrConf = $t->value('lexinfo:confidence', ['all' => true]);
            $arrStart = $t->value('oa:start', ['all' => true]);
            $arrEnd = $t->value('oa:end', ['all' => true]);
            $arrSpeaker = $t->value('dbo:speaker', ['all' => true]);
            $audio = $t->media()[0];
            $video = $audio->value('ma:isFragmentOf')->valueResource();
            $gStart = intval($audio->value('oa:start')->__toString());
            $gEnd = intval($audio->value('oa:end')->__toString());
            $nb = count($arrMC); 
            for ($i=0; $i < $nb; $i++) { 
                $itemMC = $arrMC->valueResource();
                if(!isset($result[$itemMC->id()]))
                    $result[$itemMC->id()]=array('title'=>$itemMC->displayTitle(),'uses'=>[]); 
                $result[$itemMC->id()]['uses'][]=array(
                    'a'=>$audio->id()
                    ,'v'=>$video->id()
                    ,'s'=>$arrStart[$i]->__toString()
                    ,'e'=>$arrEnd[$i]->__toString()
                    ,'gs'=>intval($arrStart[$i]->__toString())+$gStart
                    ,'ge'=>intval($arrEnd[$i]->__toString())+$gEnd
                    ,'c'=>$arrConf[$i]->__toString()
                    ,'sp'=>$arrSpeaker[$i]->__toString()
                );
            }
        }

    }



    /**
     * extraction du text à partir d'un fichier audio
     * 
     * @param   array   $params
     *
     * @return array
     */
    function speachToText($params){
        $rs = $this->acl->userIsAllowed(null,'create');
        if($rs){
            set_time_limit(0);            
            $result = [];
            $item = !is_object($params['frag']) ? $this->api->read('items',$params['frag'])->getContent() : $params['frag']; 
            $medias = $item->media();
            foreach ($medias as $media) {
                if($media->mediaType()=="audio/flac"){
                    //vérifi si le part of speech est présent
                    $param = array();
                    $param['resource_classe_id']= $this->getRc('lexinfo:PartOfSpeech');                               
                    $param['property'][0]['property']= $this->getProp("oa:hasSource")->id()."";
                    $param['property'][0]['type']='res';
                    $param['property'][0]['text']=$media->id().""; 
                    $exist = $this->api->search('items',$param)->getContent();
                    if(count($exist)){
                        $result[]= $exist[0];
                    }else{
            
                        //ATTENTION problème de dns sur le serveur paris 8
                        $oriUrl = str_replace('https://arcanes.univ-paris8.fr','http://192.168.30.208',$media->originalUrl());
                        $this->logger->info("speachToText : originalUrl = ".$oriUrl);

                        $audioResource = file_get_contents($oriUrl);

                        $speechClient=new SpeechClient(['credentials'=>$this->credentials]);            
                        //$encoding = AudioEncoding::OGG_OPUS;
                        //$sampleRateHertz = 24000;
                        //$sampleRateHertz = 44100;
                        $encoding = AudioEncoding::FLAC;
                        $languageCode = 'fr-FR';
                    
                        $audio = (new RecognitionAudio())
                            ->setContent($audioResource);

                        $config = (new RecognitionConfig())
                            ->setEncoding($encoding)
                            ->setEnableWordTimeOffsets(true)
                            ->setEnableWordConfidence(true)
                            /*différent suivant le media d'où vient le fragment
                            ->setAudioChannelCount(2)
                            ->setSampleRateHertz($sampleRateHertz)
                            ->setDiarizationConfig(
                                new SpeakerDiarizationConfig(['enable_speaker_diarization'=>true,'min_speaker_count'=>1,'max_speaker_count'=>10])
                                )
                            */
                            ->setLanguageCode($languageCode);
                            
                        $response = $speechClient->recognize($config, $audio);
                        foreach ($response->getResults() as $r) {
                            //ajoute la transcription
                            $t = $this->addTranscription($r->getAlternatives()[0], $item, $media);
                            $result[]=$t->id();
                        }
                    }
                }
            }            
            if($speechClient)$speechClient->close();            
            return $result;               
        }else return ['error'=>"droits insuffisants",'message'=>"Vous n'avez pas le droit d'exécuter cette fonction'."];
    }
  
    /**
     * ajoute une transcription
     * 
     * @param   SpeechRecognitionAlternative    $alt
     * @param   o:Item                          $item
     * @param   o:Media                         $media
     *
     * @return o:Item
     */
    function addTranscription($alt, $item, $media){
        $rt =  $this->getRt('Transcription');
        $oItem = [];
        $oItem['o:resource_class'] = ['o:id' => $rt->resourceClass()->id()];
        $oItem['o:resource_template'] = ['o:id' => $rt->id()];
        $oItem['dcterms:title'][]=[
            'property_id' => $this->getProp('dcterms:title')->id()
            ,'@value' => $alt->getTranscript() ,'type' => 'literal'
        ];
        $oItem['oa:hasSource'][]=[
            'property_id' => $this->getProp('oa:hasSource')->id()
            ,'value_resource_id' => $media->id() ,'type' => 'resource'
        ];
        $oItem['oa:hasSource'][]=[
            'property_id' => $this->getProp('oa:hasSource')->id()
            ,'value_resource_id' => $item->id() ,'type' => 'resource'
        ];
        $words = $alt->getWords();
        foreach ($words as $w) {
            $t = $this->getTag($w->getWord());
            $oItem['jdc:hasConcept'][]=[
                'property_id' => $this->getProp('jdc:hasConcept')->id()
                ,'value_resource_id' => $t->id() ,'type' => 'resource'
            ];
            $oItem['oa:start'][]=[
                'property_id' => $this->getProp('oa:start')->id()
                ,'@value' => $w->getStartTime()->getSeconds().":".$w->getStartTime()->getNanos() ,'type' => 'literal'
            ];
            $oItem['oa:end'][]=[
                'property_id' => $this->getProp('oa:end')->id()
                ,'@value' => $w->getEndTime()->getSeconds().":".$w->getEndTime()->getNanos() ,'type' => 'literal'
            ];
            $oItem['lexinfo:confidence'][]=[
                'property_id' => $this->getProp('lexinfo:confidence')->id()
                ,'@value' => $w->getConfidence()."" ,'type' => 'literal'
            ];
            $oItem['dbo:speaker'][]=[
                'property_id' => $this->getProp('dbo:speaker')->id()
                ,'@value' => $w->getSpeakerTag()."" ,'type' => 'literal'
            ];
        }
        /*NON car trop gourmant
        le lien se fais avec les mart of speech liéé
        //modifie la source
        $dataUpdate = json_decode(json_encode($item), true);
        foreach ($oItem['jdc:hasConcept'] as $c) {
            $dataUpdate['jdc:hasConcept'][]=$c;        
        }
        $this->api->update('items', $item->id(),$dataUpdate, [], ['continueOnError' => true,'isPartial'=>1, 'collectionAction' => 'replace']);    
        */
        return $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();

    }


    /**
     * Récupère le tag au format skos
     *
     * @param array $tag
     * @return o:Item
     */
    protected function getTag($tag)
    {
        //vérifie la présence de l'item pour gérer la création
        $param = array();
        $param['property'][0]['property']= $this->getProp("skos:prefLabel")->id()."";
        $param['property'][0]['type']='eq';
        $param['property'][0]['text']=$tag; 
        $result = $this->api->search('items',$param)->getContent();
        if(count($result)){
            return $result[0];
        }else{
            $oItem = [];
            $class = $this->getRc('skos:Concept');
            $oItem['o:resource_class'] = ['o:id' => $class->id()];
            $valueObject = [];
            $valueObject['property_id'] = $this->getProp("dcterms:title")->id();
            $valueObject['@value'] = $tag;
            $valueObject['type'] = 'literal';
            $oItem["dcterms:title"][] = $valueObject;
            $valueObject = [];
            $valueObject['property_id'] = $this->getProp("skos:prefLabel")->id();
            $valueObject['@value'] = $tag;
            $valueObject['type'] = 'literal';
            $oItem["skos:prefLabel"][] = $valueObject;
            //création du tag
            return $this->api->create('items', $oItem, [], ['continueOnError' => true])->getContent();
        }
    }
        

    function getProp($p){
        if(!isset($this->props[$p]))
          $this->props[$p]=$this->api->search('properties', ['term' => $p])->getContent()[0];
        return $this->props[$p];
      }
  
    function getRc($t){
        if(!isset($this->rcs[$t]))
            $this->rcs[$t] = $this->api->search('resource_classes', ['term' => $t])->getContent()[0];
        return $this->rcs[$t];
    }
    function getRt($l){
        if(!isset($this->rts[$l]))
            $this->rts[$l] = $this->api->read('resource_templates', ['label' => $l])->getContent();
        return $this->rts[$l];
    }

}
