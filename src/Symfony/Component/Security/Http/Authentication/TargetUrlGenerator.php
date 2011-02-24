<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication;

use Symfony\Component\HttpFoundation\Request;

/**
 * TargetUrlGenerator determines the authentication target url
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Thibault Duplessis <thibault.duplessis@gmail.com>
 */
class TargetUrlGenerator
{
    protected $options;

    public function __construct($options)
    {
        $this->options = $options;
    }

    /**
     * Builds the target URL according to the defined options.
     *
     * @param Request $request
     *
     * @return string
     */
    public function determineTargetUrl(Request $request)
    {
        if ($this->options['always_use_default_target_path']) {
            return $this->options['default_target_path'];
        }

        if ($targetUrl = $request->get($this->options['target_path_parameter'])) {
            return $targetUrl;
        }

        $session = $request->getSession();
        if ($targetUrl = $session->get('_security.target_path')) {
            $session->remove('_security.target_path');

            return $targetUrl;
        }

        if ($this->options['use_referer'] && $targetUrl = $request->headers->get('Referer')) {
            return $targetUrl;
        }

        return $this->options['default_target_path'];
    }
}
