<?php

namespace Symfony\Component\Console\Descriptor\Markdown;

use Symfony\Component\Console\Input\InputArgument;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class InputArgumentMarkdownDescriptor extends AbstractMarkdownDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function getDocument($object)
    {
        /** @var InputArgument $object */
        return new Document\Document(array(
            new Document\Paragraph('**'.$object->getName().':**'),
            new Document\UnorderedList(array(
                'Name: '.($object->getName() ?: '<none>'),
                'Is required: '.($object->isRequired() ? 'yes' : 'no'),
                'Is array: '.($object->isArray() ? 'yes' : 'no'),
                'Description: '.($object->getDescription() ?: '<none>'),
                'Default: `'.str_replace("\n", '', var_export($object->getDefault(), true)).'`',
            )),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof InputArgument;
    }
}
