<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TaskInterface
{
    public const ENABLED = 'enabled';
    public const PAUSED = 'paused';
    public const DISABLED = 'disabled';
    public const UNSPECIFIED = 'disabled';

    public function getName(): string;

    public function getCommand(): ?string;

    /**
     * @return \DateTimeInterface|string
     */
    public function getExpression();

    public function getOptions(): array;

    /**
     * @return callable|mixed|string
     */
    public function get(string $key, $default = null);

    public function addBag(string $name, string $value): void;

    public function getBag(string $name): ?string;

    public function set(string $key, $value = null): void;

    public function setMultiples(array $options = []): void;

    public function getFormattedInformations(): array;
}
