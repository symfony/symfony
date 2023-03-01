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

use Symfony\Component\OpenApi\Model\Reference;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class ReferenceConfigurator
{
    use Traits\DescriptionTrait;
    use Traits\ExtensionsTrait;
    use Traits\SummaryTrait;

    public function __construct(private readonly string $ref)
    {
    }

    public static function normalize(?string $ref): ?string
    {
        if (!$ref) {
            throw new \InvalidArgumentException('Missing reference name passed to '.__CLASS__.'::'.__METHOD__.'()');
        }

        return preg_replace('/[^a-zA-Z0-9\.\-\_]+/', '_', $ref);
    }

    public function build(): Reference
    {
        return new Reference(
            $this->ref,
            $this->summary,
            $this->description,
            $this->specificationExtensions,
        );
    }
}
