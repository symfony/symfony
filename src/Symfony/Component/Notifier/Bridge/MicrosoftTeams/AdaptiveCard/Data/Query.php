<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Data;

/**
 * @see https://adaptivecards.io/explorer/Data.Query.html
 */
final class Query
{
    private array $options;

    public function __construct(string $dataset)
    {
        $this->options = [
            'type' => 'Data.Query',
            'dataset' => $dataset,
        ];
    }

    /**
     * @return $this
     */
    public function count(int $count): static
    {
        $this->options['count'] = $count;

        return $this;
    }

    /**
     * @return $this
     */
    public function skip(int $skip): static
    {
        $this->options['skip'] = $skip;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
