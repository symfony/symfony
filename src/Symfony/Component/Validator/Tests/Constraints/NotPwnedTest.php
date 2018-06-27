<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\NotPwned;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class NotPwnedTest extends TestCase
{
    public function testDefaultValues()
    {
        $constraint = new NotPwned();
        $this->assertSame(1, $constraint->threshold);
        $this->assertFalse($constraint->skipOnError);
    }
}
