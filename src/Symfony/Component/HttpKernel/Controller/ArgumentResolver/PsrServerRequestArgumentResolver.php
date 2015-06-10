<?php

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver;

use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Converts HttpFoundation Request to PSR-7 ServerRequest using the bridge.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Wouter J <wouter@wouterj.nl>
 */
class PsrServerRequestArgumentResolver implements ArgumentResolverInterface
{
    /**
     * @var array
     */
    private static $supportedTypes = array(
        'Psr\Http\Message\ServerRequestInterface' => true,
        'Psr\Http\Message\RequestInterface' => true,
        'Psr\Http\Message\MessageInterface' => true,
    );

    /**
     * @var HttpMessageFactoryInterface
     */
    private $httpMessageFactory;

    public function __construct(HttpMessageFactoryInterface $httpMessageFactory)
    {
        $this->httpMessageFactory = $httpMessageFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, \ReflectionParameter $parameter)
    {
        $class = $parameter->getClass();

        return null !== $class && isset(self::$supportedTypes[$class->name]);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, \ReflectionParameter $parameter)
    {
        return $this->httpMessageFactory->createRequest($request);
    }
}
