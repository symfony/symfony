<?php

namespace Symfony\Component\Console\Descriptor\Json;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Descriptor\ApplicationDescription;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ApplicationJsonDescriptor extends AbstractJsonDescriptor
{
    /**
     * @var string|null
     */
    private $namespace;

    /**
     * @param string|null $namespace
     * @param int         $encodingOptions
     */
    public function __construct($namespace = null, $encodingOptions = 0)
    {
        $this->namespace = $namespace;
        parent::__construct($encodingOptions);
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
    public function getData($object)
    {
        /** @var Application $object */
        $description = new ApplicationDescription($object, $this->namespace);
        $descriptor = $this->build(new CommandJsonDescriptor());
        $commands = array_map(array($descriptor, 'getData'), $description->getCommands());

        return null === $this->namespace
            ? array('commands' => $commands, 'namespaces' => $description->getNamespaces())
            : array('commands' => $commands, 'namespace' => $this->namespace);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Application;
    }
}
