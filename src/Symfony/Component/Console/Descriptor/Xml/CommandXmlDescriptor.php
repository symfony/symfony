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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\CommandDescription;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class CommandXmlDescriptor extends AbstractXmlDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function buildDocument(\DOMNode $parent, $object)
    {
        $dom = $parent instanceof \DOMDocument ? $parent : $parent->ownerDocument;
        $parent->appendChild($commandXML = $dom->createElement('command'));

        /** @var Command $object */
        $description = new CommandDescription($object);
        
        $commandXML->setAttribute('id', $description->getName());
        $commandXML->setAttribute('name', $description->getName());

        $commandXML->appendChild($usageXML = $dom->createElement('usage'));
        $usageXML->appendChild($dom->createTextNode(sprintf($description->getSynopsis(), '')));

        $commandXML->appendChild($descriptionXML = $dom->createElement('description'));
        $descriptionXML->appendChild($dom->createTextNode(str_replace("\n", "\n ", $description->getDescription())));

        $commandXML->appendChild($helpXML = $dom->createElement('help'));
        $helpXML->appendChild($dom->createTextNode(str_replace("\n", "\n ", $description->getHelp())));

        $commandXML->appendChild($aliasesXML = $dom->createElement('aliases'));
        foreach ($description->getAliases() as $alias) {
            $aliasesXML->appendChild($aliasXML = $dom->createElement('alias'));
            $aliasXML->appendChild($dom->createTextNode($alias));
        }

        $descriptor = new InputDefinitionXmlDescriptor();
        $descriptor->buildDocument($commandXML, $description->getDefinition());
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Command;
    }
}
