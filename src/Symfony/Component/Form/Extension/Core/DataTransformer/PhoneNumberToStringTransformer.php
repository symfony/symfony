<?php

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class PhoneNumberToStringTransformer implements DataTransformerInterface
{
    /**
     * @var string $region
     */
    protected $region;

    public function __construct($region = null)
    {
        $this->region = $region;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($value)
    {
        if (null === $value) {
            return '';
        }

        return PhoneNumberUtil::getInstance()->format($value, $this->region);

    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        if (!is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if ('' === $value) {
            return;
        }

        try {
            return PhoneNumberUtil::getInstance()->parse($value, $this->region);
        } catch (\Exception $exception) {
            throw new TransformationFailedException(sprintf("Unable to parse phone number : %s", $value));
        }

    }
}
