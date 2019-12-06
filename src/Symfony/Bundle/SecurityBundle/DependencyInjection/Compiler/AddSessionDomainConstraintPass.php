<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Uses the session domain to restrict allowed redirection targets.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AddSessionDomainConstraintPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('session.storage.options') || !$container->has('security.http_utils')) {
            return;
        }

        $sessionOptions = $container->getParameter('session.storage.options');
        $domainRegexp = empty($sessionOptions['cookie_domain']) ? '%%s' : sprintf('(?:%%%%s|(?:.+\.)?%s)', preg_quote(trim($sessionOptions['cookie_domain'], '.')));
        $domainRegexp = (empty($sessionOptions['cookie_secure']) ? 'https?://' : 'https://').$domainRegexp;

        $container->findDefinition('security.http_utils')->addArgument(sprintf('{^%s$}i', $domainRegexp));
    }
}
