<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\Authenticator\CustomAuthenticator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class XmlCustomAuthenticatorTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testCustomAuthenticatorElementUnderSecurityNamespace()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->register('cache.system', \stdClass::class);

        $security = new SecurityExtension();
        $security->addAuthenticatorFactory(new CustomAuthenticator());
        $container->registerExtension($security);

        $this->expectDeprecation('Since symfony/security-bundle 7.2: Custom authenticators must now be namespaced; please update your security configuration "custom" tag.');
        (new XmlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/xml')))->load('custom_authenticator_under_security_namespace.xml');

        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();
    }

    public function testCustomAuthenticatorElementUnderOwnNamespace()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->register('cache.system', \stdClass::class);

        $security = new SecurityExtension();
        $security->addAuthenticatorFactory(new CustomAuthenticator());
        $container->registerExtension($security);

        (new XmlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/xml')))->load('custom_authenticator_under_own_namespace.xml');

        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        $this->addToAssertionCount(1);
    }
}
