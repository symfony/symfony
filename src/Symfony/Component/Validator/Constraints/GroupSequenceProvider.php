<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Symfony\Component\Validator\Attribute\HasNamedArguments;

/**
 * Attribute to define a group sequence provider.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class GroupSequenceProvider
{
    #[HasNamedArguments]
    public function __construct(public ?string $provider = null)
    {
    }
}
