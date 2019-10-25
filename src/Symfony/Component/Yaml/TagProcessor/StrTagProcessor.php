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

use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * @author Saif Eddin Gmati <azjezz@protonmail.com>
 */
class StrTagProcessor implements TagProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function getTag(): string
    {
        return '!!str';
    }

    /**
     * {@inheritdoc}
     */
    public function process(TaggedValue $data): string
    {
        return (string) substr($data->getValue(), 6);
    }
}
