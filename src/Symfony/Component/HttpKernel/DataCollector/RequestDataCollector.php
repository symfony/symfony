<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * RequestDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.1.0
 */
class RequestDataCollector extends DataCollector implements EventSubscriberInterface
{
    protected $controllers;

    /**
     * @since v2.1.0
     */
    public function __construct()
    {
        $this->controllers = new \SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $responseHeaders = $response->headers->all();
        $cookies = array();
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[] = $this->getCookieHeader($cookie->getName(), $cookie->getValue(), $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly());
        }
        if (count($cookies) > 0) {
            $responseHeaders['Set-Cookie'] = $cookies;
        }

        $attributes = array();
        foreach ($request->attributes->all() as $key => $value) {
            if ('_route' == $key && is_object($value)) {
                $value = $value->getPath();
            }

            $attributes[$key] = $this->varToString($value);
        }

        $content = null;
        try {
            $content = $request->getContent();
        } catch (\LogicException $e) {
            // the user already got the request content as a resource
            $content = false;
        }

        $sessionMetadata = array();
        $sessionAttributes = array();
        $flashes = array();
        if ($request->hasSession()) {
            $session = $request->getSession();
            if ($session->isStarted()) {
                $sessionMetadata['Created'] = date(DATE_RFC822, $session->getMetadataBag()->getCreated());
                $sessionMetadata['Last used'] = date(DATE_RFC822, $session->getMetadataBag()->getLastUsed());
                $sessionMetadata['Lifetime'] = $session->getMetadataBag()->getLifetime();
                $sessionAttributes = $session->all();
                $flashes = $session->getFlashBag()->peekAll();
            }
        }

        $statusCode = $response->getStatusCode();

        $this->data = array(
            'format'             => $request->getRequestFormat(),
            'content'            => $content,
            'content_type'       => $response->headers->get('Content-Type') ? $response->headers->get('Content-Type') : 'text/html',
            'status_text'        => isset(Response::$statusTexts[$statusCode]) ? Response::$statusTexts[$statusCode] : '',
            'status_code'        => $statusCode,
            'request_query'      => $request->query->all(),
            'request_request'    => $request->request->all(),
            'request_headers'    => $request->headers->all(),
            'request_server'     => $request->server->all(),
            'request_cookies'    => $request->cookies->all(),
            'request_attributes' => $attributes,
            'response_headers'   => $responseHeaders,
            'session_metadata'   => $sessionMetadata,
            'session_attributes' => $sessionAttributes,
            'flashes'            => $flashes,
            'path_info'          => $request->getPathInfo(),
            'controller'         => 'n/a',
            'locale'             => $request->getLocale(),
        );

        if (isset($this->data['request_headers']['php-auth-pw'])) {
            $this->data['request_headers']['php-auth-pw'] = '******';
        }

        if (isset($this->data['request_server']['PHP_AUTH_PW'])) {
            $this->data['request_server']['PHP_AUTH_PW'] = '******';
        }

