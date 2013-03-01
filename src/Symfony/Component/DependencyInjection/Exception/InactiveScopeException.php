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
 * This exception is thrown when you try to create a service of an inactive scope.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class InactiveScopeException extends RuntimeException
{
    private $serviceId;
    private $scope;

    public function __construct($serviceId, $scope)
    {
        parent::__construct(sprintf('You cannot create a service ("%s") of an inactive scope ("%s").', $serviceId, $scope));

        $this->serviceId = $serviceId;
        $this->scope = $scope;
    }

    public function getServiceId()
    {
        return $this->serviceId;
    }

    public function getScope()
    {
        return $this->scope;
    }
}
