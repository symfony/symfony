<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\Form\Tests\Fixtures\ConfigurableFormType;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormFactoryTest extends TestCase
{
    /**
     * @var ConfigurableFormTypeGuesser
     */
    private $guesser1;

    /**
     * @var ConfigurableFormTypeGuesser
     */
    private $guesser2;

    /**
     * @var FormRegistryInterface
     */
    private $registry;

    /**
     * @var FormFactory
     */
    private $factory;

    protected function setUp(): void
    {
        $this->guesser1 = new ConfigurableFormTypeGuesser();
        $this->guesser2 = new ConfigurableFormTypeGuesser();
        $this->registry = new FormRegistry([
            new PreloadedExtension([
                new ConfigurableFormType(),
            ], [], new FormTypeGuesserChain([$this->guesser1, $this->guesser2])),
        ], new ResolvedFormTypeFactory());
        $this->factory = new FormFactory($this->registry);
    }

    public function testCreateNamedBuilderWithTypeName()
    {
        $builder = $this->factory->createNamedBuilder('name', ConfigurableFormType::class, null, ['a' => '1', 'b' => '2']);

        $this->assertSame('1', $builder->getOption('a'));
        $this->assertSame('2', $builder->getOption('b'));
    }

    public function testCreateNamedBuilderFillsDataOption()
    {
        $builder = $this->factory->createNamedBuilder('name', ConfigurableFormType::class, 'DATA', ['a' => '1', 'b' => '2']);

        $this->assertSame('DATA', $builder->getOption('data'));
    }

    public function testCreateNamedBuilderDoesNotOverrideExistingDataOption()
    {
        $builder = $this->factory->createNamedBuilder('name', ConfigurableFormType::class, 'DATA', ['a' => '1', 'b' => '2', 'data' => 'CUSTOM']);

        $this->assertSame('CUSTOM', $builder->getOption('data'));
    }

    public function testCreateUsesBlockPrefixIfTypeGivenAsString()
    {
        $form = $this->factory->create(ConfigurableFormType::class);

        $this->assertSame('configurable_form_prefix', $form->getName());
    }

    public function testCreateNamed()
    {
        $form = $this->factory->createNamed('name', ConfigurableFormType::class, null, ['a' => '1', 'b' => '2']);

        $this->assertSame('1', $form->getConfig()->getOption('a'));
        $this->assertSame('2', $form->getConfig()->getOption('b'));
    }

    public function testCreateBuilderForPropertyWithoutTypeGuesser()
    {
        $builder = $this->factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertSame('firstName', $builder->getName());
    }

    public function testCreateBuilderForPropertyCreatesFormWithHighestConfidence()
    {
        $this->guesser1->configureTypeGuess(TextType::class, ['attr' => ['maxlength' => 10]], Guess::MEDIUM_CONFIDENCE);
        $this->guesser2->configureTypeGuess(PasswordType::class, ['attr' => ['maxlength' => 7]], Guess::HIGH_CONFIDENCE);

        $builder = $this->factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertSame('firstName', $builder->getName());
        $this->assertSame(['maxlength' => 7], $builder->getOption('attr'));
        $this->assertInstanceOf(PasswordType::class, $builder->getType()->getInnerType());
    }

    public function testCreateBuilderCreatesTextFormIfNoGuess()
    {
        $builder = $this->factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertSame('firstName', $builder->getName());
        $this->assertInstanceOf(TextType::class, $builder->getType()->getInnerType());
    }

    public function testOptionsCanBeOverridden()
    {
        $this->guesser1->configureTypeGuess(TextType::class, ['attr' => ['class' => 'foo', 'maxlength' => 10]], Guess::MEDIUM_CONFIDENCE);

        $builder = $this->factory->createBuilderForProperty('Application\Author', 'firstName', null, ['attr' => ['maxlength' => 11]]);

        $this->assertSame('firstName', $builder->getName());
        $this->assertSame(['class' => 'foo', 'maxlength' => 11], $builder->getOption('attr'));
        $this->assertInstanceOf(TextType::class, $builder->getType()->getInnerType());
    }

    public function testCreateBuilderUsesMaxLengthIfFound()
    {
        $this->guesser1->configureMaxLengthGuess(15, Guess::MEDIUM_CONFIDENCE);
        $this->guesser2->configureMaxLengthGuess(20, Guess::HIGH_CONFIDENCE);

        $builder = $this->factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertSame('firstName', $builder->getName());
        $this->assertSame(['maxlength' => 20], $builder->getOption('attr'));
        $this->assertInstanceOf(TextType::class, $builder->getType()->getInnerType());
    }

    public function testCreateBuilderUsesMaxLengthAndPattern()
    {
        $this->guesser1->configureMaxLengthGuess(20, Guess::HIGH_CONFIDENCE);
        $this->guesser2->configurePatternGuess('.{5,}', Guess::HIGH_CONFIDENCE);

        $builder = $this->factory->createBuilderForProperty('Application\Author', 'firstName', null, ['attr' => ['class' => 'tinymce']]);

        $this->assertSame('firstName', $builder->getName());
        $this->assertSame(['maxlength' => 20, 'pattern' => '.{5,}', 'class' => 'tinymce'], $builder->getOption('attr'));
        $this->assertInstanceOf(TextType::class, $builder->getType()->getInnerType());
    }

    public function testCreateBuilderUsesRequiredSettingWithHighestConfidence()
    {
        $this->guesser1->configureRequiredGuess(true, Guess::MEDIUM_CONFIDENCE);
        $this->guesser2->configureRequiredGuess(false, Guess::HIGH_CONFIDENCE);

        $builder = $this->factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertSame('firstName', $builder->getName());
        $this->assertFalse($builder->getOption('required'));
        $this->assertInstanceOf(TextType::class, $builder->getType()->getInnerType());
    }

    public function testCreateBuilderUsesPatternIfFound()
    {
        $this->guesser1->configurePatternGuess('[a-z]', Guess::MEDIUM_CONFIDENCE);
        $this->guesser2->configurePatternGuess('[a-zA-Z]', Guess::HIGH_CONFIDENCE);

        $builder = $this->factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertSame('firstName', $builder->getName());
        $this->assertSame(['pattern' => '[a-zA-Z]'], $builder->getOption('attr'));
        $this->assertInstanceOf(TextType::class, $builder->getType()->getInnerType());
    }
}

class ConfigurableFormTypeGuesser implements FormTypeGuesserInterface
{
    private $typeGuess;
    private $requiredGuess;
    private $maxLengthGuess;
    private $patternGuess;

    public function guessType($class, $property): ?TypeGuess
    {
        return $this->typeGuess;
    }

    public function guessRequired($class, $property): ?ValueGuess
    {
        return $this->requiredGuess;
    }

    public function guessMaxLength($class, $property): ?ValueGuess
    {
        return $this->maxLengthGuess;
    }

    public function guessPattern($class, $property): ?ValueGuess
    {
        return $this->patternGuess;
    }

    public function configureTypeGuess(string $type, array $options, int $confidence): void
    {
        $this->typeGuess = new TypeGuess($type, $options, $confidence);
    }

    public function configureRequiredGuess(bool $required, int $confidence): void
    {
        $this->requiredGuess = new ValueGuess($required, $confidence);
    }

    public function configureMaxLengthGuess(int $maxLength, int $confidence): void
    {
        $this->maxLengthGuess = new ValueGuess($maxLength, $confidence);
    }

    public function configurePatternGuess(string $pattern, int $confidence): void
    {
        $this->patternGuess = new ValueGuess($pattern, $confidence);
    }
}
