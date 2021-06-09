<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Transport;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 */
class DoctrineReceivedStamp implements NonSendableStampInterface
{
    private $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }
}

if (!class_exists(\Symfony\Component\Messenger\Transport\Doctrine\DoctrineReceivedStamp::class, false)) {
    class_alias(DoctrineReceivedStamp::class, \Symfony\Component\Messenger\Transport\Doctrine\DoctrineReceivedStamp::class);
}
