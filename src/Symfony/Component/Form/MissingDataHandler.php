<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * @internal
 */
class MissingDataHandler
{
    public readonly \stdClass $missingData;

    public function __construct()
    {
        $this->missingData = new \stdClass();
    }

    /**
     * Folds default values (typically from `false_values`) into $data for children the payload omits.
     *
     * Callers may pass `$this->missingData` as the sentinel for "no data submitted". Returns $data
     * unchanged when no child synthesises a value.
     */
    public function handle(FormInterface $form, mixed $data): mixed
    {
        $processedData = $this->handleMissingData($form, $data);

        return $processedData === $this->missingData ? $data : $processedData;
    }

    /**
     * Returns `$this->missingData` to signal "nothing to synthesise for this branch"; the public
     * wrapper substitutes the caller's original $data back in.
     */
    private function handleMissingData(FormInterface $form, mixed $data): mixed
    {
        $config = $form->getConfig();
        $missingData = $this->missingData;
        $falseValues = $config->getOption('false_values', null);

        if (\is_array($falseValues)) {
            if ($data === $missingData) {
                return $falseValues[0] ?? null;
            }

            if (\in_array($data, $falseValues, true)) {
                return $data;
            }
        }

        if (null === $data || $missingData === $data) {
            $data = $config->getCompound() ? [] : $data;
        }

        if (\is_array($data)) {
            $children = $config->getCompound() ? $form->all() : [$form];
            $allowDelete = $config->getOption('allow_delete', false);

            foreach ($children as $child) {
                $name = $child->getName();
                $childData = $missingData;

                if (\array_key_exists($name, $data)) {
                    $childData = $data[$name];
                } elseif ($allowDelete) {
                    // Don't re-add a deleted collection entry so ResizeFormListener can remove it.
                    continue;
                }

                $value = $this->handleMissingData($child, $childData);

                if ($missingData !== $value) {
                    $data[$name] = $value;
                }
            }

            return $data ?: $missingData;
        }

        return $data;
    }
}
