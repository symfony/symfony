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

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\WebLink\Link;
use Twig\Environment;

class AbstractControllerTest extends TestCase
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
        $expectedServices = [
            'router' => '?Symfony\\Component\\Routing\\RouterInterface',
            'request_stack' => '?Symfony\\Component\\HttpFoundation\\RequestStack',
            'http_kernel' => '?Symfony\\Component\\HttpKernel\\HttpKernelInterface',
            'serializer' => '?Symfony\\Component\\Serializer\\SerializerInterface',
            'session' => '?Symfony\\Component\\HttpFoundation\\Session\\SessionInterface',
            'security.authorization_checker' => '?Symfony\\Component\\Security\\Core\\Authorization\\AuthorizationCheckerInterface',
            'twig' => '?Twig\\Environment',
            'doctrine' => '?Doctrine\\Persistence\\ManagerRegistry',
            'form.factory' => '?Symfony\\Component\\Form\\FormFactoryInterface',
            'parameter_bag' => '?Symfony\\Component\\DependencyInjection\\ParameterBag\\ContainerBagInterface',
            'message_bus' => '?Symfony\\Component\\Messenger\\MessageBusInterface',
            'messenger.default_bus' => '?Symfony\\Component\\Messenger\\MessageBusInterface',
            'security.token_storage' => '?Symfony\\Component\\Security\\Core\\Authentication\\Token\\Storage\\TokenStorageInterface',
            'security.csrf.token_manager' => '?Symfony\\Component\\Security\\Csrf\\CsrfTokenManagerInterface',
        ];

        self::assertEquals($expectedServices, $subscribed, 'Subscribed core services in AbstractController have changed');
    }

    public function testGetParameter()
    {
        if (!class_exists(ContainerBag::class)) {
            self::markTestSkipped('ContainerBag class does not exist');
        }

        $container = new Container(new FrozenParameterBag(['foo' => 'bar']));
        $container->set('parameter_bag', new ContainerBag($container));

        $controller = $this->createController();
        $controller->setContainer($container);

        self::assertSame('bar', $controller->getParameter('foo'));
    }

    public function testMissingParameterBag()
    {
        self::expectException(ServiceNotFoundException::class);
        self::expectExceptionMessage('TestAbstractController::getParameter()" method is missing a parameter bag');
        $container = new Container();

        $controller = $this->createController();
        $controller->setContainer($container);

        $controller->getParameter('foo');
    }

    public function testForward()
    {
        $request = Request::create('/');
        $request->setLocale('fr');
        $request->setRequestFormat('xml');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $kernel = self::createMock(HttpKernelInterface::class);
        $kernel->expects(self::once())->method('handle')->willReturnCallback(function (Request $request) {
            return new Response($request->getRequestFormat().'--'.$request->getLocale());
        });

        $container = new Container();
        $container->set('request_stack', $requestStack);
        $container->set('http_kernel', $kernel);

        $controller = $this->createController();
        $controller->setContainer($container);

        $response = $controller->forward('a_controller');
        self::assertEquals('xml--fr', $response->getContent());
    }

    public function testGetUser()
    {
        $user = new InMemoryUser('user', 'pass');
        $token = new UsernamePasswordToken($user, 'default', ['ROLE_USER']);

        $controller = $this->createController();
        $controller->setContainer($this->getContainerWithTokenStorage($token));

        self::assertSame($controller->getUser(), $user);
    }

    /**
     * @group legacy
     */
    public function testGetUserAnonymousUserConvertedToNull()
    {
        $token = new AnonymousToken('default', 'anon.');

        $controller = $this->createController();
        $controller->setContainer($this->getContainerWithTokenStorage($token));

        self::assertNull($controller->getUser());
    }

    public function testGetUserWithEmptyTokenStorage()
    {
        $controller = $this->createController();
        $controller->setContainer($this->getContainerWithTokenStorage(null));

        self::assertNull($controller->getUser());
    }

    public function testGetUserWithEmptyContainer()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('The SecurityBundle is not registered in your application.');

        $controller = $this->createController();
        $controller->setContainer(new Container());

        $controller->getUser();
    }

    private function getContainerWithTokenStorage($token = null): Container
    {
        $tokenStorage = self::createMock(TokenStorage::class);
        $tokenStorage
            ->expects(self::once())
            ->method('getToken')
            ->willReturn($token);

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);

        return $container;
    }

    public function testJson()
    {
        $controller = $this->createController();
        $controller->setContainer(new Container());

        $response = $controller->json([]);
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertEquals('[]', $response->getContent());
    }

    public function testJsonWithSerializer()
    {
        $container = new Container();

        $serializer = self::createMock(SerializerInterface::class);
        $serializer
            ->expects(self::once())
            ->method('serialize')
            ->with([], 'json', ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS])
            ->willReturn('[]');

        $container->set('serializer', $serializer);

        $controller = $this->createController();
        $controller->setContainer($container);

        $response = $controller->json([]);
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertEquals('[]', $response->getContent());
    }

    public function testJsonWithSerializerContextOverride()
    {
        $container = new Container();

        $serializer = self::createMock(SerializerInterface::class);
        $serializer
            ->expects(self::once())
            ->method('serialize')
            ->with([], 'json', ['json_encode_options' => 0, 'other' => 'context'])
            ->willReturn('[]');

        $container->set('serializer', $serializer);

        $controller = $this->createController();
        $controller->setContainer($container);

        $response = $controller->json([], 200, [], ['json_encode_options' => 0, 'other' => 'context']);
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertEquals('[]', $response->getContent());
        $response->setEncodingOptions(\JSON_FORCE_OBJECT);
        self::assertEquals('{}', $response->getContent());
    }

    public function testFile()
    {
        $container = new Container();
        $kernel = self::createMock(HttpKernelInterface::class);
        $container->set('http_kernel', $kernel);

        $controller = $this->createController();
        $controller->setContainer($container);

        /* @var BinaryFileResponse $response */
        $response = $controller->file(new File(__FILE__));
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        if ($response->headers->get('content-type')) {
            self::assertSame('text/x-php', $response->headers->get('content-type'));
        }
        self::assertStringContainsString(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $response->headers->get('content-disposition'));
        self::assertStringContainsString(basename(__FILE__), $response->headers->get('content-disposition'));
    }

    public function testFileAsInline()
    {
        $controller = $this->createController();

        /* @var BinaryFileResponse $response */
        $response = $controller->file(new File(__FILE__), null, ResponseHeaderBag::DISPOSITION_INLINE);

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        if ($response->headers->get('content-type')) {
            self::assertSame('text/x-php', $response->headers->get('content-type'));
        }
        self::assertStringContainsString(ResponseHeaderBag::DISPOSITION_INLINE, $response->headers->get('content-disposition'));
        self::assertStringContainsString(basename(__FILE__), $response->headers->get('content-disposition'));
    }

    public function testFileWithOwnFileName()
    {
        $controller = $this->createController();

        /* @var BinaryFileResponse $response */
        $fileName = 'test.php';
        $response = $controller->file(new File(__FILE__), $fileName);

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        if ($response->headers->get('content-type')) {
            self::assertSame('text/x-php', $response->headers->get('content-type'));
        }
        self::assertStringContainsString(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $response->headers->get('content-disposition'));
        self::assertStringContainsString($fileName, $response->headers->get('content-disposition'));
    }

    public function testFileWithOwnFileNameAsInline()
    {
        $controller = $this->createController();

        /* @var BinaryFileResponse $response */
        $fileName = 'test.php';
        $response = $controller->file(new File(__FILE__), $fileName, ResponseHeaderBag::DISPOSITION_INLINE);

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        if ($response->headers->get('content-type')) {
            self::assertSame('text/x-php', $response->headers->get('content-type'));
        }
        self::assertStringContainsString(ResponseHeaderBag::DISPOSITION_INLINE, $response->headers->get('content-disposition'));
        self::assertStringContainsString($fileName, $response->headers->get('content-disposition'));
    }

    public function testFileFromPath()
    {
        $controller = $this->createController();

        /* @var BinaryFileResponse $response */
        $response = $controller->file(__FILE__);

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        if ($response->headers->get('content-type')) {
            self::assertSame('text/x-php', $response->headers->get('content-type'));
        }
        self::assertStringContainsString(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $response->headers->get('content-disposition'));
        self::assertStringContainsString(basename(__FILE__), $response->headers->get('content-disposition'));
    }

    public function testFileFromPathWithCustomizedFileName()
    {
        $controller = $this->createController();

        /* @var BinaryFileResponse $response */
        $response = $controller->file(__FILE__, 'test.php');

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        if ($response->headers->get('content-type')) {
            self::assertSame('text/x-php', $response->headers->get('content-type'));
        }
        self::assertStringContainsString(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $response->headers->get('content-disposition'));
        self::assertStringContainsString('test.php', $response->headers->get('content-disposition'));
    }

    public function testFileWhichDoesNotExist()
    {
        self::expectException(FileNotFoundException::class);

        $controller = $this->createController();

        $controller->file('some-file.txt', 'test.php');
    }

    public function testIsGranted()
    {
        $authorizationChecker = self::createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::once())->method('isGranted')->willReturn(true);

        $container = new Container();
        $container->set('security.authorization_checker', $authorizationChecker);

        $controller = $this->createController();
        $controller->setContainer($container);

        self::assertTrue($controller->isGranted('foo'));
    }

    public function testdenyAccessUnlessGranted()
    {
        self::expectException(AccessDeniedException::class);

        $authorizationChecker = self::createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::once())->method('isGranted')->willReturn(false);

        $container = new Container();
        $container->set('security.authorization_checker', $authorizationChecker);

        $controller = $this->createController();
        $controller->setContainer($container);

        $controller->denyAccessUnlessGranted('foo');
    }

    public function testRenderViewTwig()
    {
        $twig = self::createMock(Environment::class);
        $twig->expects(self::once())->method('render')->willReturn('bar');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = $this->createController();
        $controller->setContainer($container);

        self::assertEquals('bar', $controller->renderView('foo'));
    }

    public function testRenderTwig()
    {
        $twig = self::createMock(Environment::class);
        $twig->expects(self::once())->method('render')->willReturn('bar');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = $this->createController();
        $controller->setContainer($container);

        self::assertEquals('bar', $controller->render('foo')->getContent());
    }

    public function testRenderFormNew()
    {
        $formView = new FormView();

        $form = self::getMockBuilder(FormInterface::class)->getMock();
        $form->expects(self::once())->method('createView')->willReturn($formView);

        $twig = self::getMockBuilder(Environment::class)->disableOriginalConstructor()->getMock();
        $twig->expects(self::once())->method('render')->with('foo', ['bar' => $formView])->willReturn('bar');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = $this->createController();
        $controller->setContainer($container);

        $response = $controller->renderForm('foo', ['bar' => $form]);

        self::assertTrue($response->isSuccessful());
        self::assertSame('bar', $response->getContent());
    }

    public function testRenderFormSubmittedAndInvalid()
    {
        $formView = new FormView();

        $form = self::getMockBuilder(FormInterface::class)->getMock();
        $form->expects(self::once())->method('createView')->willReturn($formView);
        $form->expects(self::once())->method('isSubmitted')->willReturn(true);
        $form->expects(self::once())->method('isValid')->willReturn(false);

        $twig = self::getMockBuilder(Environment::class)->disableOriginalConstructor()->getMock();
        $twig->expects(self::once())->method('render')->with('foo', ['bar' => $formView])->willReturn('bar');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = $this->createController();
        $controller->setContainer($container);

        $response = $controller->renderForm('foo', ['bar' => $form]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('bar', $response->getContent());
    }

    public function testStreamTwig()
    {
        $twig = self::createMock(Environment::class);

        $container = new Container();
        $container->set('twig', $twig);

        $controller = $this->createController();
        $controller->setContainer($container);

        self::assertInstanceOf(StreamedResponse::class, $controller->stream('foo'));
    }

    public function testRedirectToRoute()
    {
        $router = self::createMock(RouterInterface::class);
        $router->expects(self::once())->method('generate')->willReturn('/foo');

        $container = new Container();
        $container->set('router', $router);

        $controller = $this->createController();
        $controller->setContainer($container);
        $response = $controller->redirectToRoute('foo');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/foo', $response->getTargetUrl());
        self::assertSame(302, $response->getStatusCode());
    }

    /**
     * @runInSeparateProcess
     */
    public function testAddFlash()
    {
        $flashBag = new FlashBag();
        $session = self::createMock(Session::class);
        $session->expects(self::once())->method('getFlashBag')->willReturn($flashBag);

        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $container = new Container();
        $container->set('session', $session);
        $container->set('request_stack', $requestStack);

        $controller = $this->createController();
        $controller->setContainer($container);
        $controller->addFlash('foo', 'bar');

        self::assertSame(['bar'], $flashBag->get('foo'));
    }

    public function testCreateAccessDeniedException()
    {
        $controller = $this->createController();

        self::assertInstanceOf(AccessDeniedException::class, $controller->createAccessDeniedException());
    }

    public function testIsCsrfTokenValid()
    {
        $tokenManager = self::createMock(CsrfTokenManagerInterface::class);
        $tokenManager->expects(self::once())->method('isTokenValid')->willReturn(true);

        $container = new Container();
        $container->set('security.csrf.token_manager', $tokenManager);

        $controller = $this->createController();
        $controller->setContainer($container);

        self::assertTrue($controller->isCsrfTokenValid('foo', 'bar'));
    }

    public function testGenerateUrl()
    {
        $router = self::createMock(RouterInterface::class);
        $router->expects(self::once())->method('generate')->willReturn('/foo');

        $container = new Container();
        $container->set('router', $router);

        $controller = $this->createController();
        $controller->setContainer($container);

        self::assertEquals('/foo', $controller->generateUrl('foo'));
    }

    public function testRedirect()
    {
        $controller = $this->createController();
        $response = $controller->redirect('https://dunglas.fr', 301);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://dunglas.fr', $response->getTargetUrl());
        self::assertSame(301, $response->getStatusCode());
    }

    public function testCreateNotFoundException()
    {
        $controller = $this->createController();

        self::assertInstanceOf(NotFoundHttpException::class, $controller->createNotFoundException());
    }

    public function testCreateForm()
    {
        $config = self::createMock(FormConfigInterface::class);
        $config->method('getInheritData')->willReturn(false);
        $config->method('getName')->willReturn('');

        $form = new Form($config);

        $formFactory = self::createMock(FormFactoryInterface::class);
        $formFactory->expects(self::once())->method('create')->willReturn($form);

        $container = new Container();
        $container->set('form.factory', $formFactory);

        $controller = $this->createController();
        $controller->setContainer($container);

        self::assertEquals($form, $controller->createForm('foo'));
    }

    public function testCreateFormBuilder()
    {
        $formBuilder = self::createMock(FormBuilderInterface::class);

        $formFactory = self::createMock(FormFactoryInterface::class);
        $formFactory->expects(self::once())->method('createBuilder')->willReturn($formBuilder);

        $container = new Container();
        $container->set('form.factory', $formFactory);

        $controller = $this->createController();
        $controller->setContainer($container);

        self::assertEquals($formBuilder, $controller->createFormBuilder('foo'));
    }

    /**
     * @group legacy
     */
    public function testGetDoctrine()
    {
        $doctrine = self::createMock(ManagerRegistry::class);

        $container = new Container();
        $container->set('doctrine', $doctrine);

        $controller = $this->createController();
        $controller->setContainer($container);

        self::assertEquals($doctrine, $controller->getDoctrine());
    }

    public function testAddLink()
    {
        $request = new Request();
        $link1 = new Link('mercure', 'https://demo.mercure.rocks');
        $link2 = new Link('self', 'https://example.com/foo');

        $controller = $this->createController();
        $controller->addLink($request, $link1);
        $controller->addLink($request, $link2);

        $links = $request->attributes->get('_links')->getLinks();
        self::assertContains($link1, $links);
        self::assertContains($link2, $links);
    }
}
