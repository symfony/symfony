<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Exception;

/**
 * @author Kris Wallsmith <kris@symfony.com>
 */
class MethodNotAllowedHttpException extends HttpException
{
    /**
     * @param string[]        $allow    An array of allowed methods
     * @param string|null     $message  The internal exception message
     * @param \Throwable|null $previous The previous exception
     * @param int|null        $code     The internal exception code
     */
    public function __construct(array $allow, ?string $message = '', ?\Throwable $previous = null, ?int $code = 0, array $headers = [])
    {
        if (null === $message) {
            trigger_deprecation('symfony/http-kernel', '5.3', 'Passing null as $message to "%s()" is deprecated, pass an empty string instead.', __METHOD__);

            $message = '';
        }
        if (null === $code) {
            trigger_deprecation('symfony/http-kernel', '5.3', 'Passing null as $code to "%s()" is deprecated, pass 0 instead.', __METHOD__);

            $code = 0;
        }

        $headers['Allow'] = strtoupper(implode(', ', $allow));

        parent::__construct(405, $message, $previous, $headers, $code);
    }
}
