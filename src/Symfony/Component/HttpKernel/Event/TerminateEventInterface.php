<?php

namespace Symfony\Component\HttpKernel\Event;

use Symfony\Component\HttpFoundation\Response;

/**
 * Represents an terminate event containing a response.
 *
 * @author Lukasz Goworko <info@lukaszgoworko.de>
 */
interface TerminateEventInterface
{
    public function getResponse(): Response;
}
