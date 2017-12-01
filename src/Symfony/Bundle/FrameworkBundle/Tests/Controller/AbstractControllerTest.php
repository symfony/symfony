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

class AbstractControllerTest extends ControllerTraitTest
{
    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @expectedExceptionMessage You have requested a non-existent service "unknown". Did you forget to add it to "Symfony\Bundle\FrameworkBundle\Tests\Controller\TestAbstractController::getSubscribedServices()"?
     */
    public function testServiceNotFound()
    {
        $controller = $this->createController();
        $controller->setContainer(new Container());
        $controller->serviceNotFoundAction();
    }

    protected function createController()
    {
        return new TestAbstractController();
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

    public function fooAction()
    {
    }

    public function serviceNotFoundAction()
    {
        $this->get('unknown');
    }
}
