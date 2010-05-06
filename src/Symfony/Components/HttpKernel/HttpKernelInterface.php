<?php

namespace Symfony\Components\HttpKernel;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * HttpKernelInterface.
 *
 * @package    Symfony
 * @subpackage Components_HttpKernel
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface HttpKernelInterface
{
    /**
     * Handles a request to convert it to a response.
     *
     * @param  Request $request A Request instance
     * @param  Boolean $main    Whether this is the main request or not
     * @param  Boolean $raw     Whether to catch exceptions or not
     *
     * @return Response $response A Response instance
     */
    public function handle(Request $request = null, $main = true, $raw = false);

    /**
     * Gets the Request instance associated with the main request.
     *
     * @return Request A Request instance
     */
    public function getRequest();
}
