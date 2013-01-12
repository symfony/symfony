<?php
namespace Symfony\Component\HttpKernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kernel proxy handling SSI-responses
 *
 * @author Sebastian Krebs <krebs.seb@gmail.com>
 */
class SsiKernelProxy implements HttpKernelInterface, TerminableInterface
{
    private $kernel;
    private $options = array(
        'pass_through' => false
    );

    public function __construct (HttpKernelInterface $kernel, array $options = array())
    {
        $this->kernel = $kernel;
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Handles a Request to convert it to a Response.
     *
     * When $catch is true, the implementation must catch all exceptions
     * and do its best to convert them to a Response instance.
     *
     * @param Request $request A Request instance
     * @param integer $type    The type of the request
     *                          (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     * @param Boolean $catch   Whether to catch exceptions or not
     *
     * @return Response A Response instance
     *
     * @throws \Exception When an Exception occurs during processing
     *
     * @api
     */
    public function handle (Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        $response = $this->kernel->handle($request, $type, $catch);

        if (!$this->options['pass_through'] && !$this->serverHasCapability($request) && $this->hasControlHeader($response)) {
            $this->parse($request, $response);
        }

        return $response;
    }

    /**
     * Terminates a request/response cycle.
     *
     * Should be called after sending the response and before shutting down the kernel.
     *
     * @param Request  $request  A Request instance
     * @param Response $response A Response instance
     *
     * @api
     */
    public function terminate (Request $request, Response $response)
    {
        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($request, $response);
        }
    }

    private function parse (Request $request, Response $response)
    {
        $this->addCapabilityHeader($request);

        $content = preg_replace_callback('#<!--\#include\s+(.*?)\s*-->#', $this->createHandler($this, $request, $response), $response->getContent());
        $response->setContent($content);

        $this->removeControlHeader($response);
    }

    private function createHandler (HttpKernelInterface $kernel, Request $request, Response $response)
    {
        return function ($attributes) use ($kernel, $request, $response) {
            $options = array();
            preg_match_all('/(virtual|fmt)="([^"]*?)"/', $attributes[1], $matches, PREG_SET_ORDER);
            foreach ($matches as $set) {
                $options[$set[1]] = $set[2];
            }

            if (!isset($options['virtual'])) {
                throw new \RuntimeException('Unable to process an SSI tag without a "virtual" attribute.');
            }


            $subRequest = Request::create($options['virtual'], 'GET', array(), $request->cookies->all(), array(), $request->server->all());

            try {
                $subResponse = $kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST, true);

                if (!$subResponse->isSuccessful()) {
                    throw new \RuntimeException(sprintf('Error when rendering "%s" (Status code is %s).', $subRequest->getUri(), $subResponse->getStatusCode()));
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

                if (!isset($options['fmt']) || $options['fmt'] != '?') {
                    throw $e;
                }
            }

            return '';
        };
    }

    private function serverHasCapability (Request $request)
    {
        $value = $request->headers->get('Surrogate-Capability');
        return $value && strpos($value, 'SSI/1.0') !== false;
    }

    private function hasControlHeader (Response $response) {
        $value = $response->headers->get('Surrogate-Control');
        return $value && strpos($value, 'SSI/1.0') !== false;
    }

    private function addCapabilityHeader (Request $request)
    {
        $current = $request->headers->get('Surrogate-Capability');
        $request->headers->set('Surrogate-Capability', ($current ? $current . ', ' : '') . 'symfony2="SSI/1.0"');
    }

    private function removeControlHeader (Response $response)
    {
        $current = $response->headers->get('Surrogate-Control');
        $new = array_filter(explode(',', $current), function ($control) {
            return strpos($control, 'SSI/1.0') === false;
        });
        if ($new) {
            $response->headers->set('Surrogate-Control', implode(', ', $new));
        } else {
            $response->headers->remove('Surrogate-Control');
        }
    }
}
