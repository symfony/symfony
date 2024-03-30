<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Loader\ContainerLoader;

class ContainerLoaderTest extends TestCase
{
    /**
     * @dataProvider supportsProvider
     */
    public function testSupports(bool $expected, ?string $type = null)
    {
        $this->assertSame($expected, (new ContainerLoader(new Container()))->supports('foo', $type));
    }

    public static function supportsProvider()
    {
        return [
            [true, 'service'],
            [false, 'bar'],
            [false, null],
        ];
    }
}
