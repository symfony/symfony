<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Intl\Data\Bundle\Reader\JsonBundleReader;
use Symfony\Component\Intl\Data\Bundle\Writer\JsonBundleWriter;
use Symfony\Component\Intl\Data\Generator\CurrencyDataGenerator;
use Symfony\Component\Intl\Data\Generator\GeneratorConfig;
use Symfony\Component\Intl\Data\Generator\LanguageDataGenerator;
use Symfony\Component\Intl\Data\Generator\LocaleDataGenerator;
use Symfony\Component\Intl\Data\Generator\RegionDataGenerator;
use Symfony\Component\Intl\Data\Generator\ScriptDataGenerator;
use Symfony\Component\Intl\Data\Provider\LanguageDataProvider;
use Symfony\Component\Intl\Data\Provider\RegionDataProvider;
use Symfony\Component\Intl\Data\Provider\ScriptDataProvider;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Data\Bundle\Compiler\GenrbCompiler;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReader;
use Symfony\Component\Intl\Locale;
use Symfony\Component\Intl\Util\IcuVersion;
use Symfony\Component\Intl\Util\SvnRepository;
use Symfony\Component\Filesystem\Filesystem;

require_once __DIR__.'/common.php';
require_once __DIR__.'/autoload.php';

$argc = $_SERVER['argc'];
$argv = $_SERVER['argv'];

if ($argc > 3 || 2 === $argc && '-h' === $argv[1]) {
    bailout(<<<MESSAGE
Usage: php update-icu-component.php <path/to/icu/source> <path/to/icu/build>

Updates the ICU data for Symfony to the latest version of ICU.

If you downloaded the SVN repository before, you can pass the path to the
repository source in the first optional argument.

If you also built the repository before, you can pass the directory where that
build is stored in the second parameter. The build directory needs to contain
the subdirectories bin/ and lib/.

For running this script, the intl extension must be loaded and all vendors
must have been installed through composer:

composer install

MESSAGE
    );
}

echo LINE;
echo centered('ICU Resource Bundle Compilation')."\n";
echo LINE;

if (!Intl::isExtensionLoaded()) {
    bailout('The intl extension for PHP is not installed.');
}

$filesystem = new Filesystem();
$urls = parse_ini_file(__DIR__.'/icu.ini');

echo "icu.ini parsed. Available versions:\n";

$maxVersion = 0;

foreach ($urls as $urlVersion => $url) {
    $maxVersion = IcuVersion::compare($maxVersion, $urlVersion, '<')
        ? $urlVersion
        : $maxVersion;

    echo "  $urlVersion\n";
}

$shortIcuVersion = strip_minor_versions($maxVersion);

if ($argc >= 2) {
    $sourceDir = $argv[1];
    $svn = new SvnRepository($sourceDir);

    echo "Using existing SVN repository at {$sourceDir}.\n";
} else {
    echo "Starting SVN checkout for version $shortIcuVersion. This may take a while...\n";

    $sourceDir = sys_get_temp_dir().'/icu-data/'.$shortIcuVersion.'/source';
    $svn = SvnRepository::download($urls[$shortIcuVersion], $sourceDir);

    echo "SVN checkout to {$sourceDir} complete.\n";
}

if ($argc >= 3) {
    $buildDir = $argv[2];
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

    echo '[1/6] libicudata.so...';

    cd($sourceDir.'/stubdata');
    run('make 2>&1 && make install 2>&1');

    echo " ok.\n";

    echo '[2/6] libicuuc.so...';

    cd($sourceDir.'/common');
    run('make 2>&1 && make install 2>&1');

    echo " ok.\n";

    echo '[3/6] libicui18n.so...';

    cd($sourceDir.'/i18n');
    run('make 2>&1 && make install 2>&1');

    echo " ok.\n";

    echo '[4/6] libicutu.so...';

    cd($sourceDir.'/tools/toolutil');
    run('make 2>&1 && make install 2>&1');

    echo " ok.\n";

    echo '[5/6] libicuio.so...';

    cd($sourceDir.'/io');
    run('make 2>&1 && make install 2>&1');

    echo " ok.\n";

    echo '[6/6] genrb...';

    cd($sourceDir.'/tools/genrb');
    run('make 2>&1 && make install 2>&1');

    echo " ok.\n";
}

$genrb = $buildDir.'/bin/genrb';
$genrbEnv = 'LD_LIBRARY_PATH='.$buildDir.'/lib ';

