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
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Loader\CsvFileLoader;

class CsvFileLoaderTest extends TestCase
{
    public function testLoad()
    {
        $loader = new CsvFileLoader();
        $resource = __DIR__.'/../fixtures/resources.csv';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        self::assertEquals(['foo' => 'bar'], $catalogue->all('domain1'));
        self::assertEquals('en', $catalogue->getLocale());
        self::assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadDoesNothingIfEmpty()
    {
        $loader = new CsvFileLoader();
        $resource = __DIR__.'/../fixtures/empty.csv';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        self::assertEquals([], $catalogue->all('domain1'));
        self::assertEquals('en', $catalogue->getLocale());
        self::assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadNonExistingResource()
    {
        self::expectException(NotFoundResourceException::class);
        $loader = new CsvFileLoader();
        $resource = __DIR__.'/../fixtures/not-exists.csv';
        $loader->load($resource, 'en', 'domain1');
    }

    public function testLoadNonLocalResource()
    {
        self::expectException(InvalidResourceException::class);
        $loader = new CsvFileLoader();
        $resource = 'http://example.com/resources.csv';
        $loader->load($resource, 'en', 'domain1');
    }
}
