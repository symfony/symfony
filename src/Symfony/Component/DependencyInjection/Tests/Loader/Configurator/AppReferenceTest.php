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
use Symfony\Component\DependencyInjection\Loader\Configurator\AppReference;

class AppReferenceTest extends TestCase
{
    public function testConfigWithBasicServices()
    {
        $config = [
            'services' => [
                'App\\' => [
                    'resource' => '../src/',
                ],
                'my_service' => [
                    'class' => 'MyClass',
                ],
            ],
        ];

        $result = AppReference::config($config);

        $config = array_replace_recursive([
            'services' => [
                '_defaults' => [
                    'autowire' => true,
                    'autoconfigure' => true,
                ],
            ],
        ], $config);

        $this->assertSame($config, $result);
    }

    public function testConfigWithWhenEnv()
    {
        $config = [
            'services' => [
                'App\\' => [
                    'resource' => '../src/',
                ],
            ],
            'when@dev' => [
                'services' => [
                    'dev_service' => [
                        'class' => 'DevClass',
                    ],
                ],
            ],
        ];

        $result = AppReference::config($config);

        $config = array_replace_recursive([
            'services' => [
                '_defaults' => [
                    'autowire' => true,
                    'autoconfigure' => true,
                ]],
            'when@dev' => [
                'services' => [
                    '_defaults' => [
                        'autowire' => true,
                        'autoconfigure' => true,
                    ],
                ],
            ],
        ], $config);

        $this->assertSame($config, $result);
    }

    public function testConfigWithoutServices()
    {
        $config = [
            'parameters' => [
                'app.name' => 'My App',
            ],
            'framework' => [
                'secret' => 'secret',
            ],
        ];

        $result = AppReference::config($config);

        $this->assertSame($config, $result);
    }

    public function testConfigWithExistingDefaults()
    {
        $config = [
            'services' => [
                '_defaults' => [
                    'autowire' => false,
                    'autoconfigure' => false,
                ],
                'App\\' => [
                    'resource' => '../src/',
                ],
            ],
            'when@dev' => [
                'services' => [
                    '_defaults' => [
                        'autowire' => false,
                        'autoconfigure' => false,
                    ],
                    'dev_service' => [
                        'class' => 'DevClass',
                    ],
                ],
            ],
        ];

        $result = AppReference::config($config);

        $this->assertSame($config, $result);
    }

    public function testConfigWithWhenEnvWithoutServices()
    {
        $config = [
            'services' => [
                'App\\' => [
                    'resource' => '../src/',
                ],
            ],
            'when@dev' => [
                'parameters' => [
                    'app.debug' => true,
                ],
            ],
        ];

        $result = AppReference::config($config);

        $config = array_replace_recursive([
            'services' => [
                '_defaults' => [
                    'autowire' => true,
                    'autoconfigure' => true,
                ],
            ],
        ], $config);

        $this->assertSame($config, $result);
    }
}
