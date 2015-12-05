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
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 * @Target({"PROPERTY", "CLASS", "ANNOTATION"})
 *
 * @author Michael Hindley <mikael.chojnacki@gmail.com>
 */
class L18n extends Constraint
{
    /**
     * @var Constraint
     */
    public $constraint;

    /**
     * @var string
     */
    private $locale;

    /**
     * @param null $options
     */
    public function __construct($options = null)
    {
        if (!$options['locale']) {
            throw new MissingOptionsException('Locale is missing');
        }

        $this->locale = $options['locale'];
        $this->constraint = $options['value'];
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @return Constraint
     */
    public function getConstraint()
    {
        return $this->constraint;
    }

    /**
     * {@inheritdoc}
     */
    public function validatedBy()
    {
        return 'validator.l18n';
    }
}
