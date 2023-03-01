<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Model;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class SecurityRequirement implements OpenApiModel
{
    use OpenApiTrait;

    public const NONE = '__NO_SECURITY';

    /**
     * @param string[] $config
     */
    public function __construct(
        private readonly string $name,
        private readonly array $config = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function toArray(): array
    {
        return [
            $this->getName() => array_filter([
                $this->normalizeCollection($this->getConfig()),
            ]),
        ];
    }
}
