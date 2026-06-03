<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Header;

use Symfony\Component\Mime\Header\UnstructuredHeader;

/**
 * @author Kieran Cross
 */
final class SuppressionGroupHeader extends UnstructuredHeader
{
    /**
     * @param int[] $groupsToDisplay
     */
    public function __construct(
        private int $groupId,
        private array $groupsToDisplay = [],
    ) {
        parent::__construct('X-Sendgrid-SuppressionGroup', json_encode([
            'group_id' => $groupId,
            'groups_to_display' => $this->groupsToDisplay,
        ], \JSON_THROW_ON_ERROR));
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }

    /**
     * @return int[]
     */
    public function getGroupsToDisplay(): array
    {
        return $this->groupsToDisplay;
    }
}
