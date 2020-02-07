<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * WebTestCase is the base class for functional tests.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class WebTestCase extends KernelTestCase
{
    use WebTestAssertionsTrait;

    protected function tearDown(): void
    {
        parent::tearDown();
        self::getClient(null);
    }

    /**
     * Creates a KernelBrowser.
     *
     * @param array $options An array of options to pass to the createKernel method
     * @param array $server  An array of server parameters
     *
     * @return KernelBrowser A KernelBrowser instance
     */
    protected static function createClient(array $options = [], array $server = [])
    {
        if (static::$booted) {
            throw new \LogicException(sprintf('Booting the kernel before calling %s() is not supported, the kernel should only be booted once.', __METHOD__));
        }

        $kernel = static::bootKernel($options);

        try {
            $client = $kernel->getContainer()->get('test.client');
        } catch (ServiceNotFoundException $e) {
            if (class_exists(KernelBrowser::class)) {
                throw new \LogicException('You cannot create the client used in functional tests if the "framework.test" config is not set to true.');
            }
            throw new \LogicException('You cannot create the client used in functional tests if the BrowserKit component is not available. Try running "composer require symfony/browser-kit"');
        }

        $client->setServerParameters($server);

        return self::getClient($client);
    }

    /**
     * Logouts a user.
     */
    public function logout(KernelBrowser $browser, string $firewallName = 'main'): void
    {
        $context = $this->getFirewallContext($browser, $firewallName);

        $browser->getContainer()->get('session')->remove('_security_'.$context);
    }

    public function login(
        KernelBrowser $browser,
        $user,
        array $roles = [],
        string $firewallName = 'main'
    ): TokenInterface {
        if (!class_exists(UsernamePasswordToken::class)) {
            throw new \RuntimeException('You must install the "symfony/security-core" component to use this feature.');
        }

        if ($user instanceof UserInterface) {
            $roles = array_merge($user->getRoles(), $roles);
        }
        $token = $this->getLoginToken($browser, $user, $roles, $firewallName);

        $this->authenticateToken($browser, $token, $firewallName);

        return $token;
    }

    protected function getLoginToken(KernelBrowser $browser, $user, array $roles = [], string $firewallName = 'main'): TokenInterface
    {
        return new UsernamePasswordToken($user, null, $firewallName, $roles);
    }

    protected function authenticateToken(KernelBrowser $browser, TokenInterface $token, string $firewallName = 'main'): void
    {
        $context = $this->getFirewallContext($browser, $firewallName);

        $session = $browser->getContainer()->get('session');
        $session->set('_security_'.$context, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $browser->getCookieJar()->set($cookie);
    }

    private function getFirewallContext(KernelBrowser $browser, string $firewallName = 'main'): string
    {
        $config = sprintf('security.firewall.map.config.%s', $firewallName);

        if (!$browser->getContainer()->has($config)) {
            throw new \RuntimeException(sprintf('Firewall "%s" does not exists.', $firewallName));
        }

        return $browser->getContainer()->get($config)->getContext();
    }
}
