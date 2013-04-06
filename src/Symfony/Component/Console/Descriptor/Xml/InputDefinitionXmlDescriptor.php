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

use Symfony\Component\Console\Input\InputDefinition;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class InputDefinitionXmlDescriptor extends AbstractXmlDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function buildDocument(\DOMNode $parent, $object)
    {
        if ($parent instanceof \DOMDocument) {
            $dom = $parent;
            $parent->appendChild($parent = $dom->createElement('definition'));
        } else {
            $dom = $parent->ownerDocument;
        }

        $parent->appendChild($argumentsXML = $dom->createElement('arguments'));
        $descriptor = new InputArgumentXmlDescriptor();
        /** @var InputDefinition $object */
        foreach ($object->getArguments() as $argument) {
            $descriptor->buildDocument($argumentsXML, $argument);
        }

        $parent->appendChild($optionsXML = $dom->createElement('options'));
        $descriptor = new InputOptionXmlDescriptor();
        foreach ($object->getOptions() as $option) {
            $descriptor->buildDocument($optionsXML, $option);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof InputDefinition;
    }
}
