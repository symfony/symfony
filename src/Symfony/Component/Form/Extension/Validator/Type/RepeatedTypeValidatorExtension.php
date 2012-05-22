<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\OptionsResolver\Options;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RepeatedTypeValidatorExtension extends AbstractTypeExtension
{
    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions()
    {
        // Map errors to the first field
        $errorMapping = function (Options $options) {
            return array('.' => $options['first_name']);
        };

        return array(
            'error_mapping' => $errorMapping,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'repeated';
    }
}
