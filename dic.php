<?php

require_once __DIR__ . '/vendor/autoload.php';

$dic['oxid.database'] = function ($dic) {
    return oxDB::getInstance();
};

$dic['makaira.connect.repository.product'] = function ($dic) {
    return new Makaira\Connect\Repository\Product(
        $dic['oxid.database'],
        $dic->getTagged('makaira.importer.modifier.product')
    );
};
