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

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Serializer\SerializerInterface;

class ControllerTest extends TestCase
{
    public function testForward()
    {
        $request = Request::create('/');
        $request->setLocale('fr');
        $request->setRequestFormat('xml');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $kernel->expects($this->once())->method('handle')->will($this->returnCallback(function (Request $request) {
            return new Response($request->getRequestFormat().'--'.$request->getLocale());
        }));

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('get')->will($this->returnValue($requestStack));
        $container->expects($this->at(1))->method('get')->will($this->returnValue($kernel));

        $controller = new TestController();
        $controller->setContainer($container);

        $response = $controller->forward('a_controller');
        $this->assertEquals('xml--fr', $response->getContent());
    }

    public function testGetUser()
    {
        $user = new User('user', 'pass');
        $token = new UsernamePasswordToken($user, 'pass', 'default', array('ROLE_USER'));

        $controller = new TestController();
        $controller->setContainer($this->getContainerWithTokenStorage($token));

        $this->assertSame($controller->getUser(), $user);
    }

    public function testGetUserAnonymousUserConvertedToNull()
    {
        $token = new AnonymousToken('default', 'anon.');

        $controller = new TestController();
        $controller->setContainer($this->getContainerWithTokenStorage($token));

        $this->assertNull($controller->getUser());
    }

    public function testGetUserWithEmptyTokenStorage()
    {
        $controller = new TestController();
        $controller->setContainer($this->getContainerWithTokenStorage(null));

        $this->assertNull($controller->getUser());
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The SecurityBundle is not registered in your application.
     */
    public function testGetUserWithEmptyContainer()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container
            ->expects($this->once())
            ->method('has')
            ->with('security.token_storage')
            ->will($this->returnValue(false));

        $controller = new TestController();
        $controller->setContainer($container);

        $controller->getUser();
    }

    /**
     * @param $token
     *
     * @return ContainerInterface
     */
    private function getContainerWithTokenStorage($token = null)
    {
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage')->getMock();
        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container
            ->expects($this->once())
            ->method('has')
            ->with('security.token_storage')
            ->will($this->returnValue(true));

        $container
            ->expects($this->once())
            ->method('get')
            ->with('security.token_storage')
            ->will($this->returnValue($tokenStorage));

        return $container;
    }

    public function testJson()
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container
            ->expects($this->once())
            ->method('has')
            ->with('serializer')
            ->will($this->returnValue(false));

        $controller = new TestController();
        $controller->setContainer($container);

