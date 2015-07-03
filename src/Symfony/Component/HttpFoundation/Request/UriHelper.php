<?php
namespace Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\ApacheRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request\RequestHelper;
use Symfony\Component\HttpFoundation\Request\Uri\UriResolver;
use Symfony\Component\HttpFoundation\Request\Uri\UriResolverInterface;

class UriHelper
{
    protected $requestHelper;
    protected $uriResolver;

    /**
     * Normalizes a query string.
     *
     * It builds a normalized query string, where keys/value pairs are alphabetized,
     * have consistent escaping and unneeded delimiters are removed.
     *
     * @param string $qs Query string
     *
     * @return string A normalized query string for the Request
     */
    public static function normalizeQueryString($qs)
    {
        if ('' == $qs) {
            return '';
        }

        $parts = array();
        $order = array();

        foreach (explode('&', $qs) as $param) {
            if ('' === $param || '=' === $param[0]) {
                // Ignore useless delimiters, e.g. "x=y&".
                // Also ignore pairs with empty key, even if there was a value, e.g. "=value", as such nameless values cannot be retrieved anyway.
                // PHP also does not include them when building _GET.
                continue;
            }

            $keyValuePair = explode('=', $param, 2);

            // GET parameters, that are submitted from a HTML form, encode spaces as "+" by default (as defined in enctype application/x-www-form-urlencoded).
            // PHP also converts "+" to spaces when filling the global _GET or when using the function parse_str. This is why we use urldecode and then normalize to
            // RFC 3986 with rawurlencode.
            $parts[] = isset($keyValuePair[1]) ?
                rawurlencode(urldecode($keyValuePair[0])).'='.rawurlencode(urldecode($keyValuePair[1])) :
                rawurlencode(urldecode($keyValuePair[0]));
            $order[] = urldecode($keyValuePair[0]);
        }

        array_multisort($order, SORT_ASC, $parts);

        return implode('&', $parts);
    }

    public function __construct(
        RequestHelper $requestHelper,
        UriResolverInterface $uriResolver = null
    ) {
        $this->requestHelper = $requestHelper;
        $this->uriResolver = $uriResolver ?: UriResolver::create();
    }

    /**
     * Returns the requested URI (path and query string).
     *
     * @param Request $request
     * @return string The raw URI (i.e. not URI decoded)
     *
     * @api
     */
    public function getRequestUri(Request $request)
    {
        return $this->uriResolver->resolveUri($request);
    }

    /**
     * Returns the path being requested relative to the executed script.
     *
     * The path info always starts with a /.
     *
     * Suppose this request is instantiated from /mysite on localhost:
     *
     *  * http://localhost/mysite              returns an empty string
     *  * http://localhost/mysite/about        returns '/about'
     *  * http://localhost/mysite/enco%20ded   returns '/enco%20ded'
     *  * http://localhost/mysite/about?var=1  returns '/about'
     *
     * @param Request $request
     * @return string The raw path (i.e. not urldecoded)
     *
     * @api
     */
    public function getPathInfo(Request $request)
    {
        $baseUrl = $this->getBaseUrl($request);

        if (null === ($requestUri = $this->uriResolver->resolveUri($request))) {
            return '/';
        }

        $pathInfo = '/';

        // Remove the query string from REQUEST_URI
        if ($pos = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $pos);
        }

        if (null !== $baseUrl && false === $pathInfo = substr($requestUri, strlen($baseUrl))) {
            // If substr() returns false then PATH_INFO is set to an empty string
            return '/';
        } elseif (null === $baseUrl) {
            return $requestUri;
        }

