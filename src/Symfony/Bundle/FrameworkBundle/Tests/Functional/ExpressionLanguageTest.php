<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\TestWith;

class ExpressionLanguageTest extends AbstractWebTestCase
{
    #[TestWith(['controller.expression_language'])]
    #[TestWith(['validator.expression_language'])]
    public function testConstantFunctionAllowsAnyConstant(string $id)
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);

        $this->assertSame(\PHP_VERSION, static::getContainer()->get($id)->evaluate('constant("PHP_VERSION")'));
    }
}
