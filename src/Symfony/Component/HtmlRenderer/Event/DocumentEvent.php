<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Event;

final class DocumentEvent
{
    private bool $defaultPrevented = false;
    private bool $propagationStopped = false;

    public function __construct(public readonly string $type, public readonly \Dom\Element $target, public readonly array $data = [])
    {
    }

    public function preventDefault(): void
    {
        $this->defaultPrevented = true;
    }

    public function isDefaultPrevented(): bool
    {
        return $this->defaultPrevented;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
