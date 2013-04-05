<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Icu\IcuData;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\ResourceBundle\Transformer\BundleTransformer;
use Symfony\Component\Intl\ResourceBundle\Transformer\Rule\CurrencyBundleTransformationRule;
use Symfony\Component\Intl\ResourceBundle\Transformer\Rule\LanguageBundleTransformationRule;
use Symfony\Component\Intl\ResourceBundle\Transformer\Rule\LocaleBundleTransformationRule;
use Symfony\Component\Intl\ResourceBundle\Transformer\Rule\RegionBundleTransformationRule;
use Symfony\Component\Intl\ResourceBundle\Transformer\StubbingContext;

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/autoload.php';

if (1 !== $GLOBALS['argc']) {
    bailout(<<<MESSAGE
Usage: php copy-stubs-to-component.php

Copies stub files created with create-stubs.php to the Icu component.

For running this script, the intl extension must be loaded and all vendors
must have been installed through composer:

    composer install --dev

MESSAGE
    );
}

echo LINE;
echo centered("ICU Resource Bundle Stub Update") . "\n";
echo LINE;

if (!class_exists('\Symfony\Component\Icu\IcuData')) {
    bailout('You must run "composer update --dev" before running this script.');
}

$stubBranch = '1.0.x';

if (!IcuData::isStubbed()) {
    bailout("Please switch to the Icu component branch $stubBranch.");
}

$filesystem = new Filesystem();

$sourceDir = sys_get_temp_dir() . '/icu-stubs';
$targetDir = IcuData::getResourceDirectory();

if (!$filesystem->exists($sourceDir)) {
    bailout("The directory $sourceDir does not exist. Please run create-stubs.php first.");
}

$filesystem->remove($targetDir);

echo "Copying files from $sourceDir to $targetDir...\n";

$filesystem->mirror($sourceDir, $targetDir);


echo "Done.\n";
