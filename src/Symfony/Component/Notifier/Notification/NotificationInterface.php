<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Notification;

use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * @author Tim Werdin <t.werdin86@gmail.com>
 */
interface NotificationInterface
{
    public const IMPORTANCE_URGENT = 'urgent';
    public const IMPORTANCE_HIGH = 'high';
    public const IMPORTANCE_MEDIUM = 'medium';
    public const IMPORTANCE_LOW = 'low';

    /**
     * @return $this
     */
    public function setSubject(string $subject);

    public function getSubject(): string;

    /**
     * @return $this
     */
    public function setContent(string $content);

    public function getContent(): string;

    /**
     * @return $this
     */
    public function setImportance(string $importance);

    public function getImportance(): string;

    /**
     * @return $this
     */
    public function setChannels(array $channels);

    public function getChannels(RecipientInterface $recipient): array;
}
