<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * RedirectResponse represents an HTTP response doing a redirect.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class RedirectResponse extends Response
{
    protected $targetUrl;

    /**
     * Creates a redirect response so that it conforms to the rules defined for a redirect status code.
     *
     * @param string  $url     The URL to redirect to
     * @param integer $status  The status code (302 by default)
     * @param array   $headers The headers (Location is always set to the given url)
     *
     * @throws \InvalidArgumentException
     *
     * @see http://tools.ietf.org/html/rfc2616#section-10.3
     *
     * @api
     */
    public function __construct($url, $status = 302, $headers = array())
    {
        if (empty($url)) {
            throw new \InvalidArgumentException('Cannot redirect to an empty URL.');
        }

        parent::__construct('', $status, $headers);

        $this->setTargetUrl($url);

        if (!$this->isRedirect()) {
            throw new \InvalidArgumentException(sprintf('The HTTP status code is not a redirect ("%s" given).', $status));
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function create($url = '', $status = 302, $headers = array())
    {
        return new static($url, $status, $headers);
    }

    /**
     * Returns the target URL.
     *
     * @return string target URL
     */
    public function getTargetUrl()
    {
        return $this->targetUrl;
    }

    /**
     * Sets the redirect target of this response.
     *
     * @param string  $url     The URL to redirect to
     *
     * @return RedirectResponse The current response.
     *
     * @throws \InvalidArgumentException
     */
    public function setTargetUrl($url)
    {
        if (empty($url)) {
            throw new \InvalidArgumentException('Cannot redirect to an empty URL.');
        }

        $this->targetUrl = $url;

        /*
         * We're a bit lost here as we don't know the encoding of the URL given
         * and we also don't know what the target system/app expects.
         *
         * RFC 1630 mandates ASCII-only URLs (so use urlencode()), but there's
         * still a difference in having a (e. g.) latin-1 or utf-8 string
         * urlencoded.
         *
         * For links, browsers will usually use the encoding of the page containing
         * the link before applying url-encoding. So for cross-site links or
         * URLs entered directly YMMV.
         *
         * Thus, we try our best to keep the encoding that was probably used. Note that we also
         * don't convert the URL to utf-8 for the redirect page, as that would on an
         * otherwise latin-1 site lead browsers to request url-encoded multibyte sequences
         * instead of (urlencoded) single-byte non-ascii chars (umlauts for example).
         */
        if (function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($url, 'UTF-8, ISO-8859-1, ISO-8859-15, cp866, cp1251, cp1252, KOI8-R');
        } else {
            $encoding = 'utf-8'; // as it was previously assumed
        }

        $urlencodedUrl = implode("/", array_map("rawurlencode", explode("/", $url)));

        $this->setContent(
            sprintf('<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=%2$s" />
        <meta http-equiv="refresh" content="1;url=%1$s" />

        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>', htmlspecialchars($url, ENT_QUOTES), $encoding));

        $this->headers->set('Location', $urlencodedUrl);

        return $this;
    }
}
