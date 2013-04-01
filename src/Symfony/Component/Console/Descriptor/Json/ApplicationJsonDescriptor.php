<?php

namespace Symfony\Component\Console\Descriptor\Json;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ApplicationJsonDescriptor extends AbstractJsonDescriptor
{
    /**
     * @var string|null
     */
    private $namespace;

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
        $commandDescriptor = $this->build(new CommandJsonDescriptor());

        /** @var Application $object */
        $commands = $object->all($this->namespace ? $object->findNamespace($this->namespace) : null);
        $data = array('commands' => array(), 'namespaces' => array());

        foreach ($this->sortCommands($object, $commands) as $space => $commands) {
            $namespaceData = array('id' => $space, 'commands' => array());

            /** @var Command $command */
            foreach ($commands as $command) {
                if (!$command->getName()) {
                    continue;
                }

                $data['commands'][] = $commandDescriptor->getData($command);
                $namespaceData['commands'][] = $command->getName();
            }

            $data['namespaces'][] = $namespaceData;
        }

        if ($this->namespace) {
            $data['namespace'] = $this->namespace;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Application;
    }

    /**
     * @param Application $application
     * @param Command[]   $commands
     *
     * @return array
     */
    private function sortCommands(Application $application, array $commands)
    {
        $method = new \ReflectionMethod($application, 'sortCommands');
        $method->setAccessible(true);

        return $method->invoke($application, $commands);
    }
}
