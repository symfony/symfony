<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Validator;

@trigger_error('The '.__NAMESPACE__.'\LegacyValidator class is deprecated since version 2.5 and will be removed in 3.0.', E_USER_DEPRECATED);

/**
 * A validator that supports both the API of Symfony < 2.5 and Symfony 2.5+.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see \Symfony\Component\Validator\ValidatorInterface
 * @see \Symfony\Component\Validator\Validator\ValidatorInterface
 *
 * @deprecated since version 2.5, to be removed in 3.0.
 */
class LegacyValidator extends RecursiveValidator
{
}
