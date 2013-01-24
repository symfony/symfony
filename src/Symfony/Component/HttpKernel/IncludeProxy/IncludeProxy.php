<?php
namespace Symfony\Component\HttpKernel\IncludeProxy;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * @author Sebastian Krebs <krebs.seb@gmail.com>
 */
class IncludeProxy implements HttpKernelInterface, TerminableInterface
{
    private $kernel;
    private $strategies;
    private $options = array(
        'pass_through' => false
    );

    public function __construct (HttpKernelInterface $kernel, array $strategies = array(), array $options = array())
    {
        $this->kernel = $kernel;
        $this->options = array_merge($this->options, $options);
        $this->strategies = $strategies;
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
        $that = $this;
        $strategies = array_filter($this->strategies, function (IncludeStrategyInterface $strategy) use ($that, $request) {
            if ($that->serverHasCapability($strategy->getName(), $request)) {
                return false;
            } else {
                $that->addCapabilityHeader($strategy->getName(), $request);
                return true;
            }
        });

        $response = $this->kernel->handle($request, $type, $catch);

        if (!$this->options['pass_through']) {
            foreach ($strategies as $strategy) {
                /** @var $strategy IncludeStrategyInterface */
                if ($this->hasControlHeader($strategy->getName(), $response)) {
                    $this->parse($strategy, $request, $response);
                }
            }
        }

        return $response;
    }

    private function parse (IncludeStrategyInterface $strategy, Request $request, Response $response)
    {
        $content = $strategy->handle($this, $request, $response);
        $response->setContent($content);

        $this->removeControlHeader($strategy->getName(), $response);
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

    private function serverHasCapability ($name, Request $request)
    {
        $value = $request->headers->get('Surrogate-Capability');

        return $value && strpos($value, $name) !== false;
    }

    private function hasControlHeader ($name, Response $response)
    {
        $value = $response->headers->get('Surrogate-Control');

        return $value && strpos($value, $name) !== false;
    }

    private function addCapabilityHeader ($name, Request $request)
    {
        $current = $request->headers->get('Surrogate-Capability');
        $request->headers->set('Surrogate-Capability', ($current ? $current . ', ' : '') . sprintf('symfony2="%s"', $name));
    }

    private function removeControlHeader ($name, Response $response)
    {
        $current = $response->headers->get('Surrogate-Control');
        $new = array_filter(explode(',', $current), function ($control) use ($name) {
            return strpos($control, $name) === false;
        });
        if ($new) {
            $response->headers->set('Surrogate-Control', implode(', ', $new));
        } else {
            $response->headers->remove('Surrogate-Control');
        }
    }
}
