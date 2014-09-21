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

require_once __DIR__.'/common.php';
require_once __DIR__.'/autoload.php';

if ($GLOBALS['argc'] > 3 || 2 === $GLOBALS['argc'] && '-h' === $GLOBALS['argv'][1]) {
    bailout(<<<MESSAGE
Usage: php update-icu-component.php <path/to/icu/source> <path/to/icu/build>

Updates the ICU data for Symfony2 to the latest version of the ICU version
included in the intl extension. For example, if your intl extension includes
ICU 4.8, the script will download the latest data available for ICU 4.8.

If you downloaded the SVN repository before, you can pass the path to the
repository source in the first optional argument.

If you also built the repository before, you can pass the directory where that
build is stored in the second parameter. The build directory needs to contain
the subdirectories bin/ and lib/.

For running this script, the intl extension must be loaded and all vendors
must have been installed through composer:

    composer install --dev

MESSAGE
    );
}

echo LINE;
echo centered("ICU Resource Bundle Compilation")."\n";
echo LINE;

if (!Intl::isExtensionLoaded()) {
    bailout('The intl extension for PHP is not installed.');
}

if (!class_exists('\Symfony\Component\Icu\IcuData')) {
    bailout('You must run "composer update --dev" before running this script.');
}

$filesystem = new Filesystem();

$icuVersionInPhp = Intl::getIcuVersion();

echo "Found intl extension with ICU version $icuVersionInPhp.\n";

$shortIcuVersion = strip_minor_versions($icuVersionInPhp);
$urls = parse_ini_file(__DIR__.'/icu.ini');

if (!isset($urls[$shortIcuVersion])) {
    bailout('The version '.$shortIcuVersion.' is not available in the icu.ini file.');
}

echo "icu.ini parsed. Available versions:\n";

foreach ($urls as $urlVersion => $url) {
    echo "  $urlVersion\n";
}

if ($GLOBALS['argc'] >= 2) {
    $sourceDir = $GLOBALS['argv'][1];
    $svn = new SvnRepository($sourceDir);

    echo "Using existing SVN repository at {$sourceDir}.\n";
} else {
    echo "Starting SVN checkout for version $shortIcuVersion. This may take a while...\n";

    $sourceDir = sys_get_temp_dir().'/icu-data/'.$shortIcuVersion.'/source';
    $svn = SvnRepository::download($urls[$shortIcuVersion], $sourceDir);

    echo "SVN checkout to {$sourceDir} complete.\n";
}

if ($GLOBALS['argc'] >= 3) {
    $buildDir = $GLOBALS['argv'][2];
} else {
    // Always build genrb so that we can determine the ICU version of the
    // download by running genrb --version
    echo "Building genrb.\n";

    cd($sourceDir);

    echo "Running configure...\n";

    $buildDir = sys_get_temp_dir().'/icu-data/'.$shortIcuVersion.'/build';

    $filesystem->remove($buildDir);
    $filesystem->mkdir($buildDir);

    run('./configure --prefix='.$buildDir.' 2>&1');

    echo "Running make...\n";

    // If the directory "lib" does not exist in the download, create it or we
    // will run into problems when building libicuuc.so.
    $filesystem->mkdir($sourceDir.'/lib');

    // If the directory "bin" does not exist in the download, create it or we
    // will run into problems when building genrb.
    $filesystem->mkdir($sourceDir.'/bin');

    echo "[1/5] libicudata.so...";

    cd($sourceDir.'/stubdata');
    run('make 2>&1 && make install 2>&1');

    echo " ok.\n";

    echo "[2/5] libicuuc.so...";

    cd($sourceDir.'/common');
    run('make 2>&1 && make install 2>&1');

    echo " ok.\n";

    echo "[3/5] libicui18n.so...";

    cd($sourceDir.'/i18n');
    run('make 2>&1 && make install 2>&1');

    echo " ok.\n";

    echo "[4/5] libicutu.so...";

    cd($sourceDir.'/tools/toolutil');
    run('make 2>&1 && make install 2>&1');

    echo " ok.\n";

    echo "[5/5] genrb...";

    cd($sourceDir.'/tools/genrb');
    run('make 2>&1 && make install 2>&1');

    echo " ok.\n";
}

$genrb = $buildDir.'/bin/genrb';
$genrbEnv = 'LD_LIBRARY_PATH='.$buildDir.'/lib ';

echo "Using $genrb.\n";

$icuVersionInDownload = get_icu_version_from_genrb($genrbEnv.' '.$genrb);

echo "Preparing resource bundle compilation (version $icuVersionInDownload)...\n";

$context = new CompilationContext(
    $sourceDir.'/data',
    IcuData::getResourceDirectory(),
    $filesystem,
    new BundleCompiler($genrb, $genrbEnv),
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

$svnInfoFile = $context->getBinaryDir().'/svn-info.txt';

file_put_contents($svnInfoFile, $svnInfo);

echo "Wrote $svnInfoFile.\n";

$versionFile = $context->getBinaryDir().'/version.txt';

file_put_contents($versionFile, "$icuVersionInDownload\n");

echo "Wrote $versionFile.\n";

echo "Done.\n";
