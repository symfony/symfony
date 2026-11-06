<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures\Generic;

/**
 * @psalm-template B
 * @template A
 * @phpstan-template C
 * @phpstan-template B
 */
class MixedTemplates
{
    /** @var B */
    public mixed $first;
    /** @var A */
    public mixed $second;
    /** @var C */
    public mixed $third;
}
