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

use Symfony\Component\Console\Input\InputOption;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class InputOptionXmlDescriptor extends AbstractXmlDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function buildDocument(\DOMNode $parent, $object)
    {
        $dom = $parent instanceof \DOMDocument ? $parent : $parent->ownerDocument;

        /** @var InputOption $object */
        $parent->appendChild($objectXML = $dom->createElement('option'));
        $objectXML->setAttribute('name', '--'.$object->getName());
        $objectXML->setAttribute('shortcut', $object->getShortcut() ? '-'.$object->getShortcut() : '');
        $objectXML->setAttribute('accept_value', $object->acceptValue() ? 1 : 0);
        $objectXML->setAttribute('is_value_required', $object->isValueRequired() ? 1 : 0);
        $objectXML->setAttribute('is_multiple', $object->isArray() ? 1 : 0);
        $objectXML->appendChild($descriptionXML = $dom->createElement('description'));
        $descriptionXML->appendChild($dom->createTextNode($object->getDescription()));

        if ($object->acceptValue()) {
            $defaults = is_array($object->getDefault()) ? $object->getDefault() : (is_bool($object->getDefault()) ? array(var_export($object->getDefault(), true)) : ($object->getDefault() ? array($object->getDefault()) : array()));

            if (!empty($defaults)) {
                $objectXML->appendChild($defaultsXML = $dom->createElement('defaults'));
                foreach ($defaults as $default) {
                    $defaultsXML->appendChild($defaultXML = $dom->createElement('default'));
                    $defaultXML->appendChild($dom->createTextNode($default));
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof InputOption;
    }
}
