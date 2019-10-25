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
 * Process TaggedValue and convert it a php value.
 *
 * @author Saif Eddin Gmati <azjezz@protonmail.com>
 */
interface TagProcessorInterface
{
    /**
     * Returns the tag name.
     */
    public function getTag(): string;

    /**
     * Process The Tagged value and return the PHP value.
     */
    public function process(TaggedValue $data);
}
