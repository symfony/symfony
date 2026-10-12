<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures\ConstructorMapping;

use Symfony\Component\Validator\Constraints\NotBlank;

final readonly class ReadonlySignUp
{
    public function __construct(
        #[NotBlank]
        public string $email,
        public \DateTimeImmutable $birthDate,
    ) {
    }
}
