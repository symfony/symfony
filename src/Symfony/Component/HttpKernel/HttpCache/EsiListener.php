<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\HttpCache;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventInterface;

/**
 * EsiListener adds a Surrogate-Control HTTP header when the Response needs to be parsed for ESI.
 *
 * The filter method must be connected to the core.response event.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EsiListener
{
    protected $dispatcher;
    protected $esi;

    /**
     * Constructor.
     *
     * @param Esi $esi An ESI instance
     */
    public function __construct(Esi $esi = null)
    {
        $this->esi = $esi;
    }

    /**
     * Filters the Response.
     *
     * @param EventInterface $event    An EventInterface instance
     * @param Response       $response A Response instance
     */
    public function filter(EventInterface $event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->get('request_type') || null === $this->esi) {
            return $response;
        }

        $this->esi->addSurrogateControl($response);

        return $response;
    }
}