        $response = $controller->json(array());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('[]', $response->getContent());
    }

    public function testJsonWithSerializer()
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container
            ->expects($this->once())
            ->method('has')
            ->with('serializer')
            ->will($this->returnValue(true));

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(array(), 'json', array('json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS))
            ->will($this->returnValue('[]'));

        $container
            ->expects($this->once())
            ->method('get')
            ->with('serializer')
            ->will($this->returnValue($serializer));

        $controller = new TestController();
        $controller->setContainer($container);

        $response = $controller->json(array());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('[]', $response->getContent());
    }

    public function testJsonWithSerializerContextOverride()
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container
            ->expects($this->once())
            ->method('has')
            ->with('serializer')
            ->will($this->returnValue(true));

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(array(), 'json', array('json_encode_options' => 0, 'other' => 'context'))
            ->will($this->returnValue('[]'));

        $container
            ->expects($this->once())
            ->method('get')
            ->with('serializer')
            ->will($this->returnValue($serializer));

        $controller = new TestController();
        $controller->setContainer($container);

        $response = $controller->json(array(), 200, array(), array('json_encode_options' => 0, 'other' => 'context'));
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('[]', $response->getContent());
        $response->setEncodingOptions(JSON_FORCE_OBJECT);
        $this->assertEquals('{}', $response->getContent());
    }

    public function testIsGranted()
    {
        $authorizationChecker = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface')->getMock();
        $authorizationChecker->expects($this->once())->method('isGranted')->willReturn(true);

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('has')->will($this->returnValue(true));
        $container->expects($this->at(1))->method('get')->will($this->returnValue($authorizationChecker));

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertTrue($controller->isGranted('foo'));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function testdenyAccessUnlessGranted()
    {
        $authorizationChecker = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface')->getMock();
        $authorizationChecker->expects($this->once())->method('isGranted')->willReturn(false);

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('has')->will($this->returnValue(true));
        $container->expects($this->at(1))->method('get')->will($this->returnValue($authorizationChecker));

        $controller = new TestController();
        $controller->setContainer($container);

        $controller->denyAccessUnlessGranted('foo');
    }

    public function testRenderViewTwig()
    {
        $twig = $this->getMockBuilder('\Twig_Environment')->disableOriginalConstructor()->getMock();
        $twig->expects($this->once())->method('render')->willReturn('bar');

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('has')->will($this->returnValue(false));
        $container->expects($this->at(1))->method('has')->will($this->returnValue(true));
        $container->expects($this->at(2))->method('get')->will($this->returnValue($twig));

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertEquals('bar', $controller->renderView('foo'));
    }

    public function testRenderTwig()
    {
        $twig = $this->getMockBuilder('\Twig_Environment')->disableOriginalConstructor()->getMock();
        $twig->expects($this->once())->method('render')->willReturn('bar');

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('has')->will($this->returnValue(false));
        $container->expects($this->at(1))->method('has')->will($this->returnValue(true));
        $container->expects($this->at(2))->method('get')->will($this->returnValue($twig));

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertEquals('bar', $controller->render('foo')->getContent());
    }

    public function testStreamTwig()
    {
        $twig = $this->getMockBuilder('\Twig_Environment')->disableOriginalConstructor()->getMock();

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('has')->will($this->returnValue(false));
        $container->expects($this->at(1))->method('has')->will($this->returnValue(true));
        $container->expects($this->at(2))->method('get')->will($this->returnValue($twig));

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\StreamedResponse', $controller->stream('foo'));
    }

    public function testRedirectToRoute()
    {
        $router = $this->getMockBuilder('Symfony\Component\Routing\RouterInterface')->getMock();
        $router->expects($this->once())->method('generate')->willReturn('/foo');

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('get')->will($this->returnValue($router));

        $controller = new TestController();
        $controller->setContainer($container);
        $response = $controller->redirectToRoute('foo');

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $response);
        $this->assertSame('/foo', $response->getTargetUrl());
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testAddFlash()
    {
        $flashBag = new FlashBag();
        $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\Session')->getMock();
        $session->expects($this->once())->method('getFlashBag')->willReturn($flashBag);

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('has')->will($this->returnValue(true));
        $container->expects($this->at(1))->method('get')->will($this->returnValue($session));

        $controller = new TestController();
        $controller->setContainer($container);
        $controller->addFlash('foo', 'bar');

        $this->assertSame(array('bar'), $flashBag->get('foo'));
    }

    public function testCreateAccessDeniedException()
    {
        $controller = new TestController();

        $this->assertInstanceOf('Symfony\Component\Security\Core\Exception\AccessDeniedException', $controller->createAccessDeniedException());
    }

    public function testIsCsrfTokenValid()
    {
        $tokenManager = $this->getMockBuilder('Symfony\Component\Security\Csrf\CsrfTokenManagerInterface')->getMock();
        $tokenManager->expects($this->once())->method('isTokenValid')->willReturn(true);

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('has')->will($this->returnValue(true));
        $container->expects($this->at(1))->method('get')->will($this->returnValue($tokenManager));

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertTrue($controller->isCsrfTokenValid('foo', 'bar'));
    }

    public function testGenerateUrl()
    {
        $router = $this->getMockBuilder('Symfony\Component\Routing\RouterInterface')->getMock();
        $router->expects($this->once())->method('generate')->willReturn('/foo');

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('get')->will($this->returnValue($router));

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertEquals('/foo', $controller->generateUrl('foo'));
    }

    public function testRedirect()
    {
        $controller = new TestController();
        $response = $controller->redirect('http://dunglas.fr', 301);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $response);
        $this->assertSame('http://dunglas.fr', $response->getTargetUrl());
        $this->assertSame(301, $response->getStatusCode());
    }

    public function testRenderViewTemplating()
    {
        $templating = $this->getMockBuilder('Symfony\Bundle\FrameworkBundle\Templating\EngineInterface')->getMock();
        $templating->expects($this->once())->method('render')->willReturn('bar');

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('has')->willReturn(true);
        $container->expects($this->at(1))->method('get')->will($this->returnValue($templating));

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertEquals('bar', $controller->renderView('foo'));
    }

    public function testRenderTemplating()
    {
        $templating = $this->getMockBuilder('Symfony\Bundle\FrameworkBundle\Templating\EngineInterface')->getMock();
        $templating->expects($this->once())->method('renderResponse')->willReturn(new Response('bar'));

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('has')->willReturn(true);
        $container->expects($this->at(1))->method('get')->will($this->returnValue($templating));

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertEquals('bar', $controller->render('foo')->getContent());
    }

    public function testStreamTemplating()
    {
        $templating = $this->getMockBuilder('Symfony\Component\Routing\RouterInterface')->getMock();

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('has')->willReturn(true);
        $container->expects($this->at(1))->method('get')->will($this->returnValue($templating));

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\StreamedResponse', $controller->stream('foo'));
    }

    public function testCreateNotFoundException()
    {
        $controller = new TestController();

        $this->assertInstanceOf('Symfony\Component\HttpKernel\Exception\NotFoundHttpException', $controller->createNotFoundException());
    }

    public function testCreateForm()
    {
        $form = $this->getMockBuilder('Symfony\Component\Form\FormInterface')->getMock();

        $formFactory = $this->getMockBuilder('Symfony\Component\Form\FormFactoryInterface')->getMock();
        $formFactory->expects($this->once())->method('create')->willReturn($form);

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('get')->will($this->returnValue($formFactory));

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertEquals($form, $controller->createForm('foo'));
    }

    public function testCreateFormBuilder()
    {
        $formBuilder = $this->getMockBuilder('Symfony\Component\Form\FormBuilderInterface')->getMock();

        $formFactory = $this->getMockBuilder('Symfony\Component\Form\FormFactoryInterface')->getMock();
        $formFactory->expects($this->once())->method('createBuilder')->willReturn($formBuilder);

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('get')->will($this->returnValue($formFactory));

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertEquals($formBuilder, $controller->createFormBuilder('foo'));
    }

    public function testGetDoctrine()
    {
        $doctrine = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')->getMock();

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('has')->will($this->returnValue(true));
        $container->expects($this->at(1))->method('get')->will($this->returnValue($doctrine));

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertEquals($doctrine, $controller->getDoctrine());
    }
}

