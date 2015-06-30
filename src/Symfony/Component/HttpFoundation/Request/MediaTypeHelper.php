<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;

class MediaTypeHelper
{
    /**
     * @var array
     */
    protected static $formats;

    /**
     * @var array
     */
    protected $charsets;

    /**
     * @var array
     */
    protected $encodings;

    /**
     * Gets the mime type associated with the format.
     *
     * @param string $format The format
     *
     * @return string The associated mime type (null if not found)
     *
     * @api
     */
    public function getMimeType($format)
    {
        if (null === static::$formats) {
            static::initializeFormats();
        }

        return isset(static::$formats[$format]) ? static::$formats[$format][0] : null;
    }

    /**
     * Gets the format associated with the mime type.
     *
     * @param string $mimeType The associated mime type
     *
     * @return string|null The format (null if not found)
     *
     * @api
     */
    public function getFormat($mimeType)
    {
        if (false !== $pos = strpos($mimeType, ';')) {
            $mimeType = substr($mimeType, 0, $pos);
        }

        if (null === static::$formats) {
            static::initializeFormats();
        }

        foreach (static::$formats as $format => $mimeTypes) {
            if (in_array($mimeType, (array) $mimeTypes)) {
                return $format;
            }
        }
        return null;
    }

    /**
     * Associates a format with mime types.
     *
     * @param string       $format    The format
     * @param string|array $mimeTypes The associated mime types (the preferred one must be the first as it will be used as the content type)
     *
     * @api
     */
    public function setFormat($format, $mimeTypes)
    {
        if (null === static::$formats) {
            static::initializeFormats();
        }

        static::$formats[$format] = is_array($mimeTypes) ? $mimeTypes : array($mimeTypes);
    }

    /**
     * Gets the format associated with the request.
     *
     * @param Request $request
     * @return null|string The format (null if no content type is present)
     *
     * @api
     */
    public function getContentType(Request $request)
    {
        return $this->getFormat($request->headers->get('CONTENT_TYPE'));
    }

    /**
     * Gets a list of charsets acceptable by the client browser.
     *
     * @param Request $request
     * @return array List of charsets in preferable order
     *
     * @api
     */
    public function getCharsets(Request $request)
    {
        return array_keys(AcceptHeader::fromString($request->headers->get('Accept-Charset'))->all());
    }

    /**
     * Gets a list of encodings acceptable by the client browser.
     *
     * @param Request $request
     * @return array List of encodings in preferable order
     */
    public function getEncodings(Request $request)
    {
        return array_keys(AcceptHeader::fromString($request->headers->get('Accept-Encoding'))->all());
    }

    /**
     * Initializes HTTP request formats.
     */
    protected static function initializeFormats()
    {
        static::$formats = array(
            'html' => array('text/html', 'application/xhtml+xml'),
            'txt' => array('text/plain'),
            'js' => array('application/javascript', 'application/x-javascript', 'text/javascript'),
            'css' => array('text/css'),
            'json' => array('application/json', 'application/x-json'),
            'xml' => array('text/xml', 'application/xml', 'application/x-xml'),
            'rdf' => array('application/rdf+xml'),
            'atom' => array('application/atom+xml'),
            'rss' => array('application/rss+xml'),
            'form' => array('application/x-www-form-urlencoded'),
        );
    }
}