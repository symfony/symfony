<?php

use Symfony\Component\Icu\IcuData;
use Symfony\Component\Intl\ResourceBundle\Reader\BinaryBundleReader;

require_once __DIR__ . '/../common.php';
require_once __DIR__ . '/../autoload.php';

$reader = new BinaryBundleReader();

$reader->read(IcuData::getResourceDirectory() . '/curr', 'en');
$reader->read(IcuData::getResourceDirectory() . '/lang', 'en');
$reader->read(IcuData::getResourceDirectory() . '/locales', 'en');
$reader->read(IcuData::getResourceDirectory() . '/region', 'en');