class TestController extends Controller
{
    public function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return parent::generateUrl($route, $parameters, $referenceType);
    }

    public function redirect($url, $status = 302)
    {
        return parent::redirect($url, $status);
    }

    public function forward($controller, array $path = array(), array $query = array())
    {
        return parent::forward($controller, $path, $query);
    }

    public function getUser()
    {
        return parent::getUser();
    }

    public function json($data, $status = 200, $headers = array(), $context = array())
    {
        return parent::json($data, $status, $headers, $context);
    }

    public function isGranted($attributes, $object = null)
    {
        return parent::isGranted($attributes, $object);
    }

    public function denyAccessUnlessGranted($attributes, $object = null, $message = 'Access Denied.')
    {
        parent::denyAccessUnlessGranted($attributes, $object, $message);
    }

    public function redirectToRoute($route, array $parameters = array(), $status = 302)
    {
        return parent::redirectToRoute($route, $parameters, $status);
    }

    public function addFlash($type, $message)
    {
        parent::addFlash($type, $message);
    }

    public function isCsrfTokenValid($id, $token)
    {
        return parent::isCsrfTokenValid($id, $token);
    }

    public function renderView($view, array $parameters = array())
    {
        return parent::renderView($view, $parameters);
    }

    public function render($view, array $parameters = array(), Response $response = null)
    {
        return parent::render($view, $parameters, $response);
    }

    public function stream($view, array $parameters = array(), StreamedResponse $response = null)
    {
        return parent::stream($view, $parameters, $response);
    }

    public function createNotFoundException($message = 'Not Found', \Exception $previous = null)
    {
        return parent::createNotFoundException($message, $previous);
    }

    public function createAccessDeniedException($message = 'Access Denied.', \Exception $previous = null)
    {
        return parent::createAccessDeniedException($message, $previous);
    }

    public function createForm($type, $data = null, array $options = array())
    {
        return parent::createForm($type, $data, $options);
    }

    public function createFormBuilder($data = null, array $options = array())
    {
        return parent::createFormBuilder($data, $options);
    }

    public function getDoctrine()
    {
        return parent::getDoctrine();
    }
}
