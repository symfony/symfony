<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\Extension\Core\DataMapper\FormDataToObjectConverterInterface;

class MoneyTypeConverter implements FormDataToObjectConverterInterface
{
    /**
     * {@inheritdoc}
     *
     * @param Money|null $originalData
     */
    public function convertFormDataToObject(array $data, $originalData)
    {
        // Logic to determine if the result should be considered null according to form fields data.
        if (null === $data['amount'] && null === $data['currency']) {
            return;
        }

        return new Money($data['amount'], $data['currency']);
    }
}