echo "Using $genrb.\n";

$icuVersionInDownload = get_icu_version_from_genrb($genrbEnv.' '.$genrb);

echo "Preparing resource bundle compilation (version $icuVersionInDownload)...\n";

$compiler = new GenrbCompiler($genrb, $genrbEnv);
$config = new GeneratorConfig($sourceDir.'/data', $icuVersionInDownload);

$baseDir = dirname(__DIR__).'/data';

//$txtDir = $baseDir.'/txt';
$jsonDir = $baseDir;
//$phpDir = $baseDir.'/'.Intl::PHP;
//$resDir = $baseDir.'/'.Intl::RB_V2;

$targetDirs = array($jsonDir/*, $resDir*/);
$workingDirs = array($jsonDir/*, $txtDir, $resDir*/);

//$config->addBundleWriter($txtDir, new TextBundleWriter());
$config->addBundleWriter($jsonDir, new JsonBundleWriter());

echo "Starting resource bundle compilation. This may take a while...\n";

$filesystem->remove($workingDirs);

foreach ($workingDirs as $targetDir) {
    $filesystem->mkdir(array(
        $targetDir.'/'.Intl::CURRENCY_DIR,
        $targetDir.'/'.Intl::LANGUAGE_DIR,
        $targetDir.'/'.Intl::LOCALE_DIR,
        $targetDir.'/'.Intl::REGION_DIR,
        $targetDir.'/'.Intl::SCRIPT_DIR,
    ));
}

// We don't want to use fallback to English during generation
Locale::setDefaultFallback(null);

echo "Generating language data...\n";

$generator = new LanguageDataGenerator($compiler, Intl::LANGUAGE_DIR);
$generator->generateData($config);

//echo "Compiling...\n";
//
//$compiler->compile($txtDir.'/'.Intl::LANGUAGE_DIR, $resDir.'/'.Intl::LANGUAGE_DIR);

echo "Generating script data...\n";

$generator = new ScriptDataGenerator($compiler, Intl::SCRIPT_DIR);
$generator->generateData($config);

//echo "Compiling...\n";
//
//$compiler->compile($txtDir.'/'.Intl::SCRIPT_DIR, $resDir.'/'.Intl::SCRIPT_DIR);

echo "Generating region data...\n";

$generator = new RegionDataGenerator($compiler, Intl::REGION_DIR);
$generator->generateData($config);

//echo "Compiling...\n";
//
//$compiler->compile($txtDir.'/'.Intl::REGION_DIR, $resDir.'/'.Intl::REGION_DIR);

echo "Generating currency data...\n";

$generator = new CurrencyDataGenerator($compiler, Intl::CURRENCY_DIR);
$generator->generateData($config);

//echo "Compiling...\n";
//
//$compiler->compile($txtDir.'/'.Intl::CURRENCY_DIR, $resDir.'/'.Intl::CURRENCY_DIR);

echo "Generating locale data...\n";

$reader = new BundleEntryReader(new JsonBundleReader());

$generator = new LocaleDataGenerator(
    Intl::LOCALE_DIR,
    new LanguageDataProvider($jsonDir.'/'.Intl::LANGUAGE_DIR, $reader),
    new ScriptDataProvider($jsonDir.'/'.Intl::SCRIPT_DIR, $reader),
    new RegionDataProvider($jsonDir.'/'.Intl::REGION_DIR, $reader)
);

$generator->generateData($config);

//echo "Compiling...\n";
//
//$compiler->compile($txtDir.'/'.Intl::LOCALE_DIR, $resDir.'/'.Intl::LOCALE_DIR);
//
//$filesystem->remove($txtDir);

echo "Resource bundle compilation complete.\n";

$svnInfo = <<<SVN_INFO
SVN information
===============

URL: {$svn->getUrl()}
Revision: {$svn->getLastCommit()->getRevision()}
Author: {$svn->getLastCommit()->getAuthor()}
Date: {$svn->getLastCommit()->getDate()}

SVN_INFO;

foreach ($targetDirs as $targetDir) {
    $svnInfoFile = $targetDir.'/svn-info.txt';

    file_put_contents($svnInfoFile, $svnInfo);

    echo "Wrote $svnInfoFile.\n";

    $versionFile = $targetDir.'/version.txt';

    file_put_contents($versionFile, "$icuVersionInDownload\n");

    echo "Wrote $versionFile.\n";
}

echo "Done.\n";
