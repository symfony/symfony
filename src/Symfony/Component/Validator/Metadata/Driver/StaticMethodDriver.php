<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Metadata\Driver;

use Metadata\Driver\DriverInterface;
use Symfony\Component\Validator\Metadata\ClassMetadata;
use Symfony\Component\Validator\Exception\MappingException;

/**
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Henrik Bjornskov <henrik@bjrnskov.dk>
 */
class StaticMethodDriver implements DriverInterface
{
    /**
     * @var string $methodName
     */
    protected $methodName;

    /**
     * @param string $methodName
     */
    public function __construct($methodName = 'loadValidatorMetadata')
    {
        $this->methodName = $methodName;
    }

    /**
     * Looks for a static method called $this->methodName and if found
     * it will be invoked with an instance of ClassMetadata
     *
     * @param \ReflectionClass $class
     * @return ClassMetadata
     */
    public function loadMetadataForClass(\ReflectionClass $class)
    {
        if ($class->hasMethod($this->methodName)) {
            $metadata = new ClassMetadata($class->getName());
            $method = $class->getMethod($this->methodName);

            if (!$method->isStatic()) {
                throw new MappingException(sprintf('The method %s::%s should be static', $class->getName(), $this->methodName));
            }

            $method->invoke(null, $metadata);

            return $metadata;
        }
    }
}
