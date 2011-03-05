<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Event;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\Request;

class ViewEventArgs extends RequestEventArgs
{
    private $controllerResult;

    public function __construct(KernelInterface $kernel, $controllerResult, Request $request, $requestType)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->controllerResult = $controllerResult;
    }

    public function getControllerResult()
    {
        return $this->controllerResult;
    }
}