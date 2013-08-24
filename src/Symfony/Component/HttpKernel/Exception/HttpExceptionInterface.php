<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Exception;

/**
 * Interface for HTTP error exceptions.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
interface HttpExceptionInterface
{
    /**
     * Returns the status code.
     *
     * @return integer An HTTP response status code
     *
     * @since v2.1.0
     */
    public function getStatusCode();

    /**
     * Returns response headers.
     *
     * @return array Response headers
     *
     * @since v2.1.0
     */
    public function getHeaders();
}
