<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ErrorEvent extends RequestEvent
{
    private $error;
    private $allowCustomResponseCode = false;

    public function __construct(HttpKernelInterface $kernel, Request $request, int $requestType, \Error $error)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->error = $error;
    }

    public function getError(): \Error
    {
        return $this->error;
    }

    public function allowCustomResponseCode(): void
    {
        $this->allowCustomResponseCode = true;
    }

    public function isAllowingCustomResponseCode(): bool
    {
        return $this->allowCustomResponseCode;
    }
}
