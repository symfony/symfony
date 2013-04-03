<?php

namespace Symfony\Component\Console\Descriptor\Markdown;

use Symfony\Component\Console\Input\InputDefinition;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class InputDefinitionMarkdownDescriptor extends AbstractMarkdownDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function getDocument($object)
    {
        $document = new Document\Document();
        $argumentDescriptor = new InputArgumentMarkdownDescriptor();
        $optionDescriptor = new InputOptionMarkdownDescriptor();

        /** @var InputDefinition $object */

        if (count($object->getArguments()) > 0) {
            $document->add(new Document\Title('Arguments:', 3));
            foreach ($object->getArguments() as $argument) {
                $document->add($argumentDescriptor->getDocument($argument));
            }
        }

        if (count($object->getOptions()) > 0) {
            $document->add(new Document\Title('Options:', 3));
            foreach ($object->getOptions() as $option) {
                $document->add($optionDescriptor->getDocument($option));
            }
        }

        return $document;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof InputDefinition;
    }
}
