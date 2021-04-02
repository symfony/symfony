<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Loader\Configurator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Loader\Configurator\EnvConfigurator;

final class EnvConfiguratorTest extends TestCase
{
    /**
     * @dataProvider provide
     */
    public function test(string $expected, EnvConfigurator $envConfigurator)
    {
        $this->assertSame($expected, (string) $envConfigurator);
    }

    public function provide()
    {
        yield ['%env(FOO)%', new EnvConfigurator('FOO')];
        yield ['%env(string:FOO)%', new EnvConfigurator('string:FOO')];
        yield ['%env(string:FOO)%', (new EnvConfigurator('FOO'))->string()];
        yield ['%env(key:path:url:FOO)%', (new EnvConfigurator('FOO'))->url()->key('path')];
        yield ['%env(default:fallback:bar:arg1:FOO)%', (new EnvConfigurator('FOO'))->custom('bar', 'arg1')->default('fallback')];
    }
}
