<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Icu\IcuData;
use Symfony\Component\Intl\ResourceBundle\Reader\BinaryBundleReader;

require_once __DIR__.'/../common.php';
require_once __DIR__.'/../autoload.php';

$reader = new BinaryBundleReader();

$reader->read(IcuData::getResourceDirectory().'/curr', 'en');
$reader->read(IcuData::getResourceDirectory().'/lang', 'en');
$reader->read(IcuData::getResourceDirectory().'/locales', 'en');
$reader->read(IcuData::getResourceDirectory().'/region', 'en');
