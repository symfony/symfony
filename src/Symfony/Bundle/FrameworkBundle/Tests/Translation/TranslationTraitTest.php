<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Translation;

use Symfony\Bundle\FrameworkBundle\Translation\TranslationTrait;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TranslationTraitTest extends TestCase
{
    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage You can not use the trans method if translator is disabled.
     */
    public function testTransWithEmptyContainer()
    {
        $container = $this->getEmptyContainer();

        $controller = new TestController();
        $controller->setContainer($container);

        $controller->trans('foo');
    }

    public function testTrans()
    {
        $container = $this->getContainerWithTranslator();

        $controller = new TestController();
        $controller->setContainer($container);

        $container->get('translator')->expects($this->once())->method('trans');

        $controller->trans('foo');
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage You can not use the transChoice method if translator is disabled.
     */
    public function testTransChoiceWithEmptyContainer()
    {
        $container = $this->getEmptyContainer();

        $controller = new TranslationController();
        $controller->setContainer($container);

        $controller->transChoice('foo', 2);
    }

    public function testTransChoice()
    {
        $container = $this->getContainerWithTranslator();

        $controller = new TranslationController();
        $controller->setContainer($container);

        $container->get('translator')->expects($this->once())->method('transChoice');

        $controller->transChoice('foo', 2);
    }

    /**
     * @return ContainerInterface
     */
    private function getContainerWithTranslator()
    {
        $translator = $this->getMockBuilder('Symfony\Component\Translation\Translator')->disableOriginalConstructor()->getMock();
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container
            ->expects($this->once())
            ->method('has')
            ->with('translator')
            ->will($this->returnValue(true));

        $container
            ->expects($this->exactly(2))
            ->method('get')
            ->with('translator')
            ->will($this->returnValue($translator));

        return $container;
    }

    /**
     * @return ContainerInterface
     */
    private function getEmptyContainer()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container
            ->expects($this->once())
            ->method('has')
            ->with('translator')
            ->will($this->returnValue(false));

        return $container;
    }
}

class TestController implements ContainerAwareInterface
{
    /** @var ContainerInterface */
    protected $container;

    use TranslationTrait;

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }
}
