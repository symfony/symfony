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

use Symfony\Component\Console\Input\InputArgument;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class InputArgumentXmlDescriptor extends AbstractXmlDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function buildDocument(\DOMNode $parent, $object)
    {
        $dom = $parent instanceof \DOMDocument ? $parent : $parent->ownerDocument;

        /** @var InputArgument $object */
        $parent->appendChild($objectXML = $dom->createElement('argument'));
        $objectXML->setAttribute('name', $object->getName());
        $objectXML->setAttribute('is_required', $object->isRequired() ? 1 : 0);
        $objectXML->setAttribute('is_array', $object->isArray() ? 1 : 0);
        $objectXML->appendChild($descriptionXML = $dom->createElement('description'));
        $descriptionXML->appendChild($dom->createTextNode($object->getDescription()));

        $objectXML->appendChild($defaultsXML = $dom->createElement('defaults'));
        $defaults = is_array($object->getDefault()) ? $object->getDefault() : (is_bool($object->getDefault()) ? array(var_export($object->getDefault(), true)) : ($object->getDefault() ? array($object->getDefault()) : array()));
        foreach ($defaults as $default) {
            $defaultsXML->appendChild($defaultXML = $dom->createElement('default'));
            $defaultXML->appendChild($dom->createTextNode($default));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof InputArgument;
    }
}
