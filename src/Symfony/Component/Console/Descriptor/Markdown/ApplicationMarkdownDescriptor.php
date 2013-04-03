<?php

namespace Symfony\Component\Console\Descriptor\Markdown;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Descriptor\ApplicationDescription;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ApplicationMarkdownDescriptor extends AbstractMarkdownDescriptor
{
    /**
     * @var string|null
     */
    private $namespace;

    /**
     * @param string|null $namespace
     * @param int         $maxWidth
     */
    public function __construct($namespace = null, $maxWidth = 120)
    {
        $this->namespace = $namespace;
        parent::__construct($maxWidth);
    }

    /**
     * {@inheritdoc}
     */
    public function configure(array $options)
    {
        $this->namespace = $options['namespace'];

        return parent::configure($options);
    }

    /**
     * {@inheritdoc}
     */
    public function getDocument($object)
    {
        /** @var Application $object */
        $description = new ApplicationDescription($object, $this->namespace);
        $descriptor = new CommandMarkdownDescriptor();
        $document = new Document\Document(array(new Document\Title($object->getName(), 1)));

        foreach ($description->getNamespaces() as $namespace) {
            if (ApplicationDescription::GLOBAL_NAMESPACE !== $namespace['id']) {
                $document->add(new Document\Paragraph('**'.$namespace['id'].':**'));
            }

            $document->add(new Document\UnorderedList($namespace['commands']));
        }

        foreach ($description->getCommands() as $command) {
            $document->add($descriptor->getDocument($command));
        }

        return $document;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Application;
    }
}
