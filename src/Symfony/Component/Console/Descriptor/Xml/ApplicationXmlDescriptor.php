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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\ApplicationDescription;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ApplicationXmlDescriptor extends AbstractXmlDescriptor
{
    /**
     * @var string|null
     */
    private $namespace;

    /**
     * @param string|null $namespace
     */
    public function __construct($namespace = null)
    {
        $this->namespace = $namespace;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(array $options)
    {
        if (isset($options['namespace'])) {
            $this->namespace = $options['namespace'];
        }

        return parent::configure($options);
    }

    /**
     * {@inheritdoc}
     */
    public function buildDocument(\DOMNode $parent, $object)
    {
        $dom = $parent instanceof \DOMDocument ? $parent : $parent->ownerDocument;
        $dom->appendChild($rootXml = $dom->createElement('symfony'));
        $rootXml->appendChild($commandsXML = $dom->createElement('commands'));

        /** @var Application $object */
        $description = new ApplicationDescription($object, $this->namespace);

        if ($this->namespace) {
            $commandsXML->setAttribute('namespace', $this->namespace);
        }

        foreach ($description->getCommands() as $command) {
            $this->getDescriptor($command)->buildDocument($commandsXML, $command);
        }

        if (!$this->namespace) {
            $rootXml->appendChild($namespacesXML = $dom->createElement('namespaces'));

            foreach ($description->getNamespaces() as $namespace) {
                $namespacesXML->appendChild($namespaceArrayXML = $dom->createElement('namespace'));
                $namespaceArrayXML->setAttribute('id', $namespace['id']);

                foreach ($namespace['commands'] as $name) {
                    $namespaceArrayXML->appendChild($commandXML = $dom->createElement('command'));
                    $commandXML->appendChild($dom->createTextNode($name));
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Application;
    }
}
