<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * This exception is thrown when a circular reference is detected between services in arguments.
 *
 * @author Nicolas LEFEVRE <weblefevre@gmail.com>
 */
class ArgumentCircularReferenceException extends RuntimeException
{
    private $argument;
    private $serviceId;

    public function __construct($argument, $serviceId, \Exception $previous = null)
    {
        parent::__construct(sprintf('Circular reference detected between service "%s" and parent argument "%s". A "ChildDefinition" can\'t references itself as parent.', $argument, $serviceId), 0, $previous);

        $this->argument = $argument;
        $this->serviceId = $serviceId;
    }

    public function getArgument()
    {
        return $this->argument;
    }

    public function getServiceId()
    {
        return $this->serviceId;
    }
}
