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

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * ContainerInterface is the interface implemented by service container classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface ContainerInterface extends PsrContainerInterface
{
    public const RUNTIME_EXCEPTION_ON_INVALID_REFERENCE = 0;
    public const EXCEPTION_ON_INVALID_REFERENCE = 1;
    public const NULL_ON_INVALID_REFERENCE = 2;
    public const IGNORE_ON_INVALID_REFERENCE = 3;
    public const IGNORE_ON_UNINITIALIZED_REFERENCE = 4;

    /**
     * @return void
     */
    public function set(string $id, ?object $service);

    /**
     * @template B of self::*_REFERENCE
     *
     * @param B $invalidBehavior
     *
     * @psalm-return (B is self::EXCEPTION_ON_INVALID_REFERENCE|self::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE ? object : object|null)
     *
     * @throws ServiceCircularReferenceException When a circular reference is detected
     * @throws ServiceNotFoundException          When the service is not defined
     *
     * @see Reference
     */
    public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object;

    public function has(string $id): bool;

    /**
     * Check for whether or not a service has been initialized.
     */
    public function initialized(string $id): bool;

    /**
     * @return array|bool|string|int|float|\UnitEnum|null
     *
     * @throws ParameterNotFoundException if the parameter is not defined
     */
    public function getParameter(string $name);

    public function hasParameter(string $name): bool;

    /**
     * @return void
     */
    public function setParameter(string $name, array|bool|string|int|float|\UnitEnum|null $value);
}
