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
 * @api
 */
interface RequestContextAwareInterface
{
    /**
     * Sets the request context.
     *
     * @param RequestContext $context The context
     *
     * @api
     *
     * @since v2.1.0
     */
    public function setContext(RequestContext $context);

    /**
     * Gets the request context.
     *
     * @return RequestContext The context
     *
     * @api
     *
     * @since v2.1.0
     */
    public function getContext();
}
