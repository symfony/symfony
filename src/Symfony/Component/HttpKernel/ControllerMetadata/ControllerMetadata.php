<?php

namespace Symfony\Component\HttpKernel\ControllerMetadata;

use Symfony\Component\HttpKernel\ControllerMetadata\Configuration\ConfigurationInterface;

/**
 * Responsible for storing metadata of a controller action.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
class ControllerMetadata implements \Serializable
{
    private $className;
    private $method;
    private $arguments;
    private $configurations;

    /**
     * @param string                   $className
     * @param string                   $method
     * @param ArgumentMetadata[]       $arguments
     * @param ConfigurationInterface[] $configurations
     */
    public function __construct($className, $method, array $arguments, array $configurations)
    {
        $this->className = $className;
        $this->method = $method;
        $this->arguments = $arguments;
        $this->configurations = $configurations;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return ArgumentMetadata[]
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @return ConfigurationInterface[]
     */
    public function getConfigurations()
    {
        return $this->configurations;
    }

    /**
     * Returns all files related to this controller.
     *
     * @return string[]
     */
    public function getTrackedFiles()
    {
        $tracked = array((new \ReflectionClass($this->className))->getFileName());

        foreach ($this->configurations as $configuration) {
            foreach ($configuration->getTrackedFiles() as $trackedFile) {
                $tracked[] = $trackedFile;
            }
        }

        return $tracked;
    }

    /**
     * @param string $className
     *
     * @return ConfigurationInterface[]
     */
    public function getConfigurationsByClass($className)
    {
        $found = array();

        foreach ($this->configurations as $configuration) {
            if (get_class($configuration) === $className || is_subclass_of($className, $configuration)) {
                $found[] = $configuration;
            }
        }

        return $found;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(array($this->className, $this->method, $this->arguments, $this->configurations));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->className, $this->method, $this->arguments, $this->configurations) = unserialize($serialized);
    }
}
