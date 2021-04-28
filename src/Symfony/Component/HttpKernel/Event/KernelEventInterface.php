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

/**
 * Represents an event containing a kernel and a request.
 *
 * @author Lukasz Goworko <info@lukaszgoworko.de>
 */
interface KernelEventInterface
{
    public function getKernel();

    public function getRequest();

    public function getRequestType();

    public function isMainRequest(): bool;
}
