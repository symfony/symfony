<?php
namespace Symfony\Component\HttpKernel\IncludeProxy;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @author Sebastian Krebs <krebs.seb@gmail.com>
 */
class EsiIncludeStrategy implements IncludeStrategyInterface
{
    public function getName ()
    {
        return 'ESI/1.0';
    }

    public function handle (HttpKernelInterface $kernel, Request $request, Response $response)
    {
        return preg_replace_callback('#<esi\:include\s+(.*?)\s*(?:/|</esi\:include)>#', $this->createHandler($kernel, $request, $response), $response->getContent());
    }

    private function createHandler (HttpKernelInterface $kernel, Request $request, Response $response)
    {
        return function ($attributes) use ($kernel, $request, $response) {
            $options = array();
            preg_match_all('/(src|onerror|alt)="([^"]*?)"/', $attributes[1], $matches, PREG_SET_ORDER);
            foreach ($matches as $set) {
                $options[$set[1]] = $set[2];
            }

            if (!isset($options['src'])) {
                throw new \RuntimeException('Unable to process an ESI tag without a "src" attribute.');
            }


            $subRequest = Request::create($options['src'], 'GET', array(), $request->cookies->all(), array(), $request->server->all());

            try {
                $subResponse = $kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST, true);

                if (!$subResponse->isSuccessful()) {
                    if (isset($options['alt']) && $options['alt']) {
                        $subRequest = Request::create($options['alt'], 'GET', array(), $request->cookies->all(), array(), $request->server->all());
                        $subResponse = $kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST, true);
                        if (!$subResponse->isSuccessful()) {
                            throw new \RuntimeException(sprintf('Error when rendering "%s" (Status code is %s).', $subRequest->getUri(), $subResponse->getStatusCode()));
                        }
                    } else {
                        throw new \RuntimeException(sprintf('Error when rendering "%s" (Status code is %s).', $subRequest->getUri(), $subResponse->getStatusCode()));
                    }
                }

                if ($response->isCacheable() && $subResponse->isCacheable()) {
                    $maxAge = min($response->headers->getCacheControlDirective('max-age'), $subResponse->headers->getCacheControlDirective('max-age'));
                    $sMaxAge = min($response->headers->getCacheControlDirective('s-maxage'), $subResponse->headers->getCacheControlDirective('s-maxage'));
                    $response->setSharedMaxAge($sMaxAge);
                    $response->setMaxAge($maxAge);
                } else {
                    $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
                }

                return $subResponse->getContent();
            } catch (\Exception $e) {
                if (isset($options['onerror']) && 'continue' == $options['onerror']) {
                    throw $e;
                }
            }

            return '';
        };
    }
}