        if (isset($this->controllers[$request])) {
            $controller = $this->controllers[$request];
            if (is_array($controller)) {
                try {
                    $r = new \ReflectionMethod($controller[0], $controller[1]);
                    $this->data['controller'] = array(
                        'class'  => is_object($controller[0]) ? get_class($controller[0]) : $controller[0],
                        'method' => $controller[1],
                        'file'   => $r->getFilename(),
                        'line'   => $r->getStartLine(),
                    );
                } catch (\ReflectionException $re) {
                    if (is_callable($controller)) {
                        // using __call or  __callStatic
                        $this->data['controller'] = array(
                            'class'  => is_object($controller[0]) ? get_class($controller[0]) : $controller[0],
                            'method' => $controller[1],
                            'file'   => 'n/a',
                            'line'   => 'n/a',
                        );
                    }
                }
            } elseif ($controller instanceof \Closure) {
                $r = new \ReflectionFunction($controller);
                $this->data['controller'] = array(
                    'class'  => $r->getName(),
                    'method' => null,
                    'file'   => $r->getFilename(),
                    'line'   => $r->getStartLine(),
                );
            } else {
                $this->data['controller'] = (string) $controller ?: 'n/a';
            }
            unset($this->controllers[$request]);
        }
    }

    /**
     * @since v2.1.0
     */
    public function getPathInfo()
    {
        return $this->data['path_info'];
    }

    /**
     * @since v2.0.0
     */
    public function getRequestRequest()
    {
        return new ParameterBag($this->data['request_request']);
    }

    /**
     * @since v2.0.0
     */
    public function getRequestQuery()
    {
        return new ParameterBag($this->data['request_query']);
    }

    /**
     * @since v2.0.0
     */
    public function getRequestHeaders()
    {
        return new HeaderBag($this->data['request_headers']);
    }

    /**
     * @since v2.0.0
     */
    public function getRequestServer()
    {
        return new ParameterBag($this->data['request_server']);
    }

    /**
     * @since v2.0.0
     */
    public function getRequestCookies()
    {
        return new ParameterBag($this->data['request_cookies']);
    }

    /**
     * @since v2.0.0
     */
    public function getRequestAttributes()
    {
        return new ParameterBag($this->data['request_attributes']);
    }

    /**
     * @since v2.0.0
     */
    public function getResponseHeaders()
    {
        return new ResponseHeaderBag($this->data['response_headers']);
    }

    /**
     * @since v2.1.0
     */
    public function getSessionMetadata()
    {
        return $this->data['session_metadata'];
    }

    /**
     * @since v2.0.0
     */
    public function getSessionAttributes()
    {
        return $this->data['session_attributes'];
    }

    /**
     * @since v2.1.0
     */
    public function getFlashes()
    {
        return $this->data['flashes'];
    }

    /**
     * @since v2.0.0
     */
    public function getContent()
    {
        return $this->data['content'];
    }

    /**
     * @since v2.0.0
     */
    public function getContentType()
    {
        return $this->data['content_type'];
    }

    /**
     * @since v2.3.0
     */
    public function getStatusText()
    {
        return $this->data['status_text'];
    }

    /**
     * @since v2.0.0
     */
    public function getStatusCode()
    {
        return $this->data['status_code'];
    }

    /**
     * @since v2.0.0
     */
    public function getFormat()
    {
        return $this->data['format'];
    }

    /**
     * @since v2.2.0
     */
    public function getLocale()
    {
        return $this->data['locale'];
    }

    /**
     * Gets the route name.
     *
     * The _route request attributes is automatically set by the Router Matcher.
     *
     * @return string The route
     *
     * @since v2.1.0
     */
    public function getRoute()
    {
        return isset($this->data['request_attributes']['_route']) ? $this->data['request_attributes']['_route'] : '';
    }

    /**
     * Gets the route parameters.
     *
     * The _route_params request attributes is automatically set by the RouterListener.
     *
     * @return array The parameters
     *
     * @since v2.1.0
     */
    public function getRouteParams()
    {
        return isset($this->data['request_attributes']['_route_params']) ? $this->data['request_attributes']['_route_params'] : array();
    }

    /**
     * Gets the controller.
     *
     * @return string The controller as a string
     *
     * @since v2.1.0
     */
    public function getController()
    {
        return $this->data['controller'];
    }

    /**
     * @since v2.1.0
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $this->controllers[$event->getRequest()] = $event->getController();
    }

    /**
     * @since v2.1.0
     */
    public static function getSubscribedEvents()
    {
        return array(KernelEvents::CONTROLLER => 'onKernelController');
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function getName()
    {
        return 'request';
    }

    /**
     * @since v2.0.0
     */
    private function getCookieHeader($name, $value, $expires, $path, $domain, $secure, $httponly)
    {
        $cookie = sprintf('%s=%s', $name, urlencode($value));

        if (0 !== $expires) {
            if (is_numeric($expires)) {
                $expires = (int) $expires;
            } elseif ($expires instanceof \DateTime) {
                $expires = $expires->getTimestamp();
            } else {
                $expires = strtotime($expires);
                if (false === $expires || -1 == $expires) {
                    throw new \InvalidArgumentException(sprintf('The "expires" cookie parameter is not valid.', $expires));
                }
            }

            $cookie .= '; expires='.substr(\DateTime::createFromFormat('U', $expires, new \DateTimeZone('UTC'))->format('D, d-M-Y H:i:s T'), 0, -5);
        }

        if ($domain) {
            $cookie .= '; domain='.$domain;
        }

        $cookie .= '; path='.$path;

        if ($secure) {
            $cookie .= '; secure';
        }

        if ($httponly) {
            $cookie .= '; httponly';
        }

        return $cookie;
    }
}
