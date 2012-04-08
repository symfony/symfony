#!/usr/bin/env php
<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*

CAUTION: This file installs the dependencies needed to run the Symfony2 test suite.
If you want to create a new project, download the Symfony Standard Edition instead:

http://symfony.com/download

*/

set_time_limit(0);

if (!is_dir($vendorDir = dirname(__FILE__).'/vendor')) {
    mkdir($vendorDir, 0777, true);
}

// optional transport change
$transport = false;
if (isset($argv[1]) && in_array($argv[1], array('--transport=http', '--transport=https', '--transport=git'))) {
    $transport = preg_replace('/^--transport=(.*)$/', '$1', $argv[1]);
}

$deps = array(
    array('doctrine', 'http://github.com/doctrine/doctrine2.git', 'origin/master'),
    array('doctrine-dbal', 'http://github.com/doctrine/dbal.git', 'origin/master'),
    array('doctrine-fixtures', 'https://github.com/doctrine/data-fixtures.git', 'origin/master'),
    array('doctrine-common', 'http://github.com/doctrine/common.git', 'origin/master'),
    array('twig', 'http://github.com/fabpot/Twig.git', 'origin/master'),
    array('propel', 'http://github.com/propelorm/Propel.git', 'origin/master'),
    array('monolog', 'https://github.com/Seldaek/monolog.git', 'origin/master'),
);

foreach ($deps as $dep) {
    list($name, $url, $rev) = $dep;

    if ($transport) {
        $url = preg_replace('/^(http:|https:|git:)(.*)/', $transport . ':$2', $url);
    }

    $installDir = $vendorDir.'/'.$name;
    $install = false;
    if (!is_dir($installDir)) {
        $install = true;
        echo "> Installing $name\n";

        system(sprintf('git clone %s %s', escapeshellarg($url), escapeshellarg($installDir)));
    }

    if (!$install) {
        echo "> Updating $name\n";
    }

    system(sprintf('cd %s && git fetch origin && git reset --hard %s', escapeshellarg($installDir), escapeshellarg($rev)));
}
