<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * Represents a PHP type-hinted service reference.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TypedReference extends Reference
{
    private $type;
    private $requiringClass;

    /**
     * @param string $id              The service identifier
     * @param string $type            The PHP type of the identified service
     * @param int    $invalidBehavior The behavior when the service does not exist
     */
    public function __construct(string $id, string $type, $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)
    {
        if (\is_string($invalidBehavior) || 3 < \func_num_args()) {
            @trigger_error(sprintf('The $requiringClass argument of "%s" is deprecated since Symfony 4.1.', __METHOD__), E_USER_DEPRECATED);

            $this->requiringClass = $invalidBehavior;
            $invalidBehavior = 3 < \func_num_args() ? \func_get_arg(3) : ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
        }
        parent::__construct($id, $invalidBehavior);
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * @deprecated since Symfony 4.1
     */
    public function getRequiringClass()
    {
        @trigger_error(sprintf('The "%s" method is deprecated since Symfony 4.1.', __METHOD__), E_USER_DEPRECATED);

        return $this->requiringClass ?? '';
    }

    /**
     * @deprecated since Symfony 4.1
     */
    public function canBeAutoregistered()
    {
        @trigger_error(sprintf('The "%s" method is deprecated since Symfony 4.1.', __METHOD__), E_USER_DEPRECATED);

        return $this->requiringClass && (false !== $i = strpos($this->type, '\\')) && 0 === strncasecmp($this->type, $this->requiringClass, 1 + $i);
    }
}