        return (string) $pathInfo;
    }

    /**
     * Returns the root path from which this request is executed.
     *
     * Suppose that an index.php file instantiates this request object:
     *
     *  * http://localhost/index.php         returns an empty string
     *  * http://localhost/index.php/page    returns an empty string
     *  * http://localhost/web/index.php     returns '/web'
     *  * http://localhost/we%20b/index.php  returns '/we%20b'
     *
     * @param Request $request
     * @return string The raw path (i.e. not urldecoded)
     *
     * @api
     */
    public function getBasePath(Request $request)
    {
        $baseUrl = $this->getBaseUrl($request);
        if (empty($baseUrl)) {
            return '';
        }

        $filename = basename($request->server->get('SCRIPT_FILENAME'));

        if (basename($baseUrl) === $filename) {
            $basePath = dirname($baseUrl);
        } else {
            $basePath = $baseUrl;
        }

        if ('\\' === DIRECTORY_SEPARATOR) {
            $basePath = str_replace('\\', '/', $basePath);
        }

        return rtrim($basePath, '/');
    }

    /**
     * Returns the root URL from which this request is executed.
     *
     * The base URL never ends with a /.
     *
     * This is similar to getBasePath(), except that it also includes the
     * script filename (e.g. index.php) if one exists.
     *
     * @param Request $request
     * @return string The raw URL (i.e. not urldecoded)
     *
     * @api
     */
    public function getBaseUrl(Request $request)
    {
        if ($request instanceof ApacheRequest) {
            $baseUrl = $request->server->get('SCRIPT_NAME');

            if (false === strpos($request->server->get('REQUEST_URI'), $baseUrl)) {
                // assume mod_rewrite
                return rtrim(dirname($baseUrl), '/\\');
            }

            return $baseUrl;
        }

        $filename = basename($request->server->get('SCRIPT_FILENAME'));

        if (basename($request->server->get('SCRIPT_NAME')) === $filename) {
            $baseUrl = $request->server->get('SCRIPT_NAME');
        } elseif (basename($request->server->get('PHP_SELF')) === $filename) {
            $baseUrl = $request->server->get('PHP_SELF');
        } elseif (basename($request->server->get('ORIG_SCRIPT_NAME')) === $filename) {
            $baseUrl = $request->server->get('ORIG_SCRIPT_NAME'); // 1and1 shared hosting compatibility
        } else {
            // Backtrack up the script_filename to find the portion matching
            // php_self
            $path = $request->server->get('PHP_SELF', '');
            $file = $request->server->get('SCRIPT_FILENAME', '');
            $segs = explode('/', trim($file, '/'));
            $segs = array_reverse($segs);
            $index = 0;
            $last = count($segs);
            $baseUrl = '';
            do {
                $seg = $segs[$index];
                $baseUrl = '/'.$seg.$baseUrl;
                ++$index;
            } while ($last > $index && (false !== $pos = strpos($path, $baseUrl)) && 0 != $pos);
        }

        // Does the baseUrl have anything in common with the request_uri?
        $requestUri = $this->getRequestUri($request);

        if ($baseUrl && false !== $prefix = $this->getUrlencodedPrefix($requestUri, $baseUrl)) {
            // full $baseUrl matches
            return $prefix;
        }

        if ($baseUrl && false !== $prefix = $this->getUrlencodedPrefix($requestUri, rtrim(dirname($baseUrl), '/').'/')) {
            // directory portion of $baseUrl matches
            return rtrim($prefix, '/');
        }

        $truncatedRequestUri = $requestUri;
        if (false !== $pos = strpos($requestUri, '?')) {
            $truncatedRequestUri = substr($requestUri, 0, $pos);
        }

        $basename = basename($baseUrl);
        if (empty($basename) || !strpos(rawurldecode($truncatedRequestUri), $basename)) {
            // no match whatsoever; set it blank
            return '';
        }

        // If using mod_rewrite or ISAPI_Rewrite strip the script filename
        // out of baseUrl. $pos !== 0 makes sure it is not matching a value
        // from PATH_INFO or QUERY_STRING
        if (strlen($requestUri) >= strlen($baseUrl) && (false !== $pos = strpos($requestUri, $baseUrl)) && $pos !== 0) {
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * Gets the request's scheme.
     *
     * @param Request $request
     * @return string
     * @api
     */
    public function getScheme(Request $request)
    {
        return $this->requestHelper->isSecure($request) ? 'https' : 'http';
    }

    /**
     * Returns the HTTP host being requested.
     *
     * The port name will be appended to the host if it's non-standard.
     *
     * @param Request $request
     * @return string
     * @api
     */
    public function getHttpHost(Request $request)
    {
        $scheme = $this->getScheme($request);
        $port = $this->requestHelper->getPort($request);

        if (('http' == $scheme && $port == 80) || ('https' == $scheme && $port == 443)) {
            return $this->requestHelper->getHost($request);
        }

        return $this->requestHelper->getHost($request).':'.$port;
    }

    /**
     * Generates a normalized URI (URL) for the Request.
     *
     * @param Request $request
     * @return string A normalized URI (URL) for the Request
     *
     * @see getQueryString()
     *
     * @api
     */
    public function getUri(Request $request)
    {
        if (null !== $qs = $this->getQueryString($request)) {
            $qs = '?'.$qs;
        }

        return $this->getSchemeAndHttpHost($request).$this->getBaseUrl($request).$this->getPathInfo($request).$qs;
    }

    /**
     * Gets the scheme and HTTP host.
     *
     * If the URL was called with basic authentication, the user
     * and the password are not added to the generated string.
     *
     * @param Request $request
     * @return string The scheme and HTTP host
     */
    public function getSchemeAndHttpHost(Request $request)
    {
        return $this->getScheme($request).'://'.$this->getHttpHost($request);
    }

    /**
     * Generates a normalized URI for the given path.
     *
     * @param Request $request
     * @param string $path A path to use instead of the current one
     * @return string The normalized URI for the path
     *
     * @api
     */
    public function getUriForPath(Request $request, $path)
    {
        return $this->getSchemeAndHttpHost($request).$this->getBaseUrl($request).$path;
    }

    /**
     * Returns the path as relative reference from the current Request path.
     *
     * Only the URIs path component (no schema, host etc.) is relevant and must be given.
     * Both paths must be absolute and not contain relative parts.
     * Relative URLs from one resource to another are useful when generating self-contained downloadable document archives.
     * Furthermore, they can be used to reduce the link size in documents.
     *
     * Example target paths, given a base path of "/a/b/c/d":
     * - "/a/b/c/d"     -> ""
     * - "/a/b/c/"      -> "./"
     * - "/a/b/"        -> "../"
     * - "/a/b/c/other" -> "other"
     * - "/a/x/y"       -> "../../x/y"
     *
     * @param Request $request
     * @param string $path The target path
     * @return string The relative target path
     */
    public function getRelativeUriForPath(Request $request, $path)
    {
        // be sure that we are dealing with an absolute path
        if (!isset($path[0]) || '/' !== $path[0]) {
            return $path;
        }

        if ($path === $basePath = $this->getPathInfo($request)) {
            return '';
        }

        $sourceDirs = explode('/', isset($basePath[0]) && '/' === $basePath[0] ? substr($basePath, 1) : $basePath);
        $targetDirs = explode('/', isset($path[0]) && '/' === $path[0] ? substr($path, 1) : $path);
        array_pop($sourceDirs);
        $targetFile = array_pop($targetDirs);

        foreach ($sourceDirs as $i => $dir) {
            if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                unset($sourceDirs[$i], $targetDirs[$i]);
            } else {
                break;
            }
        }

        $targetDirs[] = $targetFile;
        $path = str_repeat('../', count($sourceDirs)).implode('/', $targetDirs);

        // A reference to the same base directory or an empty subdirectory must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name
        // (see http://tools.ietf.org/html/rfc3986#section-4.2).
        return !isset($path[0]) || '/' === $path[0]
        || false !== ($colonPos = strpos($path, ':')) && ($colonPos < ($slashPos = strpos($path, '/')) || false === $slashPos)
            ? "./$path" : $path;
    }

    /**
     * Generates the normalized query string for the Request.
     *
     * It builds a normalized query string, where keys/value pairs are alphabetized
     * and have consistent escaping.
     *
     * @param Request $request
     * @return null|string A normalized query string for the Request
     *
     * @api
     */
    public function getQueryString(Request $request)
    {
        $qs = static::normalizeQueryString($request->server->get('QUERY_STRING'));

        return '' === $qs ? null : $qs;
    }

    /*
     * Returns the prefix as encoded in the string when the string starts with
     * the given prefix, false otherwise.
     *
     * @param string $string The urlencoded string
     * @param string $prefix The prefix not encoded
     *
     * @return string|false The prefix as it is encoded in $string, or false
     */
    private function getUrlencodedPrefix($string, $prefix)
    {
        if (0 !== strpos(rawurldecode($string), $prefix)) {
            return false;
        }

        $len = strlen($prefix);

        if (preg_match(sprintf('#^(%%[[:xdigit:]]{2}|.){%d}#', $len), $string, $match)) {
            return $match[0];
        }

        return false;
    }
}