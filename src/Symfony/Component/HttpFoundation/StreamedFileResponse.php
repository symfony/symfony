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

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * StreamedFileResponse is an HTTP response that streams a file.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class StreamedFileResponse extends StreamedResponse
{
    protected $filename;
    protected $method;

    /**
     * Constructor.
     *
     * @param string  $filename The file to stream
     * @param integer $status   The response status code
     * @param array   $headers  An array of response headers
     * @param string  $method   The method to use to stream the file, like 'readfile' or 'x-sendfile'
     *
     * @api
     */
    public function __construct($filename, $status = 200, $headers = array(), $method = 'readfile')
    {
        parent::__construct(null, $status, $headers);

        $this->filename = $filename;
        $this->method = $method;

        switch ($this->method) {
            case 'readfile':
                $this->setCallback(function () use ($filename) {
                    if (FALSE === @readfile($filename)) {
                        throw new NotFoundHttpException();
                    }
                });
                break;

            case 'x-sendfile':
                $this->setCallback(function() { });
                break;
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function create($filename, $status = 200, $headers = array())
    {
        return new static($filename, $status, $headers);
    }

    /**
     * @{inheritDoc}
     */
    public function prepare(Request $request)
    {
        switch ($this->method) {
            case 'x-sendfile':
                $this->headers->set('X-Sendfile', $this->filename);
                break;
        }

        return $parent::prepare($request);
    }
}
