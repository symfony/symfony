<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Attribute;

use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
class AskChoice implements InteractiveAttributeInterface
{
    public ?\Closure $validator;
    public array|\Closure $choices;
    private \Closure $closure;

    /**
     * @param string                                                     $question     The question to ask the user
     * @param array<string|int|float>|callable():array<string|int|float> $choices      The list of available choices (leave empty to use enum cases)
     * @param string|int|float|null                                      $default      The default answer to return if the user enters nothing
     * @param string                                                     $errorMessage The error message when the answer is invalid
     * @param string                                                     $prompt       The prompt displayed before the user input
     * @param callable|null                                              $validator    The validator for the answer
     * @param int|null                                                   $maxAttempts  The maximum number of attempts allowed to answer the question.
     *                                                                                 Null means an unlimited number of attempts
     */
    public function __construct(
        public string $question,
        array|callable $choices = [],
        public string|int|float|null $default = null,
        public string $errorMessage = 'Value "%s" is invalid',
        public string $prompt = ' > ',
        ?callable $validator = null,
        public ?int $maxAttempts = null,
    ) {
        $this->validator = $validator ? $validator(...) : null;
        $this->choices = \is_callable($choices) ? $choices(...) : $choices;
    }

    /**
     * @internal
     */
    public static function tryFrom(\ReflectionParameter|\ReflectionProperty $member, string $name): ?self
    {
        $reflection = new ReflectionMember($member);

        if (!$self = $reflection->getAttribute(self::class)) {
            return null;
        }

        $type = $reflection->getType();

        if (!$type instanceof \ReflectionNamedType) {
            throw new LogicException(\sprintf('The %s "$%s" of "%s" must have a named type. Untyped, Union or Intersection types are not supported for choice questions.', $reflection->getMemberName(), $name, $reflection->getSourceName()));
        }

        $isBackedEnum = is_subclass_of($type->getName(), \BackedEnum::class);

        // Validate that choices are provided or can be derived from enum
        if (!$self->choices && !$isBackedEnum) {
            throw new LogicException(\sprintf('The #[AskChoice] attribute for the %s "$%s" of "%s" requires either explicit choices or a BackedEnum type.', $reflection->getMemberName(), $name, $reflection->getSourceName()));
        }

        $self->closure = function (SymfonyStyle $io, InputInterface $input) use ($self, $reflection, $name, $type, $isBackedEnum) {
            if ($reflection->isProperty() && isset($this->{$reflection->getName()})) {
                return;
            }

            if ($reflection->isParameter() && !\in_array($input->getArgument($name), [null, []], true)) {
                return;
            }

            $choices = $self->choices instanceof \Closure ? ($self->choices)() : $self->choices;

            // Derive choices from enum cases if not provided
            if (!$choices && $isBackedEnum) {
                /** @var class-string<\BackedEnum> $enumClass */
                $enumClass = $type->getName();
                $choices = array_column($enumClass::cases(), 'value');
            }

            $question = new ChoiceQuestion($self->question, $choices, $self->default);
            $question->setMultiselect('array' === $type->getName());
            $question->setErrorMessage($self->errorMessage);
            $question->setPrompt($self->prompt);
            $question->setMaxAttempts($self->maxAttempts);

            if (!$self->validator && $reflection->isProperty() && !$isBackedEnum && 'array' !== $type->getName()) {
                $self->validator = fn (mixed $value): mixed => $this->{$reflection->getName()} = $value;
            }

            if ($self->validator) {
                $question->setValidator($self->validator);
            }

            $value = $io->askQuestion($question);

            if (null === $value && !$reflection->isNullable()) {
                return;
            }

            // Convert back to enum if needed
            if ($isBackedEnum) {
                /** @var class-string<\BackedEnum> $enumClass */
                $enumClass = $type->getName();
                if ($question->isMultiselect() && \is_array($value)) {
                    $value = array_map(static fn ($v) => $enumClass::from($v), $value);
                } else {
                    $value = $enumClass::from($value);
                }
            }

            if ($reflection->isProperty()) {
                $this->{$reflection->getName()} = $value;
            } else {
                $input->setArgument($name, $value);
            }
        };

        return $self;
    }

    /**
     * @internal
     */
    public function getFunction(object $instance): \ReflectionFunction
    {
        return new \ReflectionFunction($this->closure->bindTo($instance, $instance::class));
    }
}
