<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard;

/**
 * @see https://adaptivecards.io/explorer/TableCell.html
 */
final class TableCell
{
    use ContainerTrait;

    private array $options = [
        'type' => 'TableCell',
        'items' => [],
    ];

    public function toArray(): array
    {
        return $this->options;
    }
}
