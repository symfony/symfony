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

use Symfony\Component\Mercure\Update;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MercureBag implements BagInterface
{
    /**
     * @var array<string,Update>
     */
    private $updates;

    /**
     * @param Update[] $beforeUpdates
     * @param Update[] $afterUpdates
     * @param Update[] $failureUpdates
     */
    public function __construct(array $beforeUpdates = [], array $afterUpdates = [], array $failureUpdates = [])
    {
        $this->updates['before'] = $beforeUpdates;
        $this->updates['after'] = $afterUpdates;
        $this->updates['failure'] = $failureUpdates;
    }

    /**
     * @return array<string,Update>
     */
    public function getContent(): array
    {
        return $this->updates;
    }

    public function getName(): string
    {
        return 'mercure';
    }
}
