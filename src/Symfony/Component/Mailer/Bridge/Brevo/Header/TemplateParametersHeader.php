<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Brevo\Header;

use Symfony\Component\Mime\Header\UnstructuredHeader;

/**
 * Carries Brevo template parameters with arbitrary types (string, int, bool, array, …).
 *
 * Unlike a generic `ParameterizedHeader`, the Brevo API accepts mixed-type values in the
 * "params" field of the template payload — integers, arrays and booleans are valid and
 * commonly used. This header preserves those values as-is instead of forcing them to
 * strings (which would require a `setParameter()` call that only accepts `?string`).
 */
final class TemplateParametersHeader extends UnstructuredHeader
{
    public function __construct(
        private array $parameters,
    ) {
        parent::__construct('params', '');
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
