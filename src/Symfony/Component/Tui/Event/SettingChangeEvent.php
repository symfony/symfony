<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Event;

use Symfony\Component\Tui\Widget\SettingsListWidget;

/**
 * Event dispatched when a setting value changes in SettingsList.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SettingChangeEvent extends AbstractEvent
{
    public function __construct(
        SettingsListWidget $target,
        private readonly string $id,
        private readonly string $value,
    ) {
        parent::__construct($target);
    }

    /**
     * Get the setting identifier.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the new setting value.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Check if the value represents an enabled/truthy state.
     */
    public function isEnabled(): bool
    {
        return filter_var($this->value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) ?? false;
    }

    /**
     * Check if the value represents a disabled/falsy state.
     */
    public function isDisabled(): bool
    {
        return !(filter_var($this->value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) ?? true);
    }
}
