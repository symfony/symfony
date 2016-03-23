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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CheckAmbiguousReferencePassTest extends \PHPUnit_Framework_TestCase
{
    /** @var ContainerBuilder */
    private $container;

    protected function setUp()
    {
        $this->container = new ContainerBuilder();
        require_once __DIR__.'/../Fixtures/includes/classes2.php';
    }

    /**
     * @expectedException Symfony\Component\DependencyInjection\Exception\AmbiguousReferenceException
     * @expectedExceptionMessage Ambiguous services for class "Symfony\Component\DependencyInjection\Tests\Compiler\ClassNamedServices\E". You should use concrete service name instead of class: "foo", "bar"
     */
    public function testThrowExceptionForAmbiguousDefinitionInArguments()
    {
        $container = $this->container;
        $container->register('foo', ClassNamedServices\E::class);
        $container->register('bar', ClassNamedServices\E::class);

        $definition = $container->register('baz', ClassNamedServices\A::class);
        $definition->setArguments(array(new Reference(ClassNamedServices\E::class)));

        $container->compile();
    }
}
