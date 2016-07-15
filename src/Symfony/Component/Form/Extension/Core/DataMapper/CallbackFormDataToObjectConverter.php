<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataMapper;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class CallbackFormDataToObjectConverter implements FormDataToObjectConverterInterface
{
    /**
     * The callable used to map form data to an object.
     *
     * @var callable
     */
    private $converter;

    /**
     * @param callable $converter
     */
    public function __construct(callable $converter)
    {
        $this->converter = $converter;
    }

    /**
     * {@inheritdoc}
     */
    public function convertFormDataToObject(array $data, $originalData)
    {
        return call_user_func($this->converter, $data, $originalData);
    }
}
