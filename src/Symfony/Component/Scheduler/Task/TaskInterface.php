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

    public function getType(): string;

    /**
     * @param null $default
     *
     * @return callable|mixed|string
     */
    public function get(string $key, $default = null);

    /**
     * @param null $value
     */
    public function set(string $key, $value = null): void;

    /**
     * @param array $options
     */
    public function setMultiples(array $options = []): void;

    public function getFormattedInformations(): array;
}
