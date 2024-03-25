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
 *
 * @internal
 */
class XmlDescriptor extends Descriptor
{
    public function getInputDefinitionDocument(InputDefinition $definition): \DOMDocument|\DOM\XMLDocument
    {
        $dom = $this->createXMLDocument();
        $dom->appendChild($definitionXML = $this->createElement($dom, 'definition'));

        $definitionXML->appendChild($argumentsXML = $this->createElement($dom, 'arguments'));
        foreach ($definition->getArguments() as $argument) {
            $this->appendDocument($argumentsXML, $this->getInputArgumentDocument($argument));
        }

        $definitionXML->appendChild($optionsXML = $this->createElement($dom, 'options'));
        foreach ($definition->getOptions() as $option) {
            $this->appendDocument($optionsXML, $this->getInputOptionDocument($option));

            if ($option->isNegatable()) {
                $this->appendDocument($optionsXML, $this->getNegatableInputOptionDocument($option));
            }
        }

        return $dom;
    }

    public function getCommandDocument(Command $command, bool $short = false): \DOMDocument|\DOM\XMLDocument
    {
        $dom = $this->createXMLDocument();
        $dom->appendChild($commandXML = $this->createElement($dom, 'command'));

        $commandXML->setAttribute('id', $command->getName());
        $commandXML->setAttribute('name', $command->getName());
        $commandXML->setAttribute('hidden', $command->isHidden() ? 1 : 0);

        $commandXML->appendChild($usagesXML = $this->createElement($dom, 'usages'));

        $commandXML->appendChild($descriptionXML = $this->createElement($dom, 'description'));
        $descriptionXML->appendChild($dom->createTextNode(str_replace("\n", "\n ", $command->getDescription())));

        if ($short) {
            foreach ($command->getAliases() as $usage) {
                $usagesXML->appendChild($this->createElement($dom, 'usage', $usage));
            }
        } else {
            $command->mergeApplicationDefinition(false);

            foreach (array_merge([$command->getSynopsis()], $command->getAliases(), $command->getUsages()) as $usage) {
                $usagesXML->appendChild($this->createElement($dom, 'usage', $usage));
            }

            $commandXML->appendChild($helpXML = $this->createElement($dom, 'help'));
            $helpXML->appendChild($dom->createTextNode(str_replace("\n", "\n ", $command->getProcessedHelp())));

            $definitionXML = $this->getInputDefinitionDocument($command->getDefinition());
            $this->appendDocument($commandXML, $definitionXML->getElementsByTagName('definition')->item(0));
        }

        return $dom;
    }

    public function getApplicationDocument(Application $application, ?string $namespace = null, bool $short = false): \DOMDocument|\DOM\XMLDocument
    {
        $dom = $this->createXMLDocument();
        $dom->appendChild($rootXml = $this->createElement($dom, 'symfony'));

        if ('UNKNOWN' !== $application->getName()) {
            $rootXml->setAttribute('name', $application->getName());
            if ('UNKNOWN' !== $application->getVersion()) {
                $rootXml->setAttribute('version', $application->getVersion());
            }
        }

        $rootXml->appendChild($commandsXML = $this->createElement($dom, 'commands'));

        $description = new ApplicationDescription($application, $namespace, true);

        if ($namespace) {
            $commandsXML->setAttribute('namespace', $namespace);
        }

        foreach ($description->getCommands() as $command) {
            $this->appendDocument($commandsXML, $this->getCommandDocument($command, $short));
        }

        if (!$namespace) {
            $rootXml->appendChild($namespacesXML = $this->createElement($dom, 'namespaces'));

            foreach ($description->getNamespaces() as $namespaceDescription) {
                $namespacesXML->appendChild($namespaceArrayXML = $this->createElement($dom, 'namespace'));
                $namespaceArrayXML->setAttribute('id', $namespaceDescription['id']);

                foreach ($namespaceDescription['commands'] as $name) {
                    $namespaceArrayXML->appendChild($commandXML = $this->createElement($dom, 'command'));
                    $commandXML->appendChild($dom->createTextNode($name));
                }
            }
        }

        return $dom;
    }

    protected function describeInputArgument(InputArgument $argument, array $options = []): void
    {
        $this->writeDocument($this->getInputArgumentDocument($argument));
    }

    protected function describeInputOption(InputOption $option, array $options = []): void
    {
        $this->writeDocument($this->getInputOptionDocument($option));
    }

    protected function describeInputDefinition(InputDefinition $definition, array $options = []): void
    {
        $this->writeDocument($this->getInputDefinitionDocument($definition));
    }

    protected function describeCommand(Command $command, array $options = []): void
    {
        $this->writeDocument($this->getCommandDocument($command, $options['short'] ?? false));
    }

    protected function describeApplication(Application $application, array $options = []): void
    {
        $this->writeDocument($this->getApplicationDocument($application, $options['namespace'] ?? null, $options['short'] ?? false));
    }

