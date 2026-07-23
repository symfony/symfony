<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

use Symfony\Component\Tui\Exception\LogicException;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/**
 * Represents a single item in a SettingsListWidget.
 *
 * The `$label` and `$description` constructor arguments are stored verbatim
 * and rendered as-is. Only `$currentValue` is sanitized. See {@see TextWidget}
 * for the raw-passthrough contract: sanitize untrusted label/description
 * upstream via {@see StringUtils::stripControlBytes()}.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @phpstan-type SubmenuFactory callable(string, callable(?string): void): (FocusableInterface&AbstractWidget)
 */
final class SettingItem
{
    private string $currentValue;

    /**
     * @param list<string>        $values  Predefined values for cycling (empty = no cycling)
     * @param SubmenuFactory|null $submenu Factory for submenu widget
     */
    public function __construct(
        private readonly string $id,
        private readonly string $label,
        string $currentValue,
        private readonly ?string $description = null,
        /** @var list<string> */
        private readonly array $values = [],
        /** @var SubmenuFactory|null */
        private $submenu = null,
    ) {
        $this->currentValue = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($currentValue));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return list<string>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function getCurrentValue(): string
    {
        return $this->currentValue;
    }

    public function setCurrentValue(string $value): void
    {
        $this->currentValue = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($value));
    }

    public function hasValues(): bool
    {
        return (bool) $this->values;
    }

    public function hasSubmenu(): bool
    {
        return null !== $this->submenu;
    }

    /**
     * @return SubmenuFactory
     */
    public function getSubmenu(): callable
    {
        return $this->submenu ?? throw new LogicException('This setting item does not have a submenu.');
    }
}
