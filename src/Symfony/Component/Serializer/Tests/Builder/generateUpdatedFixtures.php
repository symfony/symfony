<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function includeIfExists(string $file): bool
{
    return file_exists($file) && include $file;
}

if (
    !includeIfExists(__DIR__.'/../../../../autoload.php')
    && !includeIfExists(__DIR__.'/../../vendor/autoload.php')
    && !includeIfExists(__DIR__.'/../../../../../../vendor/autoload.php')
) {
    fwrite(\STDERR, 'Install dependencies using Composer.'.\PHP_EOL);
    exit(1);
}

use Symfony\Component\Serializer\Builder\NormalizerBuilder;
use Symfony\Component\Serializer\Tests\Builder\FixtureHelper;

$outputDir = sys_get_temp_dir();
$definitionExtractor = FixtureHelper::getDefinitionExtractor();
$builder = new NormalizerBuilder();

echo \PHP_EOL;
$i = 0;
foreach (FixtureHelper::getFixturesAndResultFiles() as [$class, $outputFile]) {
    $definition = $definitionExtractor->getDefinition($class);
    $result = $builder->build($definition, $outputDir);
    $result->loadClass();
    file_put_contents($outputFile, file_get_contents($result->filePath));
    echo '.';
    if (0 === ++$i % 20) {
        echo \PHP_EOL;
    }
}

echo \PHP_EOL.\PHP_EOL;
echo 'Done generating fixtures.';
echo \PHP_EOL;
