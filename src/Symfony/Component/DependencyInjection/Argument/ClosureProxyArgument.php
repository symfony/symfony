<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ClosureProxyArgument implements ArgumentInterface
{
    private $reference;
    private $method;

    public function __construct($id, $method, $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)
    {
        $this->reference = new Reference($id, $invalidBehavior);
        $this->method = $method;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        return array($this->reference, $this->method);
    }

    /**
     * {@inheritdoc}
     */
    public function setValues(array $values)
    {
        list($this->reference, $this->method) = $values;
    }
}
