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

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class MissingDataHandler
{
    public readonly \stdClass $missingData;

    public function __construct()
    {
        $this->missingData = new \stdClass();
    }

    public function handle(FormInterface $form, mixed $data): mixed
    {
        $processedData = $this->handleMissingData($form, $data);

        return $processedData === $this->missingData ? $data : $processedData;
    }

    private function handleMissingData(FormInterface $form, mixed $data): mixed
    {
        try {
            if ($form->getConfig()->getType() instanceof ResolvedFormTypeInterface && $form->getConfig()->getType()->getInnerType() instanceof CheckboxType) {
                $falseValues = $form->getConfig()->getOption('false_values');

                if ($data === $this->missingData) {
                    return $falseValues[0];
                }

                if (\in_array($data, $falseValues)) {
                    return $data;
                }
            }
        }
        catch (\Error $error) {
            if ($error->getMessage() === 'Typed property Symfony\Component\Form\FormConfigBuilder::$type must not be accessed before initialization') {
                return $data;
            }

            throw $error;
        }

        if (null === $data || $this->missingData === $data) {
            $data = $form->getConfig()->getCompound() ? [] : $data;
        }

        if (\is_array($data)) {
            $children = $form->getConfig()->getCompound() ? $form->all() : [$form];

            foreach ($children as $child) {
                $value = $this->handleMissingData($child, \array_key_exists($child->getName(), $data) ? $data[$child->getName()] : $this->missingData);

                if ($this->missingData !== $value) {
                    $data[$child->getName()] = $value;
                }
            }

            return $data ?: $this->missingData;
        }

        return $data;
    }
}
