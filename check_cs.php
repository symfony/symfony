#!/usr/bin/php
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
    ->in(__DIR__)
    ->notName(basename(__FILE__))
    ->exclude('.git')
    ->exclude('vendor')
;

foreach ($finder as $file) { /* @var $file Symfony\Component\Finder\SplFileInfo */

    // These files are skipped because tests would break
    if (in_array($file->getRelativePathname(), array(
        'tests/Symfony/Tests/Component/ClassLoader/ClassCollectionLoaderTest.php',
        'tests/Symfony/Tests/Component/DependencyInjection/Fixtures/containers/container9.php',
        'tests/Symfony/Tests/Component/DependencyInjection/Fixtures/includes/foo.php',
        'tests/Symfony/Tests/Component/DependencyInjection/Fixtures/php/services9.php',
        'tests/Symfony/Tests/Component/DependencyInjection/Fixtures/yaml/services9.yml',
        'tests/Symfony/Tests/Component/Routing/Fixtures/dumper/url_matcher1.php',
        'tests/Symfony/Tests/Component/Routing/Fixtures/dumper/url_matcher2.php',
        'tests/Symfony/Tests/Component/Yaml/Fixtures/sfTests.yml',
    ))) {
        continue;
    }

    $old = file_get_contents($file->getRealpath());

    $new = $old;

    // [Structure] Never use short tags (<?);
    $new = str_replace('<? ', '<?php ', $new);

    // [Structure] Indentation is done by steps of four spaces (tabs are never allowed);
    $new = preg_replace_callback('/^( *)(\t+)/m', function ($matches) use ($new) {
        return $matches[1] . str_repeat('    ', strlen($matches[2]));
    }, $new);

    // [Structure] Use the linefeed character (0x0A) to end lines;
    $new = str_replace("\r\n", "\n", $new);

    // [Structure] Don't add trailing spaces at the end of lines;
    $new = preg_replace('/[ \t]*$/m', '', $new);

    // [Structure] Add a blank line before return statements;
    $new = preg_replace('/([^    {|\n]$)(\n        return .+?$\n    \}$)/m', '$1'."\n".'$2', $new);

    if ($new != $old) {
        file_put_contents($file->getRealpath(), $new);
        echo $file->getRelativePathname() . PHP_EOL;
    }
}
