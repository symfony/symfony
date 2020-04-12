<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Form\Extension\Validator\Util\ServerParams;

class LegacyServerParamsTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testClassIsDeprecated()
    {
        $this->expectDeprecation('Since symfony/form 5.1: The "Symfony\Component\Form\Extension\Validator\Util\ServerParams" class is deprecated. Use "Symfony\Component\Form\Util\ServerParams" instead.');

        new ServerParams();
    }
}
