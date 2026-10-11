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
use Symfony\Bridge\Twig\Extension\LogoutUrlExtension;
use Symfony\Bridge\Twig\Extension\SecurityExtension;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RegisterCsrfFeaturesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

class RegisterCsrfFeaturesPassTest extends TestCase
{
    public function testTheButtonsOfTheTwigExtensionsGetTheCsrfFieldAttributesOfForms()
    {
        $container = $this->createContainer();
        $container->setParameter('form.type_extension.csrf.field_attr', ['data-csrf' => 'on']);

        (new RegisterCsrfFeaturesPass())->process($container);

        $this->assertEquals(new Parameter('form.type_extension.csrf.field_attr'), $container->getDefinition('twig.extension.logout_url')->getArgument(1));
        $this->assertEquals(new Parameter('form.type_extension.csrf.field_attr'), $container->getDefinition('twig.extension.security')->getArgument(2));
    }

    public function testTheButtonsOfTheTwigExtensionsKeepTheirCsrfFieldAttributesWithoutFormCsrfProtection()
    {
        $container = $this->createContainer();

        (new RegisterCsrfFeaturesPass())->process($container);

        $this->assertSame(['data-controller' => 'csrf-protection'], $container->getDefinition('twig.extension.logout_url')->getArgument(1));
        $this->assertSame(['data-controller' => 'csrf-protection'], $container->getDefinition('twig.extension.security')->getArgument(2));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('twig.extension.logout_url', LogoutUrlExtension::class)
            ->setArguments([new Reference('security.logout_url_generator'), ['data-controller' => 'csrf-protection']]);
        $container->register('twig.extension.security', SecurityExtension::class)
            ->setArguments([null, null, ['data-controller' => 'csrf-protection']]);

        return $container;
    }
}
