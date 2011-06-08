#!/usr/bin/env php
<?php
/*
 * Coding Standards (a.k.a. CS)
 *
 * This script is designed to clean up the source files and thus follow coding
 * conventions.
 *
 * @see http://symfony.com/doc/2.0/contributing/code/standards.html
 *
 */

require_once __DIR__.'/autoload.php.dist';

use Symfony\Component\Finder\Finder;

$fix = isset($argv[1]) && 'fix' == $argv[1];

$finder = new Finder();
$finder
    ->files()
    ->name('*.md')
    ->name('*.php')
    ->name('*.php.dist')
    ->name('*.twig')
    ->name('*.xml')
    ->name('*.xml.dist')
    ->name('*.yml')
    ->in(array(__DIR__.'/src', __DIR__.'/tests'))
    ->notName(basename(__FILE__))
    ->exclude('.git')
    ->exclude('vendor')
;

$count = 0;

foreach ($finder as $file) {

    /* @var $file Symfony\Component\Finder\SplFileInfo */

    // These files are skipped because tests would break
    foreach(array(
        'tests/Symfony/Tests/Component/ClassLoader/ClassCollectionLoaderTest.php',
        'tests/Symfony/Tests/Component/DependencyInjection/Fixtures/containers/container9.php',
        'tests/Symfony/Tests/Component/DependencyInjection/Fixtures/includes/foo.php',
        'tests/Symfony/Tests/Component/DependencyInjection/Fixtures/php/services9.php',
        'tests/Symfony/Tests/Component/DependencyInjection/Fixtures/yaml/services9.yml',
        'tests/Symfony/Tests/Component/Routing/Fixtures/dumper/url_matcher1.php',
        'tests/Symfony/Tests/Component/Routing/Fixtures/dumper/url_matcher2.php',
        'tests/Symfony/Tests/Component/Yaml/Fixtures/sfTests.yml',
    ) as $skippedFile) {

        if ($skippedFile === substr($file->getRealPath(), strlen($skippedFile) * -1)) {
            continue(2);
        }
    }

    $old = file_get_contents($file->getRealpath());

    $new = $old;

    // [Structure] Never use short tags (<?)
    $new = str_replace('<? ', '<?php ', $new);

    // [Structure] Indentation is done by steps of four spaces (tabs are never allowed)
    $new = preg_replace_callback('/^( *)(\t+)/m', function ($matches) use ($new) {
        return $matches[1].str_repeat('    ', strlen($matches[2]));
    }, $new);

    // [Structure] Use the linefeed character (0x0A) to end lines
    $new = str_replace("\r\n", "\n", $new);

    // [Structure] Don't add trailing spaces at the end of lines
    $new = preg_replace('/[ \t]*$/m', '', $new);

    // [Structure] Add a blank line before return statements
    $new = preg_replace_callback('/(^.*$)\n(^ +return)/m', function ($match) {
        // don't add it if the previous line is ...
        if (
            preg_match('/\{$/m',   $match[1]) || // ... ending with an opening brace
            preg_match('/\:$/m',   $match[1]) || // ... ending with a colon (e.g. a case statement)
            preg_match('%^ *//%m', $match[1]) || // ... an inline comment
            preg_match('/^$/m',    $match[1])    // ... already blank
        ) {
            return $match[1]."\n".$match[2];
        }

        return $match[1]."\n\n".$match[2];
    }, $new);

    // [Structure] A file must always ends with a linefeed character
    if (strlen($new) && "\n" != substr($new, -1)) {
        $new .= "\n";
    }

    if ($new != $old) {
        $count++;

        if ($fix) {
            file_put_contents($file->getRealpath(), $new);
        }
        printf('%4d) %s'.PHP_EOL, $count, $file->getRelativePathname());
    }
}

exit($count ? 1 : 0);
