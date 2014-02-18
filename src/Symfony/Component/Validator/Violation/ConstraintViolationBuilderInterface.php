<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Violation;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ConstraintViolationBuilderInterface
{
    public function atPath($subPath);

    public function setParameter($key, $value);

    public function setParameters(array $parameters);

    public function setTranslationDomain($translationDomain);

    public function setInvalidValue($invalidValue);

    public function setPluralization($pluralization);

    public function setCode($code);

    public function addViolation();
}
