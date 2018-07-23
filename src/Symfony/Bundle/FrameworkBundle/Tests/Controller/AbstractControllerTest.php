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

    /**
     * This test protects the default subscribed core services against accidental modification.
     */
    public function testSubscribedServices()
    {
        $subscribed = AbstractController::getSubscribedServices();
        $expectedServices = array(
            'router' => '?Symfony\\Component\\Routing\\RouterInterface',
            'request_stack' => '?Symfony\\Component\\HttpFoundation\\RequestStack',
            'http_kernel' => '?Symfony\\Component\\HttpKernel\\HttpKernelInterface',
            'serializer' => '?Symfony\\Component\\Serializer\\SerializerInterface',
            'session' => '?Symfony\\Component\\HttpFoundation\\Session\\SessionInterface',
            'security.authorization_checker' => '?Symfony\\Component\\Security\\Core\\Authorization\\AuthorizationCheckerInterface',
            'templating' => '?Symfony\\Component\\Templating\\EngineInterface',
            'twig' => '?Twig\\Environment',
            'doctrine' => '?Doctrine\\Common\\Persistence\\ManagerRegistry',
            'form.factory' => '?Symfony\\Component\\Form\\FormFactoryInterface',
            'parameter_bag' => '?Symfony\\Component\\DependencyInjection\\ParameterBag\\ContainerBagInterface',
            'message_bus' => '?Symfony\\Component\\Messenger\\MessageBusInterface',
            'security.token_storage' => '?Symfony\\Component\\Security\\Core\\Authentication\\Token\\Storage\\TokenStorageInterface',
            'security.csrf.token_manager' => '?Symfony\\Component\\Security\\Csrf\\CsrfTokenManagerInterface',
        );

        $this->assertEquals($expectedServices, $subscribed, 'Subscribed core services in AbstractController have changed');
    }

    public function testGetParameter()
    {
        if (!class_exists(ContainerBag::class)) {
            $this->markTestSkipped('ContainerBag class does not exist');
        }

        $container = new Container(new FrozenParameterBag(array('foo' => 'bar')));
        $container->set('parameter_bag', new ContainerBag($container));

        $controller = $this->createController();
        $controller->setContainer($container);

        $this->assertSame('bar', $controller->getParameter('foo'));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @expectedExceptionMessage TestAbstractController::getParameter()" method is missing a parameter bag
     */
    public function testMissingParameterBag()
    {
        $container = new Container();

        $controller = $this->createController();
        $controller->setContainer($container);

        $controller->getParameter('foo');
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
