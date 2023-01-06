<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

/**
 * Notice we don't have getter/setter for emails
 * because we count on adder/remover.
 */
class TestSingularAndPluralProps
{
    /** @var string|null */
    private $email;

    /** @var array */
    private $emails = [];

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email)
    {
        $this->email = $email;
    }

    public function getEmails(): array
    {
        return $this->emails;
    }

    public function addEmail(string $email)
    {
        $this->emails[] = $email;
    }

    public function removeEmail(string $email)
    {
        $this->emails = array_diff($this->emails, [$email]);
    }
}
