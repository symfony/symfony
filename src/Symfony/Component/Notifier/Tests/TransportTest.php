<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport;
use Symfony\Component\Notifier\Transport\NullTransportFactory;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

class TransportTest extends TestCase
{
    public function testDefaultFactories()
    {
        $transport = new \ReflectionClass(Transport::class);
        $factoryClasses = $transport->getConstant('FACTORY_CLASSES');
        $factoryClasses[] = NullTransportFactory::class;
        $factoryClasses = array_values(array_filter($factoryClasses, 'class_exists'));
        $method = $transport->getMethod('getDefaultFactories');

        foreach ([null, new EventDispatcher()] as $dispatcher) {
            foreach ([null, new MockHttpClient()] as $client) {
                $factories = iterator_to_array($method->invoke(null, $dispatcher, $client));

                $this->assertSame($factoryClasses, array_map('get_class', $factories));
                foreach ($factories as $factory) {
                    $this->assertInstanceOf(TransportFactoryInterface::class, $factory);
                }
            }
        }
    }

    public function testAllFactoriesAreListed()
    {
        $factoryClasses = [];
        foreach (glob(\dirname(__DIR__).'/Bridge/*/*TransportFactory.php') as $file) {
            $factoryClasses[] = 'Symfony\\Component\\Notifier\\Bridge\\'.basename(\dirname($file)).'\\'.basename($file, '.php');
        }

        if (!$factoryClasses) {
            $this->markTestSkipped('The Notifier bridges are not available.');
        }

        foreach ($factoryClasses as $factoryClass) {
            $this->assertTrue(class_exists($factoryClass), $factoryClass);
        }

        $factoryClasses = array_values(array_diff($factoryClasses, [
            Bridge\FakeChat\FakeChatTransportFactory::class,
            Bridge\FakeSms\FakeSmsTransportFactory::class,
            Bridge\Mercure\MercureTransportFactory::class,
        ]));
        $listedFactories = (new \ReflectionClass(Transport::class))->getConstant('FACTORY_CLASSES');
        sort($factoryClasses);
        sort($listedFactories);

        $this->assertSame($factoryClasses, $listedFactories);
    }

    public function testFromDsnWithUnsupportedScheme()
    {
        $this->expectException(UnsupportedSchemeException::class);
        $this->expectExceptionMessage('The "unsupported" scheme is not supported.');

        Transport::fromDsn('unsupported://default');
    }
}
