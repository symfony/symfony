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
 * Response represents an HTTP response in JSON format.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class JsonResponse extends Response
{
    /**
     * Constructor.
     *
     * @param mixed   $data    The response data
     * @param integer $status  The response status code
     * @param array   $headers An array of response headers
     * @param string  $jsonp   A JSONP callback name
     */
    public function __construct($data = array(), $status = 200, $headers = array(), $jsonp = '')
    {
        // root should be JSON object, not array
        if (is_array($data) && 0 === count($data)) {
            $data = new \ArrayObject();
        }

        $content = json_encode($data);
        $contentType = 'application/json';
        if (!empty($jsonp)) {
            $content = sprintf('%s(%s);', $jsonp, $content);
            // Not using application/javascript for compatibility reasons with older browsers.
            $contentType = 'text/javascript';
        }

        parent::__construct(
            $content,
            $status,
            array_merge(array('Content-Type' => $contentType), $headers)
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param string  $jsonp   A JSONP callback name.
     */
    static public function create($data = array(), $status = 200, $headers = array(), $jsonp = '')
    {
        return new static($data, $status, $headers, $jsonp = '');
    }
}
