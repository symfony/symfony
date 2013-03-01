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
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\ResourceBundle\Compiler\BundleCompiler;
use Symfony\Component\Intl\ResourceBundle\Transformer\BundleTransformer;
use Symfony\Component\Intl\ResourceBundle\Transformer\CompilationContext;
use Symfony\Component\Intl\ResourceBundle\Transformer\Rule\CurrencyBundleTransformationRule;
use Symfony\Component\Intl\ResourceBundle\Transformer\Rule\LanguageBundleTransformationRule;
use Symfony\Component\Intl\ResourceBundle\Transformer\Rule\LocaleBundleTransformationRule;
use Symfony\Component\Intl\ResourceBundle\Transformer\Rule\RegionBundleTransformationRule;
use Symfony\Component\Intl\Util\SvnRepository;
use Symfony\Component\Filesystem\Filesystem;

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/autoload.php';

if (1 !== $GLOBALS['argc']) {
    bailout(<<<MESSAGE
Usage: php update-icu-component.php

Updates the ICU data for Symfony2 to the latest version of the ICU version
included in the intl extension. For example, if your intl extension includes
ICU 4.8, the script will download the latest data available for ICU 4.8.

For running this script, the intl extension must be loaded and all vendors
must have been installed through composer:

    composer install --dev

MESSAGE
    );
}

echo LINE;
echo centered("ICU Resource Bundle Compilation") . "\n";
echo LINE;

if (!Intl::isExtensionLoaded()) {
    bailout('The intl extension for PHP is not installed.');
}

if (!class_exists('\Symfony\Component\Icu\IcuData')) {
    bailout('You must run "composer update --dev" before running this script.');
}

$icuVersionInPhp = Intl::getIcuVersion();

echo "Found intl extension with ICU version $icuVersionInPhp.\n";

$shortIcuVersion = strip_minor_versions($icuVersionInPhp);
$urls = parse_ini_file(__DIR__ . '/icu.ini');

if (!isset($urls[$shortIcuVersion])) {
    bailout('The version ' . $shortIcuVersion . ' is not available in the icu.ini file.');
}

echo "icu.ini parsed. Available versions:\n";

foreach ($urls as $urlVersion => $url) {
    echo "  $urlVersion\n";
}

echo "Starting SVN checkout for version $shortIcuVersion. This may take a while...\n";

$svn = SvnRepository::download($urls[$shortIcuVersion], $shortIcuVersion);

echo "SVN checkout to {$svn->getPath()} complete.\n";

// Always build genrb so that we can determine the ICU version of the
// download by running genrb --version
echo "Building genrb.\n";

cd($svn->getPath());

echo "Running make clean...\n";

run('make clean');

echo "Running configure...\n";

run('./configure 2>&1');

cd($svn->getPath() . '/tools');

echo "Running make...\n";

run('make 2>&1');

$genrb = $svn->getPath() . '/bin/genrb';

echo "Using $genrb.\n";

$icuVersionInDownload = get_icu_version_from_genrb($genrb);

echo "Preparing resource bundle compilation (version $icuVersionInDownload)...\n";

$context = new CompilationContext(
    $svn->getPath() . '/data',
    IcuData::getResourceDirectory(),
    new Filesystem(),
    new BundleCompiler($genrb),
    $icuVersionInDownload
);

$transformer = new BundleTransformer();
$transformer->addRule(new LanguageBundleTransformationRule());
$transformer->addRule(new RegionBundleTransformationRule());
$transformer->addRule(new CurrencyBundleTransformationRule());
$transformer->addRule(new LocaleBundleTransformationRule());

echo "Starting resource bundle compilation. This may take a while...\n";

$transformer->compileBundles($context);

echo "Resource bundle compilation complete.\n";

$svnInfo = <<<SVN_INFO
SVN information
===============

URL: {$svn->getUrl()}
Revision: {$svn->getLastCommit()->getRevision()}
Author: {$svn->getLastCommit()->getAuthor()}
Date: {$svn->getLastCommit()->getDate()}

SVN_INFO;

$svnInfoFile = $context->getBinaryDir() . '/svn-info.txt';

file_put_contents($svnInfoFile, $svnInfo);

echo "Wrote $svnInfoFile.\n";

$versionFile = $context->getBinaryDir() . '/version.txt';

file_put_contents($versionFile, "$icuVersionInDownload\n");

echo "Wrote $versionFile.\n";

echo "Done.\n";
