<?php

namespace Symfony\Component\Console\Descriptor\Markdown;

use Symfony\Component\Console\Command\Command;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class CommandMarkdownDescriptor extends AbstractMarkdownDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function getDocument($object)
    {
        $definitionDescriptor = new InputDefinitionMarkdownDescriptor();

        $object->getProcessedHelp();

        /** @var Command $object */
        return new Document\Document(array(
            new Document\Title($object->getName(), 2),
            new Document\UnorderedList(array(
                'Description: '.($object->getDescription() ?: '<none>'),
                'Usage: `'.$object->getSynopsis().'`',
                'Aliases: '.(count($object->getAliases()) ? '`'.implode('`, `', $object->getAliases()).'`' : '<none>'),
            )),
            new Document\Paragraph($object->getProcessedHelp()),
            $definitionDescriptor->getDocument($object->getDefinition()),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Command;
    }
}
