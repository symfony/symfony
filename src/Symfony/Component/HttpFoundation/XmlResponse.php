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

use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * Represents an HTTP response in XML format.
 *
 * @author Adamo Aerendir Crespi <hello@aerendir.me>
 */
class XmlResponse extends Response
{
    protected $data;

    /**
     * @param array|string $data        The response data
     * @param string       $rootNode
     * @param bool         $xml         If the data is already an XML string
     * @param array        $context
     * @param int          $loadOptions
     * @param int          $status      The response status code
     * @param array        $headers     An array of response headers
     */
    public function __construct($data = array(), string $rootNode = 'response', bool $xml = false, array $context = array(), int $loadOptions = null, int $status = 200, array $headers = array())
    {
        parent::__construct('', $status, $headers);

        $xml ? $this->setXml($data) : $this->setData($data, $rootNode, $context);
    }

    /**
     * Sets a raw string containing an XML document to be sent.
     *
     * @param string $xml
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function setXml(string $xml)
    {
        $this->data = $xml;

        return $this->update();
    }

    /**
     * Sets the data to be sent as JSON.
     *
     * @param array    $data
     * @param string   $rootNode
     * @param array    $context
     * @param int|null $loadOptions = null
     *
     * @throws \Exception
     *
     * @return XmlResponse
     */
    public function setData(array $data = array(), $rootNode = 'response', array $context = array(), $loadOptions = null)
    {
        if (false === class_exists(XmlEncoder::class)) {
            throw new \RuntimeException('To use the XmlResponse you need to install the symfony/serializer component.');
        }

        $encoder = new XmlEncoder($rootNode, $loadOptions);

        $data = $encoder->encode($data, 'xml', $context);

        return $this->setXml($data);
    }

    /**
     * Updates the content and headers according to the JSON data and callback.
     *
     * @return $this
     */
    protected function update()
    {
        $this->headers->set('Content-Type', 'text/xml');

        return $this->setContent($this->data);
    }
}
