<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures\WithAccessors;

use Symfony\Component\PropertyInfo\Attribute\WithAccessors;

class Bar extends Foo
{
    #[WithAccessors(getter: 'currentPriority', setter: 'changePriority')]
    private int $priority;
    #[WithAccessors(getter: 'allTags', setter: 'replaceTags', adder: 'attachTag', remover: 'detachTag')]
    private array $tags;
    #[WithAccessors(getter: 'readMailbox', setter: 'replaceMailbox', adder: 'attachLetter', remover: 'detachLetter')]
    private array $mailbox;
    #[WithAccessors(getter: 'isEnabled')]
    private int $active;

    public function currentPriority(): int
    {
    }

    public function changePriority(int $priority): void
    {
    }

    public function allTags(): array
    {
    }

    public function replaceTags(array $tags): void
    {
    }

    public function attachTag(int $tag): void
    {
    }

    public function detachTag(): void
    {
    }

    public function readMailbox(): array
    {
    }

    public function replaceMailbox(array $mailbox): void
    {
    }

    public function attachLetter(int $letter): void
    {
    }

    public function detachLetter(): void
    {
    }

    public function isEnabled(): bool
    {
    }
}
