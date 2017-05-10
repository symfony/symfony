<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION", "CLASS"})
 *
 * @author Cas Leentfaar <info@casleentfaar.com>
 */
class Any extends Composite
{
    /**
     * @var string
     */
    public $message = 'None of the contraints found the value to be valid';

    /**
     * @var Constraint[]
     */
    public $constraints = array();

    /**
     * {@inheritdoc}
     */
    public function __construct($options = null)
    {
        parent::__construct($options);

        $this->message = array_key_exists('message', $options) ? $options['message'] : $this->message;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'constraints';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredOptions()
    {
        return array(
            'constraints',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getCompositeOption()
    {
        return 'constraints';
    }
}
