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
use Symfony\Component\Translation\Loader\QtFileLoader;

class QtFileLoaderTest extends TestCase
{
    public function testLoad()
    {
        $loader = new QtFileLoader();
        $resource = __DIR__.'/../Fixtures/resources.ts';
        $catalogue = $loader->load($resource, 'en', 'resources');

        $this->assertEquals([
            'foo' => 'bar',
            'foo_bar' => 'foobar',
            'bar_foo' => 'barfoo',
        ], $catalogue->all('resources'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadNonExistingResource()
    {
        $this->expectException(NotFoundResourceException::class);

        (new QtFileLoader())->load(__DIR__.'/../Fixtures/non-existing.ts', 'en', 'domain1');
    }

    public function testLoadNonLocalResource()
    {
        $this->expectException(InvalidResourceException::class);

        (new QtFileLoader())->load('http://domain1.com/resources.ts', 'en', 'domain1');
    }

    public function testLoadInvalidResource()
    {
        $this->expectException(InvalidResourceException::class);

        (new QtFileLoader())->load(__DIR__.'/../Fixtures/invalid-xml-resources.xlf', 'en', 'domain1');
    }

    public function testLoadEmptyResource()
    {
        $resource = __DIR__.'/../Fixtures/empty.xlf';

        $this->expectException(InvalidResourceException::class);
        $this->expectExceptionMessage(\sprintf('Unable to load "%s".', $resource));

        (new QtFileLoader())->load($resource, 'en', 'domain1');
    }
}
