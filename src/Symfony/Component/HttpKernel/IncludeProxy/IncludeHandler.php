<?php
namespace Symfony\Component\HttpKernel\IncludeProxy;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Sebastian Krebs <krebs.seb@gmail.com>
 *
 * This is a helper class, that act as and replace the closure of the
 * IncludeStrategy-implementations.
 *
 * @internal
 */
class IncludeHandler
{
    private $kernel;
    private $request;
    private $response;
    private $extractor;

    public function __construct (HttpKernelInterface $kernel, Request $request, Response $response, $extractor)
    {
        $this->kernel = $kernel;
        $this->request = $request;
        $this->response = $response;
        $this->extractor = $extractor;
    }

    public function __invoke ($attributes)
    {
        list($source, $alternative, $ignoreErrors) = call_user_func($this->extractor, $attributes);
        return $this->handle($source, $alternative, $ignoreErrors);
    }

    private function handle ($source, $alternative, $ignoreErrors)
    {
        if (!$source) {
            throw new \RuntimeException('Unable to process an include tag without a source attribute.');
        }

        try {
            $subResponse = $this->request($source);
            $this->updateHeaders($this->response, $subResponse);
            return $subResponse->getContent();
        } catch (\Exception $e) {
            if ($alternative) {
                return $this->handle($alternative, null, $ignoreErrors);
            }

            if ($ignoreErrors) {
                throw $e;
            }
        }

        return '';
    }

    private function request ($source)
    {
        $subRequest = Request::create($source, 'GET', array(), $this->request->cookies->all(), array(), $this->request->server->all());

        $response = $this->kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST, true);

        if (!$response->isSuccessful()) {
            throw new \RuntimeException(sprintf('Error when rendering "%s" (Status code is %s).', $subRequest->getUri(), $response->getStatusCode()));
        }
        return $response;
    }

    private function updateHeaders (Response $response, Response $subResponse)
    {
        if ($this->response->isCacheable() && $subResponse->isCacheable()) {
            $maxAge = min($response->headers->getCacheControlDirective('max-age'), $subResponse->headers->getCacheControlDirective('max-age'));
            $sMaxAge = min($response->headers->getCacheControlDirective('s-maxage'), $subResponse->headers->getCacheControlDirective('s-maxage'));
            $response->setSharedMaxAge($sMaxAge);
            $response->setMaxAge($maxAge);
        } else {
            $this->response->headers->set('Cache-Control', 'no-cache, must-revalidate');
        }
    }
}
