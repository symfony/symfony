<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Loader\CsvFileLoader;

class CsvFileLoaderTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    public function testLoad()
    {
        $loader = new CsvFileLoader();
        $resource = __DIR__.'/../Fixtures/resources.csv';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar'], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadDoesNothingIfEmpty()
    {
        $loader = new CsvFileLoader();
        $resource = __DIR__.'/../Fixtures/empty.csv';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals([], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadNonExistingResource()
    {
        $this->expectException(NotFoundResourceException::class);

        (new CsvFileLoader())->load(__DIR__.'/../Fixtures/not-exists.csv', 'en', 'domain1');
    }

    public function testLoadNonLocalResource()
    {
        $this->expectException(InvalidResourceException::class);

        (new CsvFileLoader())->load('http://example.com/resources.csv', 'en', 'domain1');
    }

    /**
     * @group legacy
     */
    public function testEscapeCharInCsvControlIsDeprecated()
    {
        $loader = new CsvFileLoader();

        $this->expectUserDeprecationMessage('Since symfony/translation 7.2: The "escape" parameter of the "Symfony\Component\Translation\Loader\CsvFileLoader::setCsvControl" method is deprecated. It will be removed in 8.0.');
        $loader->setCsvControl(';', '"', '\\');
    }
}
