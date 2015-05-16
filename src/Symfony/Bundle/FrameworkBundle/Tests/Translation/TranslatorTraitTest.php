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

use Symfony\Bundle\FrameworkBundle\Translation\TranslatorTrait;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TranslatorTraitTest extends TestCase
{
    public function testTrans()
    {
        $container = $this->getContainerWithTranslator();

        $controller = new TestController();
        $controller->setContainer($container);

        $container->get('translator')->expects($this->once())->method('trans');

        $controller->trans('foo');
    }

    public function testTransChoice()
    {
        $container = $this->getContainerWithTranslator();

        $controller = new TestController();
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
            ->expects($this->exactly(2))
            ->method('get')
            ->with('translator')
            ->will($this->returnValue($translator));

        return $container;
    }
}

class TestController implements ContainerAwareInterface
{
    /** @var ContainerInterface */
    protected $container;

    use TranslatorTrait;

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }
}
