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

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * ContainerInterface is the interface implemented by service container classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @api
 */
interface ContainerInterface
{
    const EXCEPTION_ON_INVALID_REFERENCE = 1;
    const NULL_ON_INVALID_REFERENCE = 2;
    const IGNORE_ON_INVALID_REFERENCE = 3;
    const SCOPE_CONTAINER = 'container';
    const SCOPE_PROTOTYPE = 'prototype';

    /**
     * Sets a service.
     *
     * Note: The $scope parameter is deprecated since version 2.8 and will be removed in 3.0.
     *
     * @param string $id      The service identifier
     * @param object $service The service instance
     * @param string $scope   The scope of the service
     *
     * @api
     */
    public function set($id, $service, $scope = self::SCOPE_CONTAINER);

    /**
     * Gets a service.
     *
     * @param string $id              The service identifier
     * @param int    $invalidBehavior The behavior when the service does not exist
     *
     * @return object The associated service
     *
     * @throws ServiceCircularReferenceException When a circular reference is detected
     * @throws ServiceNotFoundException          When the service is not defined
     *
     * @see Reference
     *
     * @api
     */
    public function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE);

    /**
     * Returns true if the given service is defined.
     *
     * @param string $id The service identifier
     *
     * @return bool true if the service is defined, false otherwise
     *
     * @api
     */
    public function has($id);

    /**
     * Check for whether or not a service has been initialized.
     *
     * @param string $id
     *
     * @return bool true if the service has been initialized, false otherwise
     */
    public function initialized($id);

    /**
     * Gets a parameter.
     *
     * @param string $name The parameter name
     *
     * @return mixed The parameter value
     *
     * @throws InvalidArgumentException if the parameter is not defined
     *
     * @api
     */
    public function getParameter($name);

    /**
     * Checks if a parameter exists.
     *
     * @param string $name The parameter name
     *
     * @return bool The presence of parameter in container
     *
     * @api
     */
    public function hasParameter($name);

    /**
     * Sets a parameter.
     *
     * @param string $name  The parameter name
     * @param mixed  $value The parameter value
     *
     * @api
     */
    public function setParameter($name, $value);

    /**
     * Enters the given scope.
     *
     * @param string $name
     *
     * @api
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    public function enterScope($name);

    /**
     * Leaves the current scope, and re-enters the parent scope.
     *
     * @param string $name
     *
     * @api
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    public function leaveScope($name);

    /**
     * Adds a scope to the container.
     *
     * @param ScopeInterface $scope
     *
     * @api
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    public function addScope(ScopeInterface $scope);

    /**
     * Whether this container has the given scope.
     *
     * @param string $name
     *
     * @return bool
     *
     * @api
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    public function hasScope($name);

    /**
     * Determines whether the given scope is currently active.
     *
     * It does however not check if the scope actually exists.
     *
     * @param string $name
     *
     * @return bool
     *
     * @api
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    public function isScopeActive($name);
}
