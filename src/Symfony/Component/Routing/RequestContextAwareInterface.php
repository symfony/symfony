<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing;

/**
 * Gives access to the request context used to generate and match URLs.
 */
interface RequestContextAwareInterface
{
    /**
     * Sets the request context.
     */
    public function setContext(RequestContext $context): void;

    /**
     * Gets the request context.
     */
    public function getContext(): RequestContext;
}
