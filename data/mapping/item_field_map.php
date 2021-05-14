<?php
// Warning: the mapping is not one-to-one, so some data may be lost when the
// mapping is reverted. You may adapt it to your needs.

return [
    'url'               => 'bibo:uri',
    'desc'              => 'dcterms:description',
    'titre'             => 'dcterms:title',
    'ivml:title'             => 'dcterms:title',
    'ivml:dateCreated'        => 'dcterms:created',
    'ivml:dateModified'        => 'dcterms:dateSubmitted',
    'ivml:author'              => 'dcterms:creator',
    'ivml:application'              => 'schema:application',
    'id'                 => 'dcterms:isReferencedBy',
    'semanticRelation'  => 'skos:semanticRelation',
    'width'             => 'ma:frameWidth',
    'height'            => 'ma:frameHeight',
    'x'                 => 'geom:coordX',
    'y'                 => 'geom:coordY',
    //'label'         => 'dcterms:title',
    'style'             => 'oa:styleClass',
    'from'         => 'ma:hasSource',
    'to'         => 'ma:isSourceOf',
    'type'         => 'dcterms:type',

];
