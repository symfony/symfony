<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Csrf\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilder;

class FormTypeCsrfExtension extends AbstractTypeExtension
{
    private $enabled;
    private $fieldName;

    public function __construct($enabled = true, $fieldName = '_token')
    {
        $this->enabled = $enabled;
        $this->fieldName = $fieldName;
    }

    public function buildForm(FormBuilder $builder, array $options)
    {
        if ($options['csrf_protection']) {
            $csrfOptions = array('page_id' => $options['csrf_page_id']);

            if ($options['csrf_provider']) {
                $csrfOptions['csrf_provider'] = $options['csrf_provider'];
            }

            $builder->add($options['csrf_field_name'], 'csrf', $csrfOptions);
        }
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'csrf_protection' => $this->enabled,
            'csrf_field_name' => $this->fieldName,
            'csrf_provider'   => null,
            'csrf_page_id'    => get_class($this),
        );
    }

    public function getExtendedType()
    {
        return 'form';
    }
}
