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

/**
 * Represents a single tab entry for TabsWidget.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TabItem
{
    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly AbstractWidget $content,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getContent(): AbstractWidget
    {
        return $this->content;
    }
}
