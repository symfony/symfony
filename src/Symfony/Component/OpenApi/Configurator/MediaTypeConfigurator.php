<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Configurator;

use Symfony\Component\OpenApi\Model\Encoding;
use Symfony\Component\OpenApi\Model\MediaType;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class MediaTypeConfigurator
{
    use Traits\ExamplesTrait;
    use Traits\ExtensionsTrait;
    use Traits\SchemaTrait;

    /**
     * @var array<string, Encoding>
     */
    private array $encodings = [];

    public function build(): MediaType
    {
        return new MediaType(
            $this->schema,
            $this->example,
            $this->examples ?: null,
            $this->encodings ?: null,
            $this->specificationExtensions,
        );
    }

    public function encoding(string $name, EncodingConfigurator $encoding): static
    {
        $this->encodings[$name] = $encoding->build();

        return $this;
    }
}
