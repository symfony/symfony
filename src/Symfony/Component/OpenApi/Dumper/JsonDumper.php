<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Dumper;

use Symfony\Component\OpenApi\Model\OpenApi;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class JsonDumper implements DumperInterface
{
    public function __construct(private int $flags = \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)
    {
    }

    public function dump(OpenApi $compiledDoc): string
    {
        $json = json_encode($compiledDoc->toArray(), $this->flags);

        // Marker to allow no security
        return str_replace('"__NO_SECURITY": []', '', $json);
    }
}
