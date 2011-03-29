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

/**
 * JsonResponse is a Response, that has JSON content.
 *
 * @author Christian Hoffmeister <choffmeister.github@googlemail.com>
 */
class JsonResponse extends Response
{
    /**
     * Creates a response with the object in JSON representation as content.
     *
     * @param mixed   $object The object
     * @param integer $status The status code (200 by default)
     *
     * @see http://tools.ietf.org/html/rfc2616#section-10.3.5
     */
    public function __construct($object, $status = 200)
    {
        parent::__construct(\json_encode($object), $status);
    }
}
