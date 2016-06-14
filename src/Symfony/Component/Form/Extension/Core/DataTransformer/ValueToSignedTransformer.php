<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @author William Pottier <developer@william-pottier.fr>
 */
class ValueToSignedTransformer implements DataTransformerInterface
{
    private $fieldKey;

    private $signedKey;

    private $secret;

    private $algo;

    public function __construct($fieldKey, $signedKey, $secret, $algo = 'sha512')
    {
        $this->fieldKey = $fieldKey;
        $this->signedKey = $signedKey;
        $this->secret = $secret;
        $this->algo = $algo;
    }

    /**
     * Sign the given value and return an array with the value and the signature.
     *
     * @param mixed $value The value
     *
     * @return array The array
     */
    public function transform($value)
    {
        return array(
            $this->fieldKey => $value,
            $this->signedKey => $this->calculateSignature($value),
        );
    }

    /**
     * Extracts the value and check signature from an array.
     *
     * @param array $array
     *
     * @return mixed The value
     *
     * @throws TransformationFailedException If the given value is not an array or
     *                                       if the given array can not be transformed.
     */
    public function reverseTransform($array)
    {
        if (!is_array($array)) {
            throw new TransformationFailedException('Expected an array.');
        }

        if (!array_key_exists($this->fieldKey, $array) || !array_key_exists($this->signedKey, $array)) {
            throw new TransformationFailedException('Expected an array with data and signature.');
        }

        $newSignature = $this->calculateSignature($array[$this->fieldKey]);

        if (!hash_equals($array[$this->signedKey], $newSignature)) {
            throw new TransformationFailedException(
                'The signature does not match with the provided data.'
            );
        }

        return $array[$this->fieldKey];
    }

    /**
     * Calculate signature for the provided data based on the configured algo & secret.
     *
     * @return string the calculated signature
     */
    protected function calculateSignature($value)
    {
        $ctx = hash_init($this->algo, HASH_HMAC, $this->secret);

        if (!is_array($value)) {
            $value = array($value);
        }

        foreach ($value as $valueElement) {
            hash_update($ctx, $valueElement);
        }

        return hash_final($ctx);
    }
}