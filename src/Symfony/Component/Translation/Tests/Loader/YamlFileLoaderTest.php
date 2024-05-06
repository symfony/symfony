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
        $resource = __DIR__.'/../Fixtures/resources.yml';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar'], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadNonStringMessages()
    {
        $loader = new YamlFileLoader();
        $resource = __DIR__.'/../Fixtures/non-string.yml';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertSame(['root.foo2' => '', 'root.bar' => 'bar'], $catalogue->all('domain1'));
    }

    public function testLoadDoesNothingIfEmpty()
    {
        $loader = new YamlFileLoader();
        $resource = __DIR__.'/../Fixtures/empty.yml';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals([], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadNonExistingResource()
    {
        $loader = new YamlFileLoader();
        $resource = __DIR__.'/../Fixtures/non-existing.yml';

        $this->expectException(NotFoundResourceException::class);

        $loader->load($resource, 'en', 'domain1');
    }

    public function testLoadThrowsAnExceptionIfFileNotLocal()
    {
        $loader = new YamlFileLoader();
        $resource = 'http://example.com/resources.yml';

        $this->expectException(InvalidResourceException::class);

        $loader->load($resource, 'en', 'domain1');
    }

    public function testLoadThrowsAnExceptionIfNotAnArray()
    {
        $loader = new YamlFileLoader();
        $resource = __DIR__.'/../Fixtures/non-valid.yml';

        $this->expectException(InvalidResourceException::class);

        $loader->load($resource, 'en', 'domain1');
    }
}
