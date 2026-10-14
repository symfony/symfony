<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

class Ticket
{
    public string $title = '';
    public ?string $note;
    private string $secret = '';

    public function __construct(
        public readonly int $id,
    ) {
    }

    public function getStatus(): string
    {
        return 'open';
    }

    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }
}
