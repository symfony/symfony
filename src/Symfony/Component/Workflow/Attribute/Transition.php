<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Attribute;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT | \Attribute::IS_REPEATABLE)]
final class Transition
{
    public function __construct(
        public string|array|\BackedEnum $froms = [],
        public string|array|\BackedEnum $tos = [],
        public array $metadata = [],
        public ?string $guard = null,
    ) {
        $this->froms = $this->normalizeValues($froms);
        $this->tos = $this->normalizeValues($tos);
    }

    private function normalizeValues(string|array|\BackedEnum $values): array
    {
        if (\is_string($values) || $values instanceof \BackedEnum) {
            $values = [$values];
        }

        foreach ($values as $k => $value) {
            if ($value instanceof \BackedEnum) {
                $values[$k] = $value = $value->value;
            }
            if (\is_string($value)) {
                $values[$k] = [
                    'place' => $value,
                    'weighted' => 1,
                ];
            }
        }

        return $values;
    }
}
