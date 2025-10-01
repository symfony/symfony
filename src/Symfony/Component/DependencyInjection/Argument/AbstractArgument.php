<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

/**
 * Represents an abstract service argument, which have to be set by a compiler pass or a DI extension.
 */
final class AbstractArgument
{
    use ArgumentTrait;

    private string $text;
    private string $context = '';

    public function __construct(string $text = '')
    {
        $this->text = trim($text, '. ');
    }

    public function setContext(string $context): void
    {
        $this->context = $context.' is abstract'.('' === $this->text ? '' : ': ');
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getTextWithContext(): string
    {
        return $this->context.$this->text.'.';
    }
}
