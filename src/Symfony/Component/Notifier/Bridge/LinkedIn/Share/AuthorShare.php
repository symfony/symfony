<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LinkedIn\Share;

/**
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 */
final class AuthorShare extends AbstractLinkedInShare
{
    public const PERSON = 'person';

    private string $author;

    public function __construct(string $value, string $organisation = self::PERSON)
    {
        $this->author = "urn:li:$organisation:$value";
    }

    public function author(): string
    {
        return $this->author;
    }
}