    /**
     * Appends document children to parent node.
     */
    private function appendDocument(\DOMNode|\DOM\Node $parentNode, \DOMNode|\DOM\Node $importedParent): void
    {
        foreach ($importedParent->childNodes as $childNode) {
            $parentNode->appendChild($parentNode->ownerDocument->importNode($childNode, true));
        }
    }

    /**
     * Writes DOM document.
     */
    private function writeDocument(\DOMDocument|\DOM\XMLDocument $dom): void
    {
        $dom->formatOutput = true;
        $this->write($dom->saveXML());
    }

    private function getInputArgumentDocument(InputArgument $argument): \DOMDocument|\DOM\XMLDocument
    {
        $dom = $this->createXMLDocument();

        $dom->appendChild($objectXML = $this->createElement($dom, 'argument'));
        $objectXML->setAttribute('name', $argument->getName());
        $objectXML->setAttribute('is_required', $argument->isRequired() ? 1 : 0);
        $objectXML->setAttribute('is_array', $argument->isArray() ? 1 : 0);
        $objectXML->appendChild($descriptionXML = $this->createElement($dom, 'description'));
        $descriptionXML->appendChild($dom->createTextNode($argument->getDescription()));

        $objectXML->appendChild($defaultsXML = $this->createElement($dom, 'defaults'));
        $defaults = \is_array($argument->getDefault()) ? $argument->getDefault() : (\is_bool($argument->getDefault()) ? [var_export($argument->getDefault(), true)] : ($argument->getDefault() ? [$argument->getDefault()] : []));
        foreach ($defaults as $default) {
            $defaultsXML->appendChild($defaultXML = $this->createElement($dom, 'default'));
            $defaultXML->appendChild($dom->createTextNode($default));
        }

        return $dom;
    }

    private function getInputOptionDocument(InputOption $option): \DOMDocument|\DOM\XMLDocument
    {
        $dom = $this->createXMLDocument();

        $dom->appendChild($objectXML = $this->createElement($dom, 'option'));
        $objectXML->setAttribute('name', '--'.$option->getName());
        $pos = strpos($option->getShortcut() ?? '', '|');
        if (false !== $pos) {
            $objectXML->setAttribute('shortcut', '-'.substr($option->getShortcut(), 0, $pos));
            $objectXML->setAttribute('shortcuts', '-'.str_replace('|', '|-', $option->getShortcut()));
        } else {
            $objectXML->setAttribute('shortcut', $option->getShortcut() ? '-'.$option->getShortcut() : '');
        }
        $objectXML->setAttribute('accept_value', $option->acceptValue() ? 1 : 0);
        $objectXML->setAttribute('is_value_required', $option->isValueRequired() ? 1 : 0);
        $objectXML->setAttribute('is_multiple', $option->isArray() ? 1 : 0);
        $objectXML->appendChild($descriptionXML = $this->createElement($dom, 'description'));
        $descriptionXML->appendChild($dom->createTextNode($option->getDescription()));

        if ($option->acceptValue()) {
            $defaults = \is_array($option->getDefault()) ? $option->getDefault() : (\is_bool($option->getDefault()) ? [var_export($option->getDefault(), true)] : ($option->getDefault() ? [$option->getDefault()] : []));
            $objectXML->appendChild($defaultsXML = $this->createElement($dom, 'defaults'));

            if (!empty($defaults)) {
                foreach ($defaults as $default) {
                    $defaultsXML->appendChild($defaultXML = $this->createElement($dom, 'default'));
                    $defaultXML->appendChild($dom->createTextNode($default));
                }
            }
        }

        return $dom;
    }

    private function getNegatableInputOptionDocument(InputOption $option): \DOMDocument|\DOM\XMLDocument
    {
        $dom = $this->createXMLDocument();

        $dom->appendChild($objectXML = $this->createElement($dom, 'option'));
        $objectXML->setAttribute('name', '--no-'.$option->getName());
        $objectXML->setAttribute('shortcut', '');
        $objectXML->setAttribute('accept_value', 0);
        $objectXML->setAttribute('is_value_required', 0);
        $objectXML->setAttribute('is_multiple', 0);
        $objectXML->appendChild($descriptionXML = $this->createElement($dom, 'description'));
        $descriptionXML->appendChild($dom->createTextNode('Negate the "--'.$option->getName().'" option'));

        return $dom;
    }

    private function createXMLDocument(): \DOMDocument|\DOM\XMLDocument
    {
        if (class_exists(\DOM\Document::class)) {
            return \DOM\XMLDocument::createEmpty();
        }

        return new \DOMDocument('1.0', 'UTF-8');
    }

    private function createElement(\DOMDocument|\DOM\XMLDocument $dom, string $localName, string $value = ''): \DOMElement|\DOM\Element
    {
        if ($dom instanceof \DOM\XMLDocument) {
            $element = $dom->createElement($localName);

            if ('' !== $value) {
                $element->textContent = $value;
            }

            return $element;
        }

        return $dom->createElement($localName, $value);
    }
}
