<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Watcher\Resource;

/**
 * @author Pierre du Plessis <pdples@gmail.com>
 *
 * @internal
 */
final class ArrayResource implements ResourceInterface
{
    /**
     * @var ResourceInterface[]
     */
    private $resources;

    public function __construct(array $resources)
    {
        $this->resources = $resources;
    }

    public function detectChanges(): array
    {
        $events = [];

        foreach ($this->resources as $resource) {
            if ($changed = $resource->detectChanges()) {
                $events = array_merge($events, $changed);
            }
        }

        return $events;
    }
}
