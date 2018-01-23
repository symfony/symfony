<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

class AbstractControllerTest extends ControllerTraitTest
{
    protected function createController()
    {
        return new TestAbstractController();
    }

    public function testGetParameter()
    {
        $container = new Container(new FrozenParameterBag(array('foo' => 'bar')));

        $controller = $this->createController();
        $controller->setContainer($container);

        if (!class_exists(ContainerBag::class)) {
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('The "parameter_bag" service is not available. Try running "composer require dependency-injection:^4.1"');
        } else {
            $container->set('parameter_bag', new ContainerBag($container));
        }

        $this->assertSame('bar', $controller->getParameter('foo'));
    }
}

class TestAbstractController extends AbstractController
{
    use TestControllerTrait;

    private $throwOnUnexpectedService;

    public function __construct($throwOnUnexpectedService = true)
    {
        $this->throwOnUnexpectedService = $throwOnUnexpectedService;
    }

    public function setContainer(ContainerInterface $container)
    {
        if (!$this->throwOnUnexpectedService) {
            return parent::setContainer($container);
        }

        $expected = self::getSubscribedServices();

        foreach ($container->getServiceIds() as $id) {
            if ('service_container' === $id) {
                continue;
            }
            if (!isset($expected[$id])) {
                throw new \UnexpectedValueException(sprintf('Service "%s" is not expected, as declared by %s::getSubscribedServices()', $id, AbstractController::class));
            }
            $type = substr($expected[$id], 1);
            if (!$container->get($id) instanceof $type) {
                throw new \UnexpectedValueException(sprintf('Service "%s" is expected to be an instance of "%s", as declared by %s::getSubscribedServices()', $id, $type, AbstractController::class));
            }
        }

        return parent::setContainer($container);
    }

    public function getParameter(string $name)
    {
        return parent::getParameter($name);
    }

    public function fooAction()
    {
    }
}
