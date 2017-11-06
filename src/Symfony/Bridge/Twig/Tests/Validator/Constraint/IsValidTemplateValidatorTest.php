<?php
/**
 * This file is part of the symfony package.
 *
 *     __  ___       __    ______            ____        __
 *    / / / (_)___ _/ /_  / ____/___        / __ \____ _/ /_____ _
 *   / /_/ / / __ `/ __ \/ /   / __ \______/ / / / __ `/ __/ __ `/
 *  / __  / / /_/ / / / / /___/ /_/ /_____/ /_/ / /_/ / /_/ /_/ /
 * /_/ /_/_/\__, /_/ /_/\____/\____/     /_____/\__,_/\__/\__,_/
 *         /____/
 *
 * (c) HighCo-Data <it-build@highco-data.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Created at 06/11/2017 11:54
 */

namespace Symfony\Bridge\Twig\Tests\Validator\Constraint;

use Symfony\Bridge\Twig\Validator\Constraint\IsValidTemplate;
use Symfony\Bridge\Twig\Validator\Constraint\IsValidTemplateValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Twig\Error\Error;
use Twig\Loader\LoaderInterface;
use Twig\Node\Node;
use Twig\TokenStream;

/**
 * Class Symfony\Bridge\Twig\Tests\Validator\Constraint\IsValidTemplateValidatorTest
 *
 * @author Gary PEGEOT <g.pegeot@highco-data.fr>
 */
class IsValidTemplateValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * @var \Twig\Environment|\PHPUnit_Framework_MockObject_MockObject
     */
    private $environment;


    /**
     * Test null value does not trigger error.
     */
    public function testNullIsValid()
    {
        $this->validator->validate(null, new IsValidTemplate());

        $this->assertNoViolation();
    }

    /**
     * Test blank value does not trigger error.
     */
    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new IsValidTemplate());

        $this->assertNoViolation();
    }

    /**
     * Test validation that does not throw exception.
     */
    public function testValidTemplate()
    {
        $this->environment->method('parse')->willReturn($this->createMock(Node::class));
        $this->validator->validate('{{ foo }}', new IsValidTemplate());

        $this->assertNoViolation();
    }

    /**
     * Test validation that does not throw exception.
     */
    public function testInvalidTemplate()
    {
        $error = new Error('Foo message');
        $error->setTemplateLine(42);

        $constraint = new IsValidTemplate();

        $this->environment->method('parse')->willThrowException($error);
        $this->validator->validate('{{ foo }', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameters([
                '{{ line }}' => 42,
                '{{ error }}' => 'Foo message at line 42',
            ])
            ->assertRaised();
    }

    /**
     * @return IsValidTemplateValidator
     */
    protected function createValidator()
    {
        $this->environment = $this->createMock('Twig\Environment');
        $this->environment->method('tokenize')->willReturn(new TokenStream([]));
        $this->environment->method('getLoader')->willReturn($this->createMock(LoaderInterface::class));

        return new IsValidTemplateValidator($this->environment);
    }
}
