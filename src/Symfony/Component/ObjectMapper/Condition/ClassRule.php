<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Condition;

use Symfony\Component\ObjectMapper\Exception\InvalidArgumentException;

/**
 * @template T1 of object
 * @template T2 of object
 *
 * @implements ClassRuleConditionCallableInterface<T1, T2>
 */
final class ClassRule implements ClassRuleConditionCallableInterface
{
    /**
     * @param array<class-string>|null $sources
     * @param array<class-string>|null $targets
     */
    public function __construct(
        private array $sources = [],
        private array $targets = [],
    ) {
        if (!$this->sources && !$this->targets) {
            throw new InvalidArgumentException('A ClassRule needs a sources list and/or a targets list.');
        }
    }

    public function __invoke(mixed $value, object $source, ?object $target): bool
    {
        $sourceMatch = !$this->sources;
        $targetMatch = !$this->targets;

        foreach ($this->sources as $sourceClass) {
            if ($source instanceof $sourceClass) {
                $sourceMatch = true;
                break;
            }
        }

        foreach ($this->targets as $targetClass) {
            if ($target instanceof $targetClass) {
                $targetMatch = true;
                break;
            }
        }

        return $sourceMatch && $targetMatch;
    }
}
