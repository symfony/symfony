<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Configurator\Traits;

use Symfony\Component\OpenApi\Configurator\MediaTypeConfigurator;
use Symfony\Component\OpenApi\Configurator\ReferenceConfigurator;
use Symfony\Component\OpenApi\Configurator\SchemaConfigurator;
use Symfony\Component\OpenApi\Model\MediaType;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
trait ContentTrait
{
    /**
     * @var array<string, MediaType>
     */
    private array $content = [];

    public function content(string $contentType, MediaTypeConfigurator|SchemaConfigurator|ReferenceConfigurator|string $mediaType): static
    {
        if (!$mediaType instanceof MediaTypeConfigurator) {
            $mediaType = (new MediaTypeConfigurator())->schema($mediaType);
        }

        $this->content[$contentType] = $mediaType->build();

        return $this;
    }
}
