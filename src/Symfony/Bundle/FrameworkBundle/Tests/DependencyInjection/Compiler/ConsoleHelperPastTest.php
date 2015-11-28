<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ConsoleHelperPass;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ConsoleHelperPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcessAddsConsoleHelperIdsToHelperIdArray()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new ConsoleHelperPass());
        $container->setParameter('myhelper.class', 'Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\MyHelper');

        $definition = new Definition('%myhelper.class%');
        $definition->addTag('console.helper');
        $container->setDefinition('myhelper', $definition);

        $container->compile();

        $this->assertTrue($container->hasParameter('console.helper.ids'));
        $this->assertSame(array('myhelper'), $container->getParameter('console.helper.ids'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The service "myhelper" tagged "console.helper" must be public.
     */
    public function testProcessThrowsAnExceptionIfHelperServicesAreNotPublic()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new ConsoleHelperPass());

        $definition = new Definition('Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\MyHelper');
        $definition->addTag('console.helper');
        $definition->setPublic(false);
        $container->setDefinition('myhelper', $definition);

        $container->compile();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The service "myhelper" tagged "console.helper" must not be abstract.
     */
    public function testProcessThrowsAnExceptionIfHelperServicesAreAbstract()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new ConsoleHelperPass());

        $definition = new Definition('Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\MyHelper');
        $definition->addTag('console.helper');
        $definition->setAbstract(true);
        $container->setDefinition('myhelper', $definition);

        $container->compile();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The service "myhelper" tagged "console.helper" must implement "Symfony\Component\Console\Helper\HelperInterface".
     */
    public function testProcessThrowsAnExceptionIfTheHelperServiceDoesNotImplementHelperInterface()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new ConsoleHelperPass());

        $definition = new Definition('SplObjectStorage');
        $definition->addTag('console.helper');
        $container->setDefinition('myhelper', $definition);

        $container->compile();
    }
}

class MyHelper implements HelperInterface
{
    private $helpers;

    public function setHelperSet(HelperSet $set = null)
    {
        $this->helpers = $set;
    }

    public function getHelperSet()
    {
        return $this->helpers;
    }

    public function getName()
    {
        return 'console_helpers_pass_helper';
    }
}
