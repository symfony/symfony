<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Annotation as DI;
use Symfony\Component\DependencyInjection\Compiler\ServiceAnnotationsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class DefinitionAnnotationPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcessReadsServiceAnnotation()
    {
        $container = new ContainerBuilder();

        $container->register(ClassWithManyServiceOptions::class, ClassWithManyServiceOptions::class)
            ->addTag('annotated');

        $pass = new ServiceAnnotationsPass();
        $pass->process($container);

        $definition = $container->getDefinition(ClassWithManyServiceOptions::class);
        $this->assertFalse($definition->isShared());
        $this->assertFalse($definition->isPublic());
        $this->assertTrue($definition->isSynthetic());
        $this->assertTrue($definition->isAbstract());
        $this->assertTrue($definition->isLazy());
    }

    public function testNonTaggedClassesAreNotChanged()
    {
        $container = new ContainerBuilder();

        // register the service, but don't mark it as annotated
        $container->register(ClassWithManyServiceOptions::class, ClassWithManyServiceOptions::class)
            // redundant, but here for clarity
            ->setShared(true);

        $pass = new ServiceAnnotationsPass();
        $pass->process($container);

        $definition = $container->getDefinition(ClassWithManyServiceOptions::class);
        // the annotation that sets shared to false is not ready!
        $this->assertTrue($definition->isShared());
    }

    public function testBasicConstructorArgumentAnnotations()
    {
        $container = new ContainerBuilder();

        $container->register(ClassWithConstructorArgAnnotations::class, ClassWithConstructorArgAnnotations::class)
            ->addTag('annotated');

        $pass = new ServiceAnnotationsPass();
        $pass->process($container);

        $definition = $container->getDefinition(ClassWithConstructorArgAnnotations::class);
        $this->assertEquals(new Reference('foo_service'), $definition->getArgument(0));
        $this->assertEquals('%bar_parameter%', $definition->getArgument(1));
        $this->assertEquals('scalar value', $definition->getArgument(2));
    }
}

/**
 * @DI\Service(
 *     shared=false,
 *     public=false,
 *     synthetic=true,
 *     abstract=true,
 *     lazy=true
 * )
 */
class ClassWithManyServiceOptions
{
}

class ClassWithConstructorArgAnnotations
{
    /**
     * Annotations are purposefully out of order!
     *
     * @DI\Argument(name="thirdArg", value="scalar value")
     * @DI\Argument(name="firstArg", id="foo_service")
     * @DI\Argument(name="secondArg", value="%bar_parameter%")
     */
    public function __construct($firstArg, $secondArg, $thirdArg)
    {
    }
}
