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

use Symfony\Component\Tui\Widget\TabsWidget;

/**
 * Event dispatched when the active tab changes in TabsWidget.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TabChangeEvent extends AbstractEvent
{
    public function __construct(
        TabsWidget $target,
        private readonly int $previousIndex,
        private readonly int $index,
        private readonly string $previousId,
        private readonly string $id,
    ) {
        parent::__construct($target);
    }

    public function getPreviousIndex(): int
    {
        return $this->previousIndex;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getPreviousId(): string
    {
        return $this->previousId;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
