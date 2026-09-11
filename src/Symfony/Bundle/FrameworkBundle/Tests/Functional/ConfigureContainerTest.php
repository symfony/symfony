<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\ConfigureContainer\Greeter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ConfigureContainerTest extends AbstractWebTestCase
{
    private static array $kernelOptions = [];

    public function testTheServiceIsReplacedOnTheFirstRequest()
    {
        $calls = 0;
        $client = $this->createClient(['test_case' => 'ConfigureContainer', 'configure_container' => self::greeterConfigurator('fake', $calls)]);

        $client->request('GET', '/configure_container');

        $this->assertStringContainsString('<p>fake</p>', $client->getResponse()->getContent());
        $this->assertSame(1, $calls);
    }

    public function testTheServiceIsStillReplacedAfterAReboot()
    {
        $calls = 0;
        $client = $this->createClient(['test_case' => 'ConfigureContainer', 'configure_container' => self::greeterConfigurator('fake', $calls)]);

        $client->request('GET', '/configure_container');
        $this->assertStringContainsString('<p>fake</p>', $client->getResponse()->getContent());

        $client->request('GET', '/configure_container');
        $this->assertStringContainsString('<p>fake</p>', $client->getResponse()->getContent());
        $this->assertSame(2, $calls);
    }

    public function testTheServiceIsStillReplacedAfterFollowingALink()
    {
        $calls = 0;
        $client = $this->createClient(['test_case' => 'ConfigureContainer', 'configure_container' => self::greeterConfigurator('fake', $calls)]);

        $crawler = $client->request('GET', '/configure_container');
        $client->click($crawler->selectLink('Next')->link());

        $this->assertStringContainsString('<p>fake</p>', $client->getResponse()->getContent());
        $this->assertSame(2, $calls);
    }

    public function testTheConfiguratorIsNotReappliedWhenRebootIsDisabled()
    {
        $calls = 0;
        $client = $this->createClient(['test_case' => 'ConfigureContainer', 'configure_container' => self::greeterConfigurator('fake', $calls)]);
        $client->disableReboot();

        $client->request('GET', '/configure_container');
        $client->request('GET', '/configure_container');

        $this->assertStringContainsString('<p>fake</p>', $client->getResponse()->getContent());
        $this->assertSame(1, $calls);
    }

    public function testTheOptionIsNotForwardedToCreateKernel()
    {
        $calls = 0;
        $client = $this->createClient(['test_case' => 'ConfigureContainer', 'configure_container' => self::greeterConfigurator('fake', $calls)]);

        $this->assertArrayNotHasKey('configure_container', self::$kernelOptions);
        $this->assertSame('ConfigureContainer', self::$kernelOptions['test_case']);
        $this->assertSame('fake', $client->getContainer()->get(Greeter::class)->greet());
    }

    public function testANonClosureIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "configure_container" option must be a closure, "string" given.');

        $this->createClient(['test_case' => 'ConfigureContainer', 'configure_container' => 'not a closure']);
    }

    public function testInsulatingRequestsIsRejected()
    {
        $calls = 0;
        $client = $this->createClient(['test_case' => 'ConfigureContainer', 'configure_container' => self::greeterConfigurator('fake', $calls)]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot insulate requests when a container configurator is registered, as closures cannot be passed to the insulated process.');

        $client->insulate();
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        self::$kernelOptions = $options;

        return parent::createKernel($options);
    }

    private static function greeterConfigurator(string $greeting, int &$calls): \Closure
    {
        return static function (ContainerInterface $container) use ($greeting, &$calls) {
            ++$calls;
            $container->set(Greeter::class, new Greeter($greeting));
        };
    }
}
