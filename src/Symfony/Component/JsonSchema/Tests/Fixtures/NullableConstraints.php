<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

class NullableConstraints
{
    #[Assert\NotBlank]
    public ?string $notBlank = null;

    #[Assert\NotNull]
    public ?string $notNull = null;

    #[Assert\NotBlank(allowNull: true)]
    public ?string $notBlankAllowNull = null;

    /** @var list<string>|null */
    #[Assert\NotBlank]
    public ?array $notBlankArray = null;
}
