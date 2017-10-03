<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Console\Descriptor;

use Symfony\Component\Form\ResolvedFormTypeInterface;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
class JsonDescriptor extends Descriptor
{
    protected function describeDefaults(array $options = array())
    {
        $data['builtin_form_types'] = $this->getCoreTypes();
        $data['service_form_types'] = array_values(array_diff($options['types'], $data['builtin_form_types']));
        $data['type_extensions'] = $options['extensions'];
        $data['type_guessers'] = $options['guessers'];

        $this->writeData($data, $options);
    }

    protected function describeResolvedFormType(ResolvedFormTypeInterface $resolvedFormType, array $options = array())
    {
        $this->collectOptions($resolvedFormType);

        $formOptions = array(
            'own' => $this->ownOptions,
            'overridden' => $this->overriddenOptions,
            'parent' => $this->parentOptions,
            'extension' => $this->extensionOptions,
            'required' => $this->requiredOptions,
        );
        $this->sortOptions($formOptions);

        $data = array(
            'class' => get_class($resolvedFormType->getInnerType()),
            'block_prefix' => $resolvedFormType->getInnerType()->getBlockPrefix(),
            'options' => $formOptions,
            'parent_types' => $this->parents,
            'type_extensions' => $this->extensions,
        );

        $this->writeData($data, $options);
    }

    private function writeData(array $data, array $options)
    {
        $flags = isset($options['json_encoding']) ? $options['json_encoding'] : 0;
        $this->output->write(json_encode($data, $flags | JSON_PRETTY_PRINT)."\n");
    }

    private function sortOptions(array &$options)
    {
        foreach ($options as &$opts) {
            $sorted = false;
            foreach ($opts as &$opt) {
                if (is_array($opt)) {
                    sort($opt);
                    $sorted = true;
                }
            }
            if (!$sorted) {
                sort($opts);
            }
        }
    }
}
