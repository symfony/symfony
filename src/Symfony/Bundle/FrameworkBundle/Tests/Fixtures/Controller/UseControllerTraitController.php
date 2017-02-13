<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class UseControllerTraitController
{
    use ControllerTrait {
        getRouter as traitGetRouter;
        getRequestStack as traitGetRequestStack;
        getHttpKernel as traitGetHttpKernel;
        getSerializer as traitGetSerializer;
        getSession as traitGetSession;
        getAuthorizationChecker as traitGetAuthorizationChecker;
        getTwig as traitGetTwig;
        getDoctrine as traitGetDoctrine;
        getFormFactory as traitGetFormFactory;
        getTokenStorage as traitGetTokenStorage;
        getCsrfTokenManager as traitGetCsrfTokenManager;

        generateUrl as public;
        forward as public;
        redirect as public;
        redirectToRoute as public;
        json as public;
        file as public;
        addFlash as public;
        isGranted as public;
        denyAccessUnlessGranted as public;
        renderView as public;
        render as public;
        stream as public;
        createNotFoundException as public;
        createAccessDeniedException as public;
        createForm as public;
        createFormBuilder as public;
        getUser as public;
        isCsrfTokenValid as public;
    }

    private $router;
    private $httpKernel;
    private $serializer;
    private $authorizationChecker;
    private $session;
    private $twig;
    private $doctrine;
    private $formFactory;

    public function setRouter(RouterInterface $router)
    {
        $this->router = $router;
    }

    protected function getRouter(): RouterInterface
    {
        return $this->router ?? $this->traitGetRouter();
    }

    public function setRequestStack(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    protected function getRequestStack(): RequestStack
    {
        return $this->requestStack ?? $this->traitGetRequestStack();
    }

    public function setHttpKernel(HttpKernelInterface $httpKernel)
    {
        $this->httpKernel = $httpKernel;
    }

    protected function getHttpKernel(): HttpKernelInterface
    {
        return $this->httpKernel ?? $this->traitGetHttpKernel();
    }

    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    protected function getSerializer(): SerializerInterface
    {
        return $this->serializer ?? $this->traitGetSerializer();
    }

    public function setSession(Session $session)
    {
        $this->session = $session;
    }

    protected function getSession(): Session
    {
        return $this->session ?? $this->traitGetSession();
    }

    public function setAuthorizationChecker(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    protected function getAuthorizationChecker(): AuthorizationCheckerInterface
    {
        return $this->authorizationChecker ?? $this->traitGetAuthorizationChecker();
    }

    public function setTwig(\Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    protected function getTwig(): \Twig_Environment
    {
        return $this->twig ?? $this->traitGetTwig();
    }

    public function setDoctrine(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public function getDoctrine(): ManagerRegistry
    {
        return $this->doctrine ?? $this->traitGetDoctrine();
    }

    public function setFormFactory(FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;
    }

    protected function getFormFactory(): FormFactoryInterface
    {
        return $this->formFactory ?? $this->traitGetFormFactory();
    }

    public function setTokenStorage(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    protected function getTokenStorage(): TokenStorageInterface
    {
        return $this->tokenStorage ?? $this->traitGetTokenStorage();
    }

    public function setCsrfTokenManager(CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;
    }

    protected function getCsrfTokenManager(): CsrfTokenManagerInterface
    {
        return $this->csrfTokenManager ?? $this->traitGetCsrfTokenManager();
    }
}
