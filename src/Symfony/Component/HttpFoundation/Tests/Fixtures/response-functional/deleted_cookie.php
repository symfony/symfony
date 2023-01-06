<?php

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorageFactory;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/** @var Response $r */
$r = require __DIR__.'/common.inc';

$sessionId = 'vqd4dpbtst3af0k4sdl18nebkn';
session_id($sessionId);
$sessionName = session_name();
$_COOKIE[$sessionName] = $sessionId;

$request = new Request();
$request->cookies->set($sessionName, $sessionId);

$requestStack = new RequestStack();
$requestStack->push($request);

$sessionFactory = new SessionFactory($requestStack, new NativeSessionStorageFactory());

$container = new Container();
$container->set('request_stack', $requestStack);
$container->set('session_factory', $sessionFactory);

$listener = new SessionListener($container);

$kernel = new class($r) implements HttpKernelInterface {
    /**
     * @var Response
     */
    private $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        return $this->response;
    }
};

$listener->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
$session = $request->getSession();
$session->set('foo', 'bar');
$session->invalidate();

$listener->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $r));

$r->sendHeaders();
