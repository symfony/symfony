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

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MessengerBag implements BagInterface
{
    private $messages;

    public function __construct(array $beforeMessages = [], array $afterMessages = [], array $failureMessages = [])
    {
        $this->messages['before'] = $beforeMessages;
        $this->messages['after'] = $afterMessages;
        $this->messages['failure'] = $failureMessages;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): array
    {
        return $this->messages;
    }

    public function getName(): string
    {
        return 'messenger';
    }
}
