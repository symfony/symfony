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

use Symfony\Component\OpenApi\Model\RequestBody;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class RequestBodyConfigurator
{
    use Traits\ContentTrait;
    use Traits\DescriptionTrait;
    use Traits\ExtensionsTrait;

    private ?bool $required = null;

    public function build(): RequestBody
    {
        return new RequestBody($this->content, $this->description, $this->required, $this->specificationExtensions);
    }

    public function required(bool $required): static
    {
        $this->required = $required;

        return $this;
    }
}
