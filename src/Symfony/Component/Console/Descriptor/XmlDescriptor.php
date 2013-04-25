<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * XML descriptor.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class XmlDescriptor extends Descriptor
{
    /**
     * {@inheritdoc}
     */
    protected function describeInputArgument(InputArgument $argument, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');

        $dom->appendChild($objectXML = $dom->createElement('argument'));
        $objectXML->setAttribute('name', $argument->getName());
        $objectXML->setAttribute('is_required', $argument->isRequired() ? 1 : 0);
        $objectXML->setAttribute('is_array', $argument->isArray() ? 1 : 0);
        $objectXML->appendChild($descriptionXML = $dom->createElement('description'));
        $descriptionXML->appendChild($dom->createTextNode($argument->getDescription()));

        $objectXML->appendChild($defaultsXML = $dom->createElement('defaults'));
        $defaults = is_array($argument->getDefault()) ? $argument->getDefault() : (is_bool($argument->getDefault()) ? array(var_export($argument->getDefault(), true)) : ($argument->getDefault() ? array($argument->getDefault()) : array()));
        foreach ($defaults as $default) {
            $defaultsXML->appendChild($defaultXML = $dom->createElement('default'));
            $defaultXML->appendChild($dom->createTextNode($default));
        }

        return $this->output($dom, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeInputOption(InputOption $option, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');

        $dom->appendChild($objectXML = $dom->createElement('option'));
        $objectXML->setAttribute('name', '--'.$option->getName());
        $pos = strpos($option->getShortcut(), '|');
        if (false !== $pos) {
            $objectXML->setAttribute('shortcut', '-'.substr($option->getShortcut(), 0, $pos));
            $objectXML->setAttribute('shortcuts', '-'.implode('|-', explode('|', $option->getShortcut())));
        } else {
            $objectXML->setAttribute('shortcut', $option->getShortcut() ? '-'.$option->getShortcut() : '');
        }
        $objectXML->setAttribute('accept_value', $option->acceptValue() ? 1 : 0);
        $objectXML->setAttribute('is_value_required', $option->isValueRequired() ? 1 : 0);
        $objectXML->setAttribute('is_multiple', $option->isArray() ? 1 : 0);
        $objectXML->appendChild($descriptionXML = $dom->createElement('description'));
        $descriptionXML->appendChild($dom->createTextNode($option->getDescription()));

        if ($option->acceptValue()) {
            $defaults = is_array($option->getDefault()) ? $option->getDefault() : (is_bool($option->getDefault()) ? array(var_export($option->getDefault(), true)) : ($option->getDefault() ? array($option->getDefault()) : array()));
            $objectXML->appendChild($defaultsXML = $dom->createElement('defaults'));

            if (!empty($defaults)) {
                foreach ($defaults as $default) {
                    $defaultsXML->appendChild($defaultXML = $dom->createElement('default'));
                    $defaultXML->appendChild($dom->createTextNode($default));
                }
            }
        }

        return $this->output($dom, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeInputDefinition(InputDefinition $definition, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($definitionXML = $dom->createElement('definition'));

        $definitionXML->appendChild($argumentsXML = $dom->createElement('arguments'));
        foreach ($definition->getArguments() as $argument) {
            $this->appendDocument($argumentsXML, $this->describeInputArgument($argument, array('as_dom' => true)));
        }

        $definitionXML->appendChild($optionsXML = $dom->createElement('options'));
        foreach ($definition->getOptions() as $option) {
            $this->appendDocument($optionsXML, $this->describeInputOption($option, array('as_dom' => true)));
        }

        return $this->output($dom, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeCommand(Command $command, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($commandXML = $dom->createElement('command'));

        $command->getSynopsis();
        $command->mergeApplicationDefinition(false);

        $commandXML->setAttribute('id', $command->getName());
        $commandXML->setAttribute('name', $command->getName());

        $commandXML->appendChild($usageXML = $dom->createElement('usage'));
        $usageXML->appendChild($dom->createTextNode(sprintf($command->getSynopsis(), '')));

        $commandXML->appendChild($descriptionXML = $dom->createElement('description'));
        $descriptionXML->appendChild($dom->createTextNode(str_replace("\n", "\n ", $command->getDescription())));

        $commandXML->appendChild($helpXML = $dom->createElement('help'));
        $helpXML->appendChild($dom->createTextNode(str_replace("\n", "\n ", $command->getProcessedHelp())));

        $commandXML->appendChild($aliasesXML = $dom->createElement('aliases'));
        foreach ($command->getAliases() as $alias) {
            $aliasesXML->appendChild($aliasXML = $dom->createElement('alias'));
            $aliasXML->appendChild($dom->createTextNode($alias));
        }

        $definitionXML = $this->describeInputDefinition($command->getNativeDefinition(), array('as_dom' => true));
        $this->appendDocument($commandXML, $definitionXML->getElementsByTagName('definition')->item(0));

        return $this->output($dom, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeApplication(Application $application, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($rootXml = $dom->createElement('symfony'));
        $rootXml->appendChild($commandsXML = $dom->createElement('commands'));

        $describedNamespace = isset($options['namespace']) ? $options['namespace'] : null;
        $description = new ApplicationDescription($application, $describedNamespace);

        if ($describedNamespace) {
            $commandsXML->setAttribute('namespace', $describedNamespace);
        }

        foreach ($description->getCommands() as $command) {
            $this->appendDocument($commandsXML, $this->describeCommand($command, array('as_dom' => true)));
        }

        if (!$describedNamespace) {
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

        return $this->output($dom, $options);
    }

    /**
     * Appends document children to parent node.
     *
     * @param \DOMNode $parentNode
     * @param \DOMNode $importedParent
     */
    private function appendDocument(\DOMNode $parentNode, \DOMNode $importedParent)
    {
        foreach ($importedParent->childNodes as $childNode) {
            $parentNode->appendChild($parentNode->ownerDocument->importNode($childNode, true));
        }
    }

    /**
     * Outputs document as DOMDocument or string according to options.
     *
     * @param \DOMDocument $dom
     * @param array        $options
     *
     * @return \DOMDocument|string
     */
    private function output(\DOMDocument $dom, array $options)
    {
        if (isset($options['as_dom']) && $options['as_dom']) {
            return $dom;
        }

        $dom->formatOutput = true;

        return $dom->saveXML();
    }
}
