<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bag;

use Symfony\Component\Mime\RawMessage;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MailerBag implements BagInterface
{
    /**
     * @var array<string,RawMessage>
     */
    private $mails;

    /**
     * @param RawMessage[] $beforeMails
     * @param RawMessage[] $afterMails
     * @param RawMessage[] $failureMails
     */
    public function __construct(array $beforeMails = [], array $afterMails = [], array $failureMails = [])
    {
        $this->mails['before'] = $beforeMails;
        $this->mails['after'] = $afterMails;
        $this->mails['failure'] = $failureMails;
    }

    /**
     * @return array<string,RawMessage>
     */
    public function getContent(): array
    {
        return $this->mails;
    }

    public function getName(): string
    {
        return 'mailer';
    }
}
