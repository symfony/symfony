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
use Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait;
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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ControllerTraitTest extends \PHPUnit_Framework_TestCase
{
    use ControllerTrait {
        getSerializer as traitGetSerializer;
        getTemplating as traitGetTemplating;
    }

    private $token;
    private $serializer;
    private $flashBag;
    private $isGranted;
    private $templating;
    private $twig;
    private $formFactory;

    protected function getRouter()
    {
        $router = $this->getMock(RouterInterface::class);
        $router->expects($this->once())->method('generate')->willReturn('/foo');

        return $router;
    }

    protected function getRequestStack()
    {
        $request = Request::create('/');
        $request->setLocale('fr');
        $request->setRequestFormat('xml');

        $requestStack = new RequestStack();
        $requestStack->push($request);


        return $requestStack;
    }

    protected function getHttpKernel()
    {
        $kernel = $this->getMock(HttpKernelInterface::class);
        $kernel->expects($this->once())->method('handle')->will($this->returnCallback(function (Request $request) {
            return new Response($request->getRequestFormat().'--'.$request->getLocale());
        }));

        return $kernel;
    }

    protected function getSerializer()
    {
        if ($this->serializer) {
            return $this->serializer;
        }

        return $this->traitGetSerializer();
    }

    protected function getSession()
    {
        $session = $this->getMock(Session::class);
        $session->expects($this->once())->method('getFlashBag')->willReturn($this->flashBag);

        return $session;
    }

    protected function getAuthorizationChecker()
    {
        $authorizationChecker = $this->getMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->once())->method('isGranted')->willReturn($this->isGranted);

        return $authorizationChecker;
    }

    protected function getTemplating()
    {
        if ($this->templating) {
            return $this->templating;
        }

        return $this->traitGetTemplating();
    }

    protected function getTwig()
    {
        return $this->twig;
    }

    protected function getDoctrine()
    {
        return $this->getMock(ManagerRegistry::class);
    }

    protected function getFormFactory()
    {
        return $this->formFactory;
    }

    protected function getTokenStorage()
    {
        $tokenStorage = $this->getMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($this->token));

        return $tokenStorage;
    }

    protected function getCsrfTokenManager()
    {
        $tokenManager = $this->getMock(CsrfTokenManagerInterface::class);
        $tokenManager->expects($this->once())->method('isTokenValid')->willReturn(true);

        return $tokenManager;
    }

    public function testGenerateUrl()
    {
        $this->assertEquals('/foo', $this->generateUrl('foo'));
    }

    public function testForward()
    {
        $response = $this->forward('a_controller');
        $this->assertEquals('xml--fr', $response->getContent());
    }

    public function testRedirect()
    {
        $response = $this->redirect('http://example.com', 301);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://example.com', $response->getTargetUrl());
        $this->assertSame(301, $response->getStatusCode());
    }

    public function testRedirectToRoute()
    {
        $response = $this->redirectToRoute('foo');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/foo', $response->getTargetUrl());
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testGetUser()
    {
        $user = new User('user', 'pass');
        $this->token = new UsernamePasswordToken($user, 'pass', 'default', array('ROLE_USER'));

        $this->assertSame($this->getUser(), $user);
    }

    public function testGetUserAnonymousUserConvertedToNull()
    {
        $this->token = new AnonymousToken('default', 'anon.');
        $this->assertNull($this->getUser());
    }

    public function testGetUserWithEmptyTokenStorage()
    {
        $this->token = null;
        $this->assertNull($this->getUser());
    }

    public function testJson()
    {
        $this->serializer = null;

        $response = $this->json(array());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('[]', $response->getContent());
    }

    public function testJsonWithSerializer()
    {
        $this->serializer = $this->getMock(SerializerInterface::class);
        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(array(), 'json', array('json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS))
            ->will($this->returnValue('[]'));

        $response = $this->json(array());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('[]', $response->getContent());
    }

    public function testJsonWithSerializerContextOverride()
    {
        $this->serializer = $this->getMock(SerializerInterface::class);
        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(array(), 'json', array('json_encode_options' => 0, 'other' => 'context'))
            ->will($this->returnValue('[]'));

        $response = $this->json(array(), 200, array(), array('json_encode_options' => 0, 'other' => 'context'));
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('[]', $response->getContent());
        $response->setEncodingOptions(JSON_FORCE_OBJECT);
        $this->assertEquals('{}', $response->getContent());
    }

    public function testAddFlash()
    {
        $this->flashBag = new FlashBag();

        $this->addFlash('foo', 'bar');

        $this->assertSame(array('bar'), $this->flashBag->get('foo'));
    }

    public function testIsGranted()
    {
        $this->isGranted = true;

        $this->assertTrue($this->isGranted('foo'));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    public function testDenyAccessUnlessGranted()
    {
        $this->isGranted = false;

        $this->denyAccessUnlessGranted('foo');
    }

    public function testRenderViewTemplating()
    {
        $this->templating = $this->getMock(EngineInterface::class);
        $this->templating->expects($this->once())->method('render')->willReturn('bar');

        $this->assertEquals('bar', $this->renderView('foo'));
    }

    public function testRenderViewTwig()
    {
        $this->templating = false;
        $this->twig = $this->getMockBuilder(\Twig_Environment::class)->disableOriginalConstructor()->getMock();
        $this->twig->expects($this->once())->method('render')->willReturn('bar');

        $this->assertEquals('bar', $this->renderView('foo'));
    }

    public function testRenderTemplating()
    {
        $this->templating = $this->getMock(EngineInterface::class);
        $this->templating->expects($this->once())->method('renderResponse')->willReturn(new Response('bar'));

        $this->assertEquals('bar', $this->render('foo')->getContent());
    }

    public function testRenderTwig()
    {
        $this->templating = false;
        $this->twig = $this->getMockBuilder(\Twig_Environment::class)->disableOriginalConstructor()->getMock();
        $this->twig->expects($this->once())->method('render')->willReturn('bar');

        $this->assertEquals('bar', $this->render('foo')->getContent());
    }

    public function testStreamTemplating()
    {
        $this->templating = $this->getMock(EngineInterface::class);

        $this->assertInstanceOf(StreamedResponse::class, $this->stream('foo'));
    }

    public function testStreamTwig()
    {
        $this->templating = false;
        $this->twig = $this->getMockBuilder(\Twig_Environment::class)->disableOriginalConstructor()->getMock();

        $this->assertInstanceOf(StreamedResponse::class, $this->stream('foo'));
    }

    public function testCreateNotFoundException()
    {
        $this->assertInstanceOf(NotFoundHttpException::class, $this->createNotFoundException());
    }

    public function testCreateAccessDeniedException()
    {
        $this->assertInstanceOf(AccessDeniedException::class, $this->createAccessDeniedException());
    }

    public function testCreateForm()
    {
        $form = $this->getMock(FormInterface::class);

        $this->formFactory = $this->getMock(FormFactoryInterface::class);
        $this->formFactory->expects($this->once())->method('create')->willReturn($form);

        $this->assertEquals($form, $this->createForm('foo'));
    }

    public function testCreateFormBuilder()
    {
        $formBuilder = $this->getMock(FormBuilderInterface::class);

        $this->formFactory = $this->getMock(FormFactoryInterface::class);
        $this->formFactory->expects($this->once())->method('createBuilder')->willReturn($formBuilder);

        $this->assertEquals($formBuilder, $this->createFormBuilder('foo'));
    }

    public function testGetDoctrine()
    {
        $this->assertInstanceOf(ManagerRegistry::class, $this->getDoctrine());
    }

    public function testIsCsrfTokenValid()
    {
        $this->assertTrue($this->isCsrfTokenValid('foo', 'bar'));
    }
}
