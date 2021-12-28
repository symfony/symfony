<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Completion;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class CompletionInputTest extends TestCase
{
    /**
     * @dataProvider provideBindData
     */
    public function testBind(CompletionInput $input, string $expectedType, ?string $expectedName, string $expectedValue)
    {
        $definition = new InputDefinition([
            new InputOption('with-required-value', 'r', InputOption::VALUE_REQUIRED),
            new InputOption('with-optional-value', 'o', InputOption::VALUE_OPTIONAL),
            new InputOption('without-value', 'n', InputOption::VALUE_NONE),
            new InputArgument('required-arg', InputArgument::REQUIRED),
            new InputArgument('optional-arg', InputArgument::OPTIONAL),
        ]);

        $input->bind($definition);

        $this->assertEquals($expectedType, $input->getCompletionType(), 'Unexpected type');
        $this->assertEquals($expectedName, $input->getCompletionName(), 'Unexpected name');
        $this->assertEquals($expectedValue, $input->getCompletionValue(), 'Unexpected value');
    }

    public function provideBindData()
    {
        // option names
        yield 'optname-minimal-input' => [CompletionInput::fromTokens(['bin/console', '-'], 1), CompletionInput::TYPE_OPTION_NAME, null, '-'];
        yield 'optname-partial' => [CompletionInput::fromTokens(['bin/console', '--with'], 1), CompletionInput::TYPE_OPTION_NAME, null, '--with'];

        // option values
        yield 'optval-short' => [CompletionInput::fromTokens(['bin/console', '-r'], 1), CompletionInput::TYPE_OPTION_VALUE, 'with-required-value', ''];
        yield 'optval-short-partial' => [CompletionInput::fromTokens(['bin/console', '-rsymf'], 1), CompletionInput::TYPE_OPTION_VALUE, 'with-required-value', 'symf'];
        yield 'optval-short-space' => [CompletionInput::fromTokens(['bin/console', '-r'], 2), CompletionInput::TYPE_OPTION_VALUE, 'with-required-value', ''];
        yield 'optval-short-space-partial' => [CompletionInput::fromTokens(['bin/console', '-r', 'symf'], 2), CompletionInput::TYPE_OPTION_VALUE, 'with-required-value', 'symf'];
        yield 'optval-short-before-arg' => [CompletionInput::fromTokens(['bin/console', '-r', 'symfony'], 1), CompletionInput::TYPE_OPTION_VALUE, 'with-required-value', ''];
        yield 'optval-long' => [CompletionInput::fromTokens(['bin/console', '--with-required-value='], 1), CompletionInput::TYPE_OPTION_VALUE, 'with-required-value', ''];
        yield 'optval-long-partial' => [CompletionInput::fromTokens(['bin/console', '--with-required-value=symf'], 1), CompletionInput::TYPE_OPTION_VALUE, 'with-required-value', 'symf'];
        yield 'optval-long-space' => [CompletionInput::fromTokens(['bin/console', '--with-required-value'], 2), CompletionInput::TYPE_OPTION_VALUE, 'with-required-value', ''];
        yield 'optval-long-space-partial' => [CompletionInput::fromTokens(['bin/console', '--with-required-value', 'symf'], 2), CompletionInput::TYPE_OPTION_VALUE, 'with-required-value', 'symf'];

        yield 'optval-short-optional' => [CompletionInput::fromTokens(['bin/console', '-o'], 1), CompletionInput::TYPE_OPTION_VALUE, 'with-optional-value', ''];
        yield 'optval-short-space-optional' => [CompletionInput::fromTokens(['bin/console', '-o'], 2), CompletionInput::TYPE_OPTION_VALUE, 'with-optional-value', ''];
        yield 'optval-long-optional' => [CompletionInput::fromTokens(['bin/console', '--with-optional-value='], 1), CompletionInput::TYPE_OPTION_VALUE, 'with-optional-value', ''];
        yield 'optval-long-space-optional' => [CompletionInput::fromTokens(['bin/console', '--with-optional-value'], 2), CompletionInput::TYPE_OPTION_VALUE, 'with-optional-value', ''];

        // arguments
        yield 'arg-minimal-input' => [CompletionInput::fromTokens(['bin/console'], 1), CompletionInput::TYPE_ARGUMENT_VALUE, 'required-arg', ''];
        yield 'arg-optional' => [CompletionInput::fromTokens(['bin/console', 'symfony'], 2), CompletionInput::TYPE_ARGUMENT_VALUE, 'optional-arg', ''];
        yield 'arg-partial' => [CompletionInput::fromTokens(['bin/console', 'symf'], 1), CompletionInput::TYPE_ARGUMENT_VALUE, 'required-arg', 'symf'];
        yield 'arg-optional-partial' => [CompletionInput::fromTokens(['bin/console', 'symfony', 'sen'], 2), CompletionInput::TYPE_ARGUMENT_VALUE, 'optional-arg', 'sen'];

        yield 'arg-after-option' => [CompletionInput::fromTokens(['bin/console', '--without-value'], 2), CompletionInput::TYPE_ARGUMENT_VALUE, 'required-arg', ''];
        yield 'arg-after-optional-value-option' => [CompletionInput::fromTokens(['bin/console', '--with-optional-value', '--'], 3), CompletionInput::TYPE_ARGUMENT_VALUE, 'required-arg', ''];

        // end of definition
        yield 'end' => [CompletionInput::fromTokens(['bin/console', 'symfony', 'sensiolabs'], 3), CompletionInput::TYPE_NONE, null, ''];
    }

    /**
     * @dataProvider provideBindWithLastArrayArgumentData
     */
    public function testBindWithLastArrayArgument(CompletionInput $input, ?string $expectedValue)
    {
        $definition = new InputDefinition([
            new InputArgument('list-arg', InputArgument::IS_ARRAY | InputArgument::REQUIRED),
        ]);

        $input->bind($definition);

        $this->assertEquals(CompletionInput::TYPE_ARGUMENT_VALUE, $input->getCompletionType(), 'Unexpected type');
        $this->assertEquals('list-arg', $input->getCompletionName(), 'Unexpected name');
        $this->assertEquals($expectedValue, $input->getCompletionValue(), 'Unexpected value');
    }

    public function provideBindWithLastArrayArgumentData()
    {
        yield [CompletionInput::fromTokens(['bin/console'], 1), null];
        yield [CompletionInput::fromTokens(['bin/console', 'symfony', 'sensiolabs'], 3), null];
        yield [CompletionInput::fromTokens(['bin/console', 'symfony', 'sen'], 2), 'sen'];
    }

    public function testBindArgumentWithDefault()
    {
        $definition = new InputDefinition([
            new InputArgument('arg-with-default', InputArgument::OPTIONAL, '', 'default'),
        ]);

        $input = CompletionInput::fromTokens(['bin/console'], 1);
        $input->bind($definition);

        $this->assertEquals(CompletionInput::TYPE_ARGUMENT_VALUE, $input->getCompletionType(), 'Unexpected type');
        $this->assertEquals('arg-with-default', $input->getCompletionName(), 'Unexpected name');
        $this->assertEquals('', $input->getCompletionValue(), 'Unexpected value');
    }

    /**
     * @dataProvider provideFromStringData
     */
    public function testFromString($inputStr, array $expectedTokens)
    {
        $input = CompletionInput::fromString($inputStr, 1);

        $tokensProperty = (new \ReflectionClass($input))->getProperty('tokens');
        $tokensProperty->setAccessible(true);

        $this->assertEquals($expectedTokens, $tokensProperty->getValue($input));
    }

    public function provideFromStringData()
    {
        yield ['bin/console cache:clear', ['bin/console', 'cache:clear']];
        yield ['bin/console --env prod', ['bin/console', '--env', 'prod']];
        yield ['bin/console --env=prod', ['bin/console', '--env=prod']];
        yield ['bin/console -eprod', ['bin/console', '-eprod']];
        yield ['bin/console cache:clear "multi word string"', ['bin/console', 'cache:clear', '"multi word string"']];
        yield ['bin/console cache:clear \'multi word string\'', ['bin/console', 'cache:clear', '\'multi word string\'']];
    }
}
