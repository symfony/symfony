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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\ResourceCheckerInterface;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResourceChecker;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContainerParametersResourceCheckerTest extends TestCase
{
    /** @var ContainerParametersResource */
    private $resource;

    /** @var ResourceCheckerInterface */
    private $resourceChecker;

    /** @var ContainerInterface */
    private $container;

    protected function setUp(): void
    {
        $this->resource = new ContainerParametersResource(['locales' => ['fr', 'en'], 'default_locale' => 'fr']);
        $this->container = $this->createMock(ContainerInterface::class);
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
        $mockContainer($this->container);

        $this->assertSame($expected, $this->resourceChecker->isFresh($this->resource, time()));
    }

    public static function isFreshProvider()
    {
        yield 'not fresh on missing parameter' => [function (MockObject $container) {
            $container->method('hasParameter')->with('locales')->willReturn(false);
        }, false];

        yield 'not fresh on different value' => [function (MockObject $container) {
            $container->method('getParameter')->with('locales')->willReturn(['nl', 'es']);
        }, false];

        yield 'fresh on every identical parameters' => [function (MockObject $container) {
            $container->expects(self::exactly(2))->method('hasParameter')->willReturn(true);
            $container->expects(self::exactly(2))->method('getParameter')
                ->willReturnCallback(function (...$args) {
                    static $series = [
                        [['locales'], ['fr', 'en']],
                        [['default_locale'], 'fr'],
                    ];

                    [$expectedArgs, $return] = array_shift($series);
                    self::assertSame($expectedArgs, $args);

                    return $return;
                })
            ;
        }, true];
    }
}
