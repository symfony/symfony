<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\TagProcessor;

use Symfony\Component\Yaml\Inline;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * @author Saif Eddin Gmati <azjezz@protonmail.com>
 */
class BinaryTagProcessor implements TagProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function getTag(): string
    {
        return '!!binary';
    }

    /**
     * {@inheritdoc}
     */
    public function process(TaggedValue $data): string
    {
        return Inline::evaluateBinaryScalar($data->getValue());
    }
}
