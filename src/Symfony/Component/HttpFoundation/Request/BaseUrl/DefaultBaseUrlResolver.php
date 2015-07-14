<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 7/7/15
 * Time: 10:56 PM
 */

namespace Symfony\Component\HttpFoundation\Request\BaseUrl;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Request\Uri\UriResolverInterface;

class DefaultBaseUrlResolver implements BaseUrlResolverInterface
{
    protected $uriResolver;

    public function __construct(UriResolverInterface $uriResolver)
    {
        $this->uriResolver = $uriResolver;
    }

    public function resolveBaseUrl(Request $request)
    {
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
        $requestUri = $this->uriResolver->getRequestUri($request);

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