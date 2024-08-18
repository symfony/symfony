<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResourceChecker;
use Symfony\Component\DependencyInjection\Container;

class ContainerParametersResourceCheckerTest extends TestCase
{
    private ContainerParametersResource $resource;
    private ContainerParametersResourceChecker $resourceChecker;
    private Container $container;

    protected function setUp(): void
    {
        $this->resource = new ContainerParametersResource(['locales' => ['fr', 'en'], 'default_locale' => 'fr']);
        $this->container = new Container();
        $this->resourceChecker = new ContainerParametersResourceChecker($this->container);
    }

    public function testSupports()
    {
        $this->assertTrue($this->resourceChecker->supports($this->resource));
    }

    /**
     * @dataProvider isFreshProvider
     */
    public function testIsFresh(callable $mockContainer, $expected)
    {
        $mockContainer($this->container, $this);

        $this->assertSame($expected, $this->resourceChecker->isFresh($this->resource, time()));
    }

    public static function isFreshProvider()
    {
        yield 'not fresh on missing parameter' => [function (Container $container) {
        }, false];

        yield 'not fresh on different value' => [function (Container $container) {
            $container->setParameter('locales', ['nl', 'es']);
        }, false];

        yield 'fresh on every identical parameters' => [function (Container $container) {
            $container->setParameter('locales', ['fr', 'en']);
            $container->setParameter('default_locale', 'fr');
        }, true];
    }
}
