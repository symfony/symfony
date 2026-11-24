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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Loader\IniFileLoader;

class IniFileLoaderTest extends TestCase
{
    public function testLoad()
    {
        $loader = new IniFileLoader();
        $resource = __DIR__.'/../Fixtures/resources.ini';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar'], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadDoesNothingIfEmpty()
    {
        $loader = new IniFileLoader();
        $resource = __DIR__.'/../Fixtures/empty.ini';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals([], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    #[TestWith(["[invalid\nfoo=bar"])]
    #[TestWith(['foo="unterminated'])]
    public function testLoadInvalidResource(string $contents)
    {
        $resource = tempnam(sys_get_temp_dir(), 'translation');

        try {
            file_put_contents($resource, $contents);

            $this->expectException(InvalidResourceException::class);
            $this->expectExceptionMessage(\sprintf('Unable to load file "%s".', $resource));

            @(new IniFileLoader())->load($resource, 'en');
        } finally {
            unlink($resource);
        }
    }

    public function testLoadNonExistingResource()
    {
        $this->expectException(NotFoundResourceException::class);

        (new IniFileLoader())->load(__DIR__.'/../Fixtures/non-existing.ini', 'en', 'domain1');
    }
}
