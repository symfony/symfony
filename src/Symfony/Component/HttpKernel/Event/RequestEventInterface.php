<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Event;

use Symfony\Component\HttpFoundation\Response;

/**
 * Represents an event containing probably a response.
 *
 * @author Lukasz Goworko <info@lukaszgoworko.de>
 */
interface RequestEventInterface
{
    /**
     * Returns the response object.
     *
     * @return Response|null
     */
    public function getResponse();

    public function setResponse(Response $response);

    /**
     * Returns whether a response was set.
     *
     * @return bool Whether a response was set
     */
    public function hasResponse();
}
