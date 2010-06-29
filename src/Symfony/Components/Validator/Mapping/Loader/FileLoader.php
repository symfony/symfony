<?php

namespace Symfony\Components\Validator\Mapping\Loader;

use Symfony\Components\Validator\Exception\MappingException;
use Symfony\Components\Validator\Mapping\ClassMetadata;
use Symfony\Components\Validator\Mapping\GroupMetadata;

abstract class FileLoader implements LoaderInterface
{
    protected $file;

    public function __construct($file)
    {
        if (!file_exists($file)) {
            throw new MappingException(sprintf('The mapping file %s does not exist', $file));
        }

        if (!is_readable($file)) {
            throw new MappingException(sprintf('The mapping file %s is not readable', $file));
        }

        $this->file = $file;
    }

    /**
     * Creates a new constraint instance for the given constraint name
     *
     * @param string $name    The constraint name. Either a constraint relative
     *                        to the default constraint namespace, or a fully
     *                        qualified class name
     * @param array $options  The constraint options
     *
     * @return Constraint
     */
    protected function newConstraint($name, $options)
    {
        if (strpos($name, '\\') !== false && class_exists($name)) {
            $className = (string)$name;
        } else {
            $className = 'Symfony\\Components\\Validator\\Constraints\\'.$name;
        }

        return new $className($options);
    }
}