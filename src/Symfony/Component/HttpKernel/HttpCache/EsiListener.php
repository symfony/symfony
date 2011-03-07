<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\HttpCache;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\RequestEventArgs;

/**
 * EsiListener adds a Surrogate-Control HTTP header when the Response needs to be parsed for ESI.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EsiListener
{
    protected $i;
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
     * @param RequestEventArgs $eventArgs  A RequestEventArgs instance
     */
    public function filterCoreResponse(RequestEventArgs $eventArgs)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $eventArgs->getRequestType() || null === $this->esi) {
            return;
        }

        $this->esi->addSurrogateControl($eventArgs->getResponse());
    }
}
