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
use Symfony\Component\Yaml\Yaml;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class YamlDumper implements DumperInterface
{
    public function __construct(
        private int $inline = 10,
        private int $indent = 4,
        private int $flags = Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE,
    ) {
    }

    public function dump(OpenApi $compiledDoc): string
    {
        $yaml = Yaml::dump($compiledDoc->toArray(), $this->inline, $this->indent, $this->flags);

        // Marker to allow no security
        return str_replace('__NO_SECURITY: []', '{}', $yaml);
    }
}
