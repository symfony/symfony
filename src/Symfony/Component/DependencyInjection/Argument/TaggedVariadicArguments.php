<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class TaggedVariadicArguments implements ArgumentInterface
{
    use ReferenceSetArgumentTrait;

    private $tag;

    public function __construct(string $tag)
    {
        $this->setValues([]);

        $this->tag = $tag;
    }

    public function getTag()
    {
        return $this->tag;
    }
}
