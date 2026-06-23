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
 * A single entry shown in the left-hand list of the interactive "debug" command.
 *
 * Kept intentionally minimal: the {@see $type} discriminates how the item is
 * described in the detail pane (and doubles as the facet it belongs to), while
 * {@see $value} is the real identifier passed back to the owning section.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
final class DebugItem
{
    /**
     * @param string      $type       An opaque, section-defined kind (e.g. "service", "parameter", "tag")
     * @param string      $value      The real id/name used by the section to describe the item
     * @param string      $label      The text rendered in the list (sanitized before display)
     * @param string|null $group      An optional heading the item belongs to; consecutive items sharing
     *                                the same group are rendered under a single header in the list. When
     *                                null, the item is listed without a header.
     * @param string|null $detail     optional precomputed detail, used when building the detail would
     *                                otherwise redo expensive work already done for the list
     * @param string|null $searchText Optional extra text the item is matched against, in addition to
     *                                its label and value (e.g. a route's path and controller). When
     *                                null, only the label and value are matched.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $value,
        public readonly string $label,
        public readonly ?string $group = null,
        public readonly ?string $detail = null,
        public readonly ?string $searchText = null,
    ) {
    }

    /**
     * Whether the item matches the given case-insensitive search query.
     */
    public function matches(string $query): bool
    {
        return '' === $query
            || false !== stripos($this->label, $query)
            || false !== stripos($this->value, $query)
            || (null !== $this->searchText && false !== stripos($this->searchText, $query));
    }
}
