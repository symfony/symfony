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

use Doctrine\Common\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Controller\UseControllerTraitController;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @requires PHP 7
 */
class ControllerTraitTest extends PHPUnitTestCase
{
    public function testGenerateUrl()
    {
        $router = $this->getMockBuilder(RouterInterface::class)->getMock();
        $router->expects($this->once())->method('generate')->willReturn('/foo');

        $controller = new UseControllerTraitController();
        $controller->setRouter($router);

        $this->assertEquals('/foo', $controller->generateUrl('foo'));
    }

    public function testForward()
    {
        $request = Request::create('/');
        $request->setLocale('fr');
        $request->setRequestFormat('xml');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $httpKernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $httpKernel->expects($this->once())->method('handle')->will($this->returnCallback(function (Request $request) {
            return new Response($request->getRequestFormat().'--'.$request->getLocale());
        }));

        $controller = new UseControllerTraitController();
        $controller->setRequestStack($requestStack);
        $controller->setHttpKernel($httpKernel);

        $response = $controller->forward('a_controller');
        $this->assertEquals('xml--fr', $response->getContent());
    }

    public function testRedirect()
    {
        $controller = new UseControllerTraitController();

        $response = $controller->redirect('http://example.com', 301);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://example.com', $response->getTargetUrl());
        $this->assertSame(301, $response->getStatusCode());
    }

    public function testRedirectToRoute()
    {
        $router = $this->getMockBuilder(RouterInterface::class)->getMock();
        $router->expects($this->once())->method('generate')->willReturn('/foo');

        $controller = new UseControllerTraitController();
        $controller->setRouter($router);

        $response = $controller->redirectToRoute('foo');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/foo', $response->getTargetUrl());
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testGetUser()
    {
        $user = new User('user', 'pass');

        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue(new UsernamePasswordToken($user, 'pass', 'default', array('ROLE_USER'))));

        $controller = new UseControllerTraitController();
        $controller->setTokenStorage($tokenStorage);

        $this->assertSame($controller->getUser(), $user);
    }

    public function testGetUserAnonymousUserConvertedToNull()
    {
        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue(new AnonymousToken('default', 'anon.')));

        $controller = new UseControllerTraitController();
        $controller->setTokenStorage($tokenStorage);

        $this->assertNull($controller->getUser());
    }

    public function testGetUserWithEmptyTokenStorage()
    {
        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue(null));

        $controller = new UseControllerTraitController();
        $controller->setTokenStorage($tokenStorage);

        $this->assertNull($controller->getUser());
    }

    public function testJsonWithSerializer()
    {
        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(array(), 'json', array('json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS))
            ->will($this->returnValue('[]'));

        $controller = new UseControllerTraitController();
        $controller->setSerializer($serializer);

        $response = $controller->json(array());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('[]', $response->getContent());
    }

    public function testJsonWithSerializerContextOverride()
    {
        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(array(), 'json', array('json_encode_options' => 0, 'other' => 'context'))
            ->will($this->returnValue('[]'));

        $controller = new UseControllerTraitController();
        $controller->setSerializer($serializer);

        $response = $controller->json(array(), 200, array(), array('json_encode_options' => 0, 'other' => 'context'));
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('[]', $response->getContent());
        $response->setEncodingOptions(JSON_FORCE_OBJECT);
        $this->assertEquals('{}', $response->getContent());
    }

    public function testAddFlash()
    {
        $flashBag = new FlashBag();

        $session = $this->getMockBuilder(Session::class)->getMock();
        $session->method('getFlashBag')->willReturn($flashBag);

        $controller = new UseControllerTraitController();
        $controller->setSession($session);

        $controller->addFlash('foo', 'bar');

        $this->assertSame(array('bar'), $flashBag->get('foo'));
    }

    public function testIsGranted()
    {
        $authorizationChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authorizationChecker->expects($this->once())->method('isGranted')->willReturn(true);

        $controller = new UseControllerTraitController();
        $controller->setAuthorizationChecker($authorizationChecker);

        $this->assertTrue($controller->isGranted('foo'));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function testDenyAccessUnlessGranted()
    {
        $authorizationChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authorizationChecker->expects($this->once())->method('isGranted')->willReturn(false);

        $controller = new UseControllerTraitController();
        $controller->setAuthorizationChecker($authorizationChecker);

        $controller->denyAccessUnlessGranted('foo');
    }

    public function testRenderView()
    {
        $twig = $this->getMockBuilder(\Twig_Environment::class)->disableOriginalConstructor()->getMock();
        $twig->expects($this->once())->method('render')->willReturn('bar');

        $controller = new UseControllerTraitController();
        $controller->setTwig($twig);

        $this->assertEquals('bar', $controller->renderView('foo'));
    }

    public function testRenderTwig()
    {
        $twig = $this->getMockBuilder(\Twig_Environment::class)->disableOriginalConstructor()->getMock();
        $twig->expects($this->once())->method('render')->willReturn('bar');

        $controller = new UseControllerTraitController();
        $controller->setTwig($twig);

        $this->assertEquals('bar', $controller->render('foo')->getContent());
    }

    public function testStreamTwig()
    {
        $twig = $this->getMockBuilder(\Twig_Environment::class)->disableOriginalConstructor()->getMock();

        $controller = new UseControllerTraitController();
        $controller->setTwig($twig);

        $this->assertInstanceOf(StreamedResponse::class, $controller->stream('foo'));
    }

    public function testCreateNotFoundException()
    {
        $controller = new UseControllerTraitController();

        $this->assertInstanceOf(NotFoundHttpException::class, $controller->createNotFoundException());
    }

    public function testCreateAccessDeniedException()
    {
        $controller = new UseControllerTraitController();

        $this->assertInstanceOf(AccessDeniedException::class, $controller->createAccessDeniedException());
    }

    public function testCreateForm()
    {
        $form = $this->getMockBuilder(FormInterface::class)->getMock();

        $formFactory = $this->getMockBuilder(FormFactoryInterface::class)->getMock();
        $formFactory->expects($this->once())->method('create')->willReturn($form);

        $controller = new UseControllerTraitController();
        $controller->setFormFactory($formFactory);

        $this->assertEquals($form, $controller->createForm('foo'));
    }

    public function testCreateFormBuilder()
    {
        $formBuilder = $this->getMockBuilder(FormBuilderInterface::class)->getMock();

        $formFactory = $this->getMockBuilder(FormFactoryInterface::class)->getMock();
        $formFactory->expects($this->once())->method('createBuilder')->willReturn($formBuilder);

        $controller = new UseControllerTraitController();
        $controller->setFormFactory($formFactory);

        $this->assertEquals($formBuilder, $controller->createFormBuilder('foo'));
    }

    public function testGetDoctrine()
    {
        $doctrine = $this->getMockBuilder(ManagerRegistry::class)->getMock();

        $controller = new UseControllerTraitController();
        $controller->setDoctrine($doctrine);

        $this->assertSame($doctrine, $controller->getDoctrine());
    }

    public function testIsCsrfTokenValid()
    {
        $csrfTokenManager = $this->getMockBuilder(CsrfTokenManagerInterface::class)->getMock();
        $csrfTokenManager->expects($this->once())->method('isTokenValid')->willReturn(true);

        $controller = new UseControllerTraitController();
        $controller->setCsrfTokenManager($csrfTokenManager);

        $this->assertTrue($controller->isCsrfTokenValid('foo', 'bar'));
    }
}
