<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Fixtures;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

final class TestOptions implements MessageOptionsInterface
{
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getRecipientId(): ?string
    {
        return $this->options['recipient_id'];
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
