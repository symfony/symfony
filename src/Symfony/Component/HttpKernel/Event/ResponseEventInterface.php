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
 * Represents an event containing response.
 *
 * @author Lukasz Goworko <info@lukaszgoworko.de>
 */
interface ResponseEventInterface
{
    public function getResponse(): Response;

    public function setResponse(Response $response): void;
}
