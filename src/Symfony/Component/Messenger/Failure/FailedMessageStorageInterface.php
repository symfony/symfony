<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Failure;

use Symfony\Component\Messenger\Envelope;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
interface FailedMessageStorageInterface
{
    public function add(Envelope $envelope, \Throwable $exception, string $transportName, \DateTimeInterface $failedAt): FailedMessage;

    /**
     * @return FailedMessage[]
     */
    public function all(): array;

    public function get($id): FailedMessage;

    public function remove(FailedMessage $failedMessage): void;

    public function removeAll(): void;
}
