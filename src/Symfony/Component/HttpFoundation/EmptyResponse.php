<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

class EmptyResponse extends Response
{
    /**
     * @param array $headers
     */
    public function __construct(array $headers = [])
    {
        parent::__construct(null, Response::HTTP_NO_CONTENT, $headers);
    }

    /**
     * Factory method for chainability.
     *
     * Example:
     *
     *     return Response::create()
     *         ->setSharedMaxAge(300);
     *
     * @param array $headers An array of response headers
     *
     * @return static
     */
    public static function create($content = '', $status = 200, $headers = [])
    {
        return new static($headers);
    }
}
