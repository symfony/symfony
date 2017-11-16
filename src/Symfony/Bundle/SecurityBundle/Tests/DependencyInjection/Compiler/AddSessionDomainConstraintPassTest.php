<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddSessionDomainConstraintPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

class AddSessionDomainConstraintPassTest extends TestCase
{
    public function testSessionCookie()
    {
        $container = $this->createContainer(array('cookie_domain' => '.symfony.com.', 'cookie_secure' => true));

        $utils = $container->get('security.http_utils');
        $request = Request::create('/', 'get');

        $this->assertTrue($utils->createRedirectResponse($request, 'https://symfony.com/blog')->isRedirect('https://symfony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.symfony.com/blog')->isRedirect('https://www.symfony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://localhost/foo')->isRedirect('https://localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.localhost/foo')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://symfony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://pirate.com/foo')->isRedirect('http://localhost/'));
    }

    public function testSessionNoDomain()
    {
        $container = $this->createContainer(array('cookie_secure' => true));

        $utils = $container->get('security.http_utils');
        $request = Request::create('/', 'get');

        $this->assertTrue($utils->createRedirectResponse($request, 'https://symfony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.symfony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://localhost/foo')->isRedirect('https://localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.localhost/foo')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://symfony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://pirate.com/foo')->isRedirect('http://localhost/'));
    }

    public function testSessionNoSecure()
    {
        $container = $this->createContainer(array('cookie_domain' => '.symfony.com.'));

        $utils = $container->get('security.http_utils');
        $request = Request::create('/', 'get');

        $this->assertTrue($utils->createRedirectResponse($request, 'https://symfony.com/blog')->isRedirect('https://symfony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.symfony.com/blog')->isRedirect('https://www.symfony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://localhost/foo')->isRedirect('https://localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.localhost/foo')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://symfony.com/blog')->isRedirect('http://symfony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://pirate.com/foo')->isRedirect('http://localhost/'));
    }

    public function testSessionNoSecureAndNoDomain()
    {
        $container = $this->createContainer(array());

        $utils = $container->get('security.http_utils');
        $request = Request::create('/', 'get');

        $this->assertTrue($utils->createRedirectResponse($request, 'https://symfony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.symfony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://localhost/foo')->isRedirect('https://localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://localhost/foo')->isRedirect('http://localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.localhost/foo')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://symfony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://pirate.com/foo')->isRedirect('http://localhost/'));
    }

    public function testNoSession()
    {
        $container = $this->createContainer(null);

        $utils = $container->get('security.http_utils');
        $request = Request::create('/', 'get');

        $this->assertTrue($utils->createRedirectResponse($request, 'https://symfony.com/blog')->isRedirect('https://symfony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.symfony.com/blog')->isRedirect('https://www.symfony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://localhost/foo')->isRedirect('https://localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.localhost/foo')->isRedirect('https://www.localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://symfony.com/blog')->isRedirect('http://symfony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://pirate.com/foo')->isRedirect('http://pirate.com/foo'));
    }

    private function createContainer($sessionStorageOptions)
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', __DIR__);
        $container->setParameter('kernel.charset', 'UTF-8');
        $container->setParameter('kernel.container_class', 'cc');
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.root_dir', __DIR__);
        $container->setParameter('kernel.secret', __DIR__);
        if (null !== $sessionStorageOptions) {
            $container->setParameter('session.storage.options', $sessionStorageOptions);
        }
        $container->setParameter('request_listener.http_port', 80);
        $container->setParameter('request_listener.https_port', 443);

        $config = array(
            'security' => array(
                'providers' => array('some_provider' => array('id' => 'foo')),
                'firewalls' => array('some_firewall' => array('security' => false)),
            ),
        );

        $ext = new FrameworkExtension();
        $ext->load(array(), $container);

        $ext = new SecurityExtension();
        $ext->load($config, $container);

        (new AddSessionDomainConstraintPass())->process($container);

        return $container;
    }
}
