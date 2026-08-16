<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Fragment;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Twig\Environment;

/**
 * Implements the Hinclude rendering strategy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 8.2, will be removed in 9.0; use the "esi" or "inline" fragment renderer, or Symfony UX Turbo, instead
 */
class HIncludeFragmentRenderer extends RoutableFragmentRenderer
{
    /**
     * @param string|null $globalDefaultTemplate The global default content (it can be a template name or the content)
     */
    public function __construct(
        private ?Environment $twig = null,
        private ?UriSigner $signer = null,
        private ?string $globalDefaultTemplate = null,
        private string $charset = 'utf-8',
    ) {
        trigger_deprecation('symfony/http-kernel', '8.2', 'The "%s" class is deprecated and will be removed in 9.0; use the "esi" or "inline" fragment renderer, or Symfony UX Turbo, instead.', self::class);
    }

    /**
     * Checks if a templating engine has been set.
     */
    public function hasTemplating(): bool
    {
        return null !== $this->twig;
    }

    /**
     * Additional available options:
     *
     *  * default:    The default content (it can be a template name or the content)
     *  * id:         An optional hx:include tag id attribute
     *  * attributes: An optional array of hx:include tag attributes
     */
    public function render(string|ControllerReference $uri, Request $request, array $options = []): Response
    {
        if ($uri instanceof ControllerReference) {
            $uri = (new FragmentUriGenerator($this->fragmentPath, $this->signer))->generate($uri, $request);
        }

        // We need to replace ampersands in the URI with the encoded form in order to return valid html/xml content.
        $uri = str_replace('&', '&amp;', $uri);

        $template = $options['default'] ?? $this->globalDefaultTemplate;
        if (null !== $this->twig && $template && $this->twig->getLoader()->exists($template)) {
            $content = $this->twig->render($template);
        } else {
            $content = $template;
        }

        $attributes = isset($options['attributes']) && \is_array($options['attributes']) ? $options['attributes'] : [];
        if (isset($options['id']) && $options['id']) {
            $attributes['id'] = $options['id'];
        }
        $renderedAttributes = '';
        if ($attributes) {
            $flags = \ENT_QUOTES | \ENT_SUBSTITUTE;
            foreach ($attributes as $attribute => $value) {
                $renderedAttributes .= \sprintf(
                    ' %s="%s"',
                    htmlspecialchars($attribute, $flags, $this->charset, false),
                    htmlspecialchars($value, $flags, $this->charset, false)
                );
            }
        }

        return new Response(\sprintf('<hx:include src="%s"%s>%s</hx:include>', $uri, $renderedAttributes, $content));
    }

    public function getName(): string
    {
        return 'hinclude';
    }
}
