<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddSessionDomainConstraintPass;
use Symphony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\HttpFoundation\Request;

class AddSessionDomainConstraintPassTest extends TestCase
{
    public function testSessionCookie()
    {
        $container = $this->createContainer(array('cookie_domain' => '.symphony.com.', 'cookie_secure' => true));

        $utils = $container->get('security.http_utils');
        $request = Request::create('/', 'get');

        $this->assertTrue($utils->createRedirectResponse($request, 'https://symphony.com/blog')->isRedirect('https://symphony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.symphony.com/blog')->isRedirect('https://www.symphony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://localhost/foo')->isRedirect('https://localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.localhost/foo')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://symphony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://pirate.com/foo')->isRedirect('http://localhost/'));
    }

    public function testSessionNoDomain()
    {
        $container = $this->createContainer(array('cookie_secure' => true));

        $utils = $container->get('security.http_utils');
        $request = Request::create('/', 'get');

        $this->assertTrue($utils->createRedirectResponse($request, 'https://symphony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.symphony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://localhost/foo')->isRedirect('https://localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.localhost/foo')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://symphony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://pirate.com/foo')->isRedirect('http://localhost/'));
    }

    public function testSessionNoSecure()
    {
        $container = $this->createContainer(array('cookie_domain' => '.symphony.com.'));

        $utils = $container->get('security.http_utils');
        $request = Request::create('/', 'get');

        $this->assertTrue($utils->createRedirectResponse($request, 'https://symphony.com/blog')->isRedirect('https://symphony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.symphony.com/blog')->isRedirect('https://www.symphony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://localhost/foo')->isRedirect('https://localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.localhost/foo')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://symphony.com/blog')->isRedirect('http://symphony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://pirate.com/foo')->isRedirect('http://localhost/'));
    }

    public function testSessionNoSecureAndNoDomain()
    {
        $container = $this->createContainer(array());

        $utils = $container->get('security.http_utils');
        $request = Request::create('/', 'get');

        $this->assertTrue($utils->createRedirectResponse($request, 'https://symphony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.symphony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://localhost/foo')->isRedirect('https://localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://localhost/foo')->isRedirect('http://localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.localhost/foo')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://symphony.com/blog')->isRedirect('http://localhost/'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://pirate.com/foo')->isRedirect('http://localhost/'));
    }

    public function testNoSession()
    {
        $container = $this->createContainer(null);

        $utils = $container->get('security.http_utils');
        $request = Request::create('/', 'get');

        $this->assertTrue($utils->createRedirectResponse($request, 'https://symphony.com/blog')->isRedirect('https://symphony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.symphony.com/blog')->isRedirect('https://www.symphony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://localhost/foo')->isRedirect('https://localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'https://www.localhost/foo')->isRedirect('https://www.localhost/foo'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://symphony.com/blog')->isRedirect('http://symphony.com/blog'));
        $this->assertTrue($utils->createRedirectResponse($request, 'http://pirate.com/foo')->isRedirect('http://pirate.com/foo'));
    }

    private function createContainer($sessionStorageOptions)
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles_metadata', array());
        $container->setParameter('kernel.cache_dir', __DIR__);
        $container->setParameter('kernel.charset', 'UTF-8');
        $container->setParameter('kernel.container_class', 'cc');
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.project_dir', __DIR__);
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
        $ext->load(array('framework' => array('csrf_protection' => false)), $container);

        $ext = new SecurityExtension();
        $ext->load($config, $container);

        $pass = new AddSessionDomainConstraintPass();
        $pass->process($container);

        return $container;
    }
}
