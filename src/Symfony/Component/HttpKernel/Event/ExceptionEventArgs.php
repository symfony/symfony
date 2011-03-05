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

class ExceptionEventArgs extends RequestEventArgs
{
    private $exception;

    private $handled = false;

    public function __construct(KernelInterface $kernel, \Exception $e, Request $request, $requestType)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->exception = $e;
    }

    public function getException()
    {
        return $this->exception;
    }

    public function setException(\Exception $exception)
    {
        $this->exception = $exception;
    }

    public function setHandled($handled)
    {
        $this->handled = $handled;

        $this->stopPropagation();
    }

    public function isHandled()
    {
        return $this->handled;
    }
}