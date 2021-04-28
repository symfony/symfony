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

/**
 * Represents an event containing a throwable.
 *
 * @author Lukasz Goworko <info@lukaszgoworko.de>
 */
interface ExceptionEventInterface
{
    public function getThrowable(): \Throwable;

    public function setThrowable(\Throwable $exception): void;

    public function allowCustomResponseCode(): void;

    public function isAllowingCustomResponseCode(): bool;
}
