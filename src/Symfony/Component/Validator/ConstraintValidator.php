<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

/*
 * @api
 */
abstract class ConstraintValidator implements ConstraintValidatorInterface
{
    /**
     * @var ExecutionContext
     */
    protected $context;
    /**
     * @var string
     */
    private $messageTemplate;
    /**
     * @var array
     */
    private $messageParameters;

    /**
     * {@inheritDoc}
     */
    public function initialize(ExecutionContext $context)
    {
        $this->context = $context;
        $this->messageTemplate = '';
        $this->messageParameters = array();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getMessageTemplate()
    {
        return $this->messageTemplate;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function getMessageParameters()
    {
        return $this->messageParameters;
    }

    /**
     * @api
     */
    protected function setMessage($template, array $parameters = array())
    {
        $this->messageTemplate = $template;
        $this->messageParameters = $parameters;
    }
}
