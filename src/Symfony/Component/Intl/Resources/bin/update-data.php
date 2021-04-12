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
use Symfony\Component\Intl\Data\Bundle\Compiler\GenrbCompiler;
use Symfony\Component\Intl\Data\Bundle\Writer\PhpBundleWriter;
use Symfony\Component\Intl\Data\Generator\CurrencyDataGenerator;
use Symfony\Component\Intl\Data\Generator\GeneratorConfig;
use Symfony\Component\Intl\Data\Generator\LanguageDataGenerator;
use Symfony\Component\Intl\Data\Generator\LocaleDataGenerator;
use Symfony\Component\Intl\Data\Generator\RegionDataGenerator;
use Symfony\Component\Intl\Data\Generator\ScriptDataGenerator;
use Symfony\Component\Intl\Data\Generator\TimezoneDataGenerator;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Locale;
use Symfony\Component\Intl\Util\GitRepository;

require_once __DIR__.'/common.php';
require_once __DIR__.'/autoload.php';

$argc = $_SERVER['argc'];
$argv = $_SERVER['argv'];

if ($argc > 3 || 2 === $argc && '-h' === $argv[1]) {
    bailout(<<<'MESSAGE'
Usage: php update-data.php <path/to/icu/source> <path/to/icu/build>

Updates the ICU data for Symfony to the latest version of ICU.

If you downloaded the git repository before, you can pass the path to the
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

if ($argc >= 2) {
    $repoDir = $argv[1];
    $git = new GitRepository($repoDir);

    echo "Using the existing git repository at {$repoDir}.\n";
} else {
    echo "Starting git clone. This may take a while...\n";

    $repoDir = sys_get_temp_dir().'/icu-data';
    $git = GitRepository::download('https://github.com/unicode-org/icu.git', $repoDir);

    echo "Git clone to {$repoDir} complete.\n";
}

$gitTag = $git->getLastTag(function ($tag) {
    return preg_match('#^release-[0-9]{1,}-[0-9]{1}$#', $tag);
});
$shortIcuVersion = strip_minor_versions(preg_replace('#release-([0-9]{1,})-([0-9]{1,})#', '$1.$2', $gitTag));

echo "Checking out `{$gitTag}` for version `{$shortIcuVersion}`...\n";
$git->checkout('tags/'.$gitTag);

$filesystem = new Filesystem();
$sourceDir = $repoDir.'/icu4c/source';

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
$dataDir = dirname(__DIR__).'/data';

$config->addBundleWriter($dataDir, new PhpBundleWriter());

echo "Starting resource bundle compilation. This may take a while...\n";

// We don't want to use fallback to English during generation
Locale::setDefaultFallback('root');

echo "Generating language data...\n";

$generator = new LanguageDataGenerator($compiler, Intl::LANGUAGE_DIR);
$generator->generateData($config);

echo "Generating script data...\n";

$generator = new ScriptDataGenerator($compiler, Intl::SCRIPT_DIR);
$generator->generateData($config);

echo "Generating region data...\n";

$generator = new RegionDataGenerator($compiler, Intl::REGION_DIR);
$generator->generateData($config);

echo "Generating currency data...\n";

$generator = new CurrencyDataGenerator($compiler, Intl::CURRENCY_DIR);
$generator->generateData($config);

echo "Generating locale data...\n";

$generator = new LocaleDataGenerator($compiler, Intl::LOCALE_DIR);
$generator->generateData($config);

echo "Generating timezone data...\n";

$generator = new TimezoneDataGenerator($compiler, Intl::TIMEZONE_DIR);
$generator->generateData($config);

echo "Resource bundle compilation complete.\n";

$gitInfo = <<<GIT_INFO
Git information
===============

URL: {$git->getUrl()}
Revision: {$git->getLastCommitHash()}
Author: {$git->getLastAuthor()}
Date: {$git->getLastAuthoredDate()->format('c')}

GIT_INFO;

$gitInfoFile = $dataDir.'/git-info.txt';

file_put_contents($gitInfoFile, $gitInfo);

echo "Wrote $gitInfoFile.\n";

$versionFile = $dataDir.'/version.txt';

file_put_contents($versionFile, "$icuVersionInDownload\n");

echo "Wrote $versionFile.\n";
echo "Done.\n";
