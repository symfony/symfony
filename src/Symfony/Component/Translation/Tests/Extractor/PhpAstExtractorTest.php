<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Extractor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Extractor\PhpAstExtractor;
use Symfony\Component\Translation\Extractor\Visitor\TransMethodVisitor;
use Symfony\Component\Translation\MessageCatalogue;

final class PhpAstExtractorTest extends TestCase
{
    private const FIXTURES_FOLDER = __DIR__ . '/../Fixtures/extractor-php-ast/extract-files/';

    /**
     * @dataProvider resourcesProvider
     */
    public function testExtractFiles(iterable|string $resource)
    {
        $extractor = new PhpAstExtractor([new TransMethodVisitor()]);
        $catalogue = new MessageCatalogue('en');

        $extractor->extract($resource, $catalogue);

        $this->assertEquals(['messages' => ['example' => 'example']], $catalogue->all());
        $this->assertEquals(['sources' => [self::FIXTURES_FOLDER.'translation.html.php:1']], $catalogue->getMetadata('example'));
    }

    public static function resourcesProvider(): \Generator
    {
        $phpFiles = [];
        $splFiles = [];
        foreach (new \DirectoryIterator(self::FIXTURES_FOLDER) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            if ('php' === $fileInfo->getExtension()) {
                $phpFiles[] = $fileInfo->getPathname();
            }
            $splFiles[] = $fileInfo->getFileInfo();
        }

        yield 'directory' => [self::FIXTURES_FOLDER];
        yield 'phpFiles' => [$phpFiles];
        yield 'glob' => [glob(self::FIXTURES_FOLDER.'*')];
        yield 'splFiles' => [$splFiles];
        yield 'ArrayObject_glob' => [new \ArrayObject(glob(self::FIXTURES_FOLDER.'*'))];
        yield 'ArrayObject_splFiles' => [new \ArrayObject($splFiles)];
    }
}
