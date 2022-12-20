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
use Symfony\Component\Translation\Loader\YamlFileLoader;

class YamlFileLoaderTest extends TestCase
{
    public function testLoad()
    {
        $loader = new YamlFileLoader();
        $resource = __DIR__.'/../fixtures/resources.yml';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        self::assertEquals(['foo' => 'bar'], $catalogue->all('domain1'));
        self::assertEquals('en', $catalogue->getLocale());
        self::assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadDoesNothingIfEmpty()
    {
        $loader = new YamlFileLoader();
        $resource = __DIR__.'/../fixtures/empty.yml';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        self::assertEquals([], $catalogue->all('domain1'));
        self::assertEquals('en', $catalogue->getLocale());
        self::assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadNonExistingResource()
    {
        self::expectException(NotFoundResourceException::class);
        $loader = new YamlFileLoader();
        $resource = __DIR__.'/../fixtures/non-existing.yml';
        $loader->load($resource, 'en', 'domain1');
    }

    public function testLoadThrowsAnExceptionIfFileNotLocal()
    {
        self::expectException(InvalidResourceException::class);
        $loader = new YamlFileLoader();
        $resource = 'http://example.com/resources.yml';
        $loader->load($resource, 'en', 'domain1');
    }

    public function testLoadThrowsAnExceptionIfNotAnArray()
    {
        self::expectException(InvalidResourceException::class);
        $loader = new YamlFileLoader();
        $resource = __DIR__.'/../fixtures/non-valid.yml';
        $loader->load($resource, 'en', 'domain1');
    }
}
