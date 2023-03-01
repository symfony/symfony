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

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class EncodingConfigurator
{
    use Traits\ExtensionsTrait;
    use Traits\HeadersTrait;

    private ?string $contentType = null;
    private ?string $style = null;
    private ?bool $explode = null;
    private ?bool $allowReserved = null;

    public function build(): ?Encoding
    {
        return new Encoding(
            $this->contentType,
            $this->headers,
            $this->style,
            $this->explode,
            $this->allowReserved,
            $this->specificationExtensions,
        );
    }

    public function contentType(string $contentType): static
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function style(string $style): static
    {
        $this->style = $style;

        return $this;
    }

    public function explode(bool $explode): static
    {
        $this->explode = $explode;

        return $this;
    }

    public function allowReserved(bool $allowReserved): static
    {
        $this->allowReserved = $allowReserved;

        return $this;
    }
}
