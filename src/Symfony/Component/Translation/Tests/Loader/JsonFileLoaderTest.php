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
use Symfony\Component\Translation\Loader\JsonFileLoader;

class JsonFileLoaderTest extends TestCase
{
    public function testLoad()
    {
        $loader = new JsonFileLoader();
        $resource = __DIR__.'/../Fixtures/resources.json';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar'], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadDoesNothingIfEmpty()
    {
        $loader = new JsonFileLoader();
        $resource = __DIR__.'/../Fixtures/empty.json';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals([], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadNonExistingResource()
    {
        $this->expectException(NotFoundResourceException::class);

        (new JsonFileLoader())->load(__DIR__.'/../Fixtures/non-existing.json', 'en', 'domain1');
    }

    public function testParseException()
    {
        $this->expectException(InvalidResourceException::class);
        $this->expectExceptionMessage('Error parsing JSON: Syntax error, malformed JSON');

        (new JsonFileLoader())->load(__DIR__.'/../Fixtures/malformed.json', 'en', 'domain1');
    }
}
