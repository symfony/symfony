<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Extension\Core\DataTransformer\ValueToDuplicatesTransformer;

class RepeatedType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        // Prepare for spliting options.
        $splitOptions = array(
            'first'  => array(),
            'second' => array()
        );
        
        // Split options. If the type of option is not specified, treat this as a global option.
        foreach ($options['options'] as $optionKey => $optionValue) {
            list($key, $type) = $this->extractSplitOption($optionKey);
            
            if ( ! $type) {
                $splitOptions['first'][$key] = $optionValue;
                $splitOptions['second'][$key] = $optionValue;
                continue;
            }
            
            $splitOptions[$type][$key] = $optionValue;
        }
        
        $builder
            ->appendClientTransformer(new ValueToDuplicatesTransformer(array(
                $options['first_name'],
                $options['second_name'],
            )))
            ->add($options['first_name'], $options['type'], $splitOptions['options'])
            ->add($options['second_name'], $options['type'], $splitOptions['options'])
        ;
    }
    
    /**
     * Check if this option key contains the given prefix.
     *
     * @param string $optionKey
     * @param string $prefix
     *
     * @return boolean
     */
    protected function doesOptionKeyContain($optionKey, $prefix)
    {
        return strpos($optionKey, $prefix) === 0;
    }
    
    /**
     * Extract the option based on its prefix.
     *
     * @param string $optionKey
     * @return array where the first index (0) is a sanitized option key and the second one is a type of option ('first' or 'second').
     */
    protected function extractSplitOption($optionKey)
    {
        $optionType = null;
        
        // Figure out the type of option
        if ($this->doesOptionKeyContain($optionKey, 'first_') {
            $optionType = 'first';
        } else if ($this->doesOptionKeyContain($optionKey, 'second_') {
            $optionType = 'second';
        }
        
        if ($optionType) {
            $optionKey = substr($optionKey, strlen($optionType) + 1);
        }
        
        return array(
            $optionKey,
            $optionType
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions(array $options)
    {
        return array(
            'type'              => 'text',
            'options'           => array(),
            'first_name'        => 'first',
            'second_name'       => 'second',
            'error_bubbling'    => false,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'repeated';
    }
}
