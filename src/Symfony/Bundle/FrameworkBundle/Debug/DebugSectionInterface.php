<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug;

/**
 * Contributes a tab to the interactive "debug" command.
 *
 * Each section owns one area of the application (container, routing, config, …). It is
 * registered as a service tagged "debug.section" so that any bundle can plug its
 * own area into the unified command; sections whose underlying component is not
 * installed simply do not register, and the command degrades gracefully.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
interface DebugSectionInterface
{
    /**
     * The label shown in the top menu (e.g. "Container").
     */
    public function getLabel(): string;

    /**
     * The compact label shown when the top menu does not fit (e.g. "DI").
     */
    public function getShortLabel(): string;

    /**
     * The items shown in the left-hand list, filtered by the search query.
     *
     * An empty query must return the full, unfiltered set.
     *
     * @return list<DebugItem>
     */
    public function search(string $query): array;

    /**
     * Filters an already-built item list by the search query.
     *
     * Must return the same result as search($query) does when $items is the
     * full set returned by search(''). It runs in the UI process on cached
     * items on every keystroke, so it must be fast and must not rebuild any
     * index.
     *
     * @param list<DebugItem> $items
     *
     * @return list<DebugItem>
     */
    public function filter(array $items, string $query): array;

    /**
     * The detail of the given item, rendered in the right-hand pane.
     *
     * The returned string may contain ANSI escape sequences (it is rendered
     * verbatim), allowing sections to reuse the output of the existing
     * "debug:*" descriptors.
     *
     * @param int $width The number of columns available in the detail pane
     */
    public function describe(DebugItem $item, int $width): string;
}
