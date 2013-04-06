<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor\Xml;

use Symfony\Component\Console\Descriptor\DescriptorInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractXmlDescriptor implements DescriptorInterface
{
    /**
     * {@inheritdoc}
     */
    public function configure(array $options)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function describe($object)
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $this->buildDocument($document, $object);

        return $document->saveXML();
    }

    /**
     * Builds DOM document with object.
     *
     * @param \DOMNode $parent
     * @param object   $object
     */
    abstract public function buildDocument(\DOMNode $parent, $object);

    /**
     * {@inheritdoc}
     */
    public function getFormat()
    {
        return 'xml';
    }

    /**
     * {@inheritdoc}
     */
    public function useFormatting()
    {
        return false;
    }
}
