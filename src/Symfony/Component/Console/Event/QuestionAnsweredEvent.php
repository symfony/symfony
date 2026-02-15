<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when constraint validation is needed for a question.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class QuestionAnsweredEvent extends Event
{
    private array $violations = [];

    public function __construct(
        public readonly mixed $value,
        public readonly array $constraints,
    ) {
    }

    public function addViolation(string $message): void
    {
        $this->violations[] = $message;
    }

    public function getViolations(): array
    {
        return $this->violations;
    }

    public function hasViolations(): bool
    {
        return (bool) $this->violations;
    }
}
