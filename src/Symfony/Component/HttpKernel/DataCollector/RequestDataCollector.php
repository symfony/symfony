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

use Symfony\Component\HttpFoundation\Cookie;
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
 */
class RequestDataCollector extends DataCollector implements EventSubscriberInterface
{
    protected $controllers;

    public function __construct()
    {
        $this->controllers = new \SplObjectStorage();
    }

    /**
     * {@inheritdoc}
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
            if (is_object($value)) {
                $attributes[$key] = sprintf('Object(%s)', get_class($value));
                if (is_callable(array($value, '__toString'))) {
                    $attributes[$key] .= sprintf(' = %s', (string) $value);
                }
            } else {
                $attributes[$key] = $value;
            }
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

        $this->data = array(
            'format'             => $request->getRequestFormat(),
            'content'            => $content,
            'content_type'       => $response->headers->get('Content-Type') ? $response->headers->get('Content-Type') : 'text/html',
            'status_code'        => $response->getStatusCode(),
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
        );

        if (isset($this->controllers[$request])) {
            $controller = $this->controllers[$request];
            if (is_array($controller)) {
                $r = new \ReflectionMethod($controller[0], $controller[1]);
                $this->data['controller'] = array(
                    'class'  => get_class($controller[0]),
                    'method' => $controller[1],
                    'file'   => $r->getFilename(),
                    'line'   => $r->getStartLine(),
                );
            } elseif ($controller instanceof \Closure) {
                $this->data['controller'] = 'Closure';
            } else {
                $this->data['controller'] = (string) $controller ?: 'n/a';
            }
            unset($this->controllers[$request]);
        }
    }

    public function getPathInfo()
    {
        return $this->data['path_info'];
    }

    public function getRequestRequest()
    {
        return new ParameterBag($this->data['request_request']);
    }

    public function getRequestQuery()
    {
        return new ParameterBag($this->data['request_query']);
    }

    public function getRequestHeaders()
    {
        return new HeaderBag($this->data['request_headers']);
    }

    public function getRequestServer()
    {
        return new ParameterBag($this->data['request_server']);
    }

    public function getRequestCookies()
    {
        return new ParameterBag($this->data['request_cookies']);
    }

    public function getRequestAttributes()
    {
        return new ParameterBag($this->data['request_attributes']);
    }

    public function getResponseHeaders()
    {
        return new ResponseHeaderBag($this->data['response_headers']);
    }

    public function getSessionMetadata()
    {
        return $this->data['session_metadata'];
    }

    public function getSessionAttributes()
    {
        return $this->data['session_attributes'];
    }

    public function getFlashes()
    {
        return $this->data['flashes'];
    }

    public function getContent()
    {
        return $this->data['content'];
    }

    public function getContentType()
    {
        return $this->data['content_type'];
    }

    public function getStatusCode()
    {
        return $this->data['status_code'];
    }

    public function getFormat()
    {
        return $this->data['format'];
    }

    /**
     * Gets the route name.
     *
     * The _route request attributes is automatically set by the Router Matcher.
     *
     * @return string The route
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
     */
    public function getRouteParams()
    {
        return isset($this->data['request_attributes']['_route_params']) ? $this->data['request_attributes']['_route_params'] : array();
    }

    /**
     * Gets the controller.
     *
     * @return string The controller as a string
     */
    public function getController()
    {
        return $this->data['controller'];
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $this->controllers[$event->getRequest()] = $event->getController();
    }

    public static function getSubscribedEvents()
    {
        return array(KernelEvents::CONTROLLER => 'onKernelController');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'request';
    }

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
