<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Provide advanced metadata for PhpStrom IDE.
 *
 * @see https://www.jetbrains.com/help/phpstorm/ide-advanced-metadata.html
 */
namespace PHPSTORM_META {
    // -- HTTP Methods.
    registerArgumentsSet('http_methods',
        \Symfony\Component\HttpFoundation\Request::METHOD_HEAD,
        \Symfony\Component\HttpFoundation\Request::METHOD_GET,
        \Symfony\Component\HttpFoundation\Request::METHOD_POST,
        \Symfony\Component\HttpFoundation\Request::METHOD_PUT,
        \Symfony\Component\HttpFoundation\Request::METHOD_PATCH,
        \Symfony\Component\HttpFoundation\Request::METHOD_DELETE,
        \Symfony\Component\HttpFoundation\Request::METHOD_PURGE,
        \Symfony\Component\HttpFoundation\Request::METHOD_OPTIONS,
        \Symfony\Component\HttpFoundation\Request::METHOD_TRACE,
        \Symfony\Component\HttpFoundation\Request::METHOD_CONNECT,
    );
    expectedArguments(\Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher::__construct(), 0, argumentsSet('http_methods'));
    expectedArguments(\Symfony\Component\HttpFoundation\Request::create(), 1, argumentsSet('http_methods'));
    expectedArguments(\Symfony\Component\HttpFoundation\Request::isMethod(), 0, argumentsSet('http_methods'));
    expectedArguments(\Symfony\Component\HttpFoundation\Request::setMethod(), 0, argumentsSet('http_methods'));
    expectedReturnValues(\Symfony\Component\HttpFoundation\Request::getMethod(), argumentsSet('http_methods'));
    expectedReturnValues(\Symfony\Component\HttpFoundation\Request::getRealMethod(), argumentsSet('http_methods'));

    // -- HTTP schemes.
    expectedReturnValues(
        \Symfony\Component\HttpFoundation\Request::getScheme(),
        'https',
        'http'
    );

    // -- Request formats.
    registerArgumentsSet('request_formats',
        'html',
        'txt',
        'js',
        'css',
        'json',
        'jsonld',
        'xml',
        'rdf',
        'atom',
        'rss',
        'form',
    );
    expectedArguments(\Symfony\Component\HttpFoundation\Request::getRequestFormat(), 0, argumentsSet('request_formats'));
    expectedReturnValues(\Symfony\Component\HttpFoundation\Request::getRequestFormat(), argumentsSet('request_formats'));
    expectedArguments(\Symfony\Component\HttpFoundation\Request::getPreferredFormat(), 0, argumentsSet('request_formats'));
    expectedReturnValues(\Symfony\Component\HttpFoundation\Request::getPreferredFormat(), argumentsSet('request_formats'));
    expectedReturnValues(\Symfony\Component\HttpFoundation\Request::getContentTypeFormat(), argumentsSet('request_formats'));
    expectedArguments(\Symfony\Component\HttpFoundation\Request::getMimeType(), 0, argumentsSet('request_formats'));
    expectedArguments(\Symfony\Component\HttpFoundation\Request::getMimeTypes(), 0, argumentsSet('request_formats'));

    // -- Mime types.
    // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
    registerArgumentsSet('mime_types',
        'audio/aac',
        'application/x-abiword',
        'application/x-freearc',
        'image/avif',
        'video/x-msvideo',
        'application/vnd.amazon.ebook',
        'application/octet-stream',
        'image/bmp',
        'application/x-bzip',
        'application/x-bzip2',
        'application/x-cdf',
        'application/x-csh',
        'text/css',
        'text/csv',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-fontobject',
        'application/epub+zip',
        'application/gzip',
        'image/gif',
        'text/html',
        'image/vnd.microsoft.icon',
        'text/calendar',
        'application/java-archive',
        'image/jpeg',
        'text/javascript',
        'application/json',
        'application/ld+json',
        'audio/midi',
        'audio/x-midi',
        'text/javascript',
        'audio/mpeg',
        'video/mp4',
        'video/mpeg',
        'application/vnd.apple.installer+xml',
        'application/vnd.oasis.opendocument.presentation',
        'application/vnd.oasis.opendocument.text',
        'audio/ogg',
        'video/ogg',
        'application/ogg',
        'audio/opus',
        'font/otf',
        'image/png',
        'application/pdf',
        'application/x-httpd-php',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.rar',
        'application/rtf',
        'application/x-sh',
        'image/tiff',
        'video/mp2t',
        'font/ttf',
        'text/plain',
        'application/vnd.visio',
        'audio/wav',
        'audio/webm',
        'video/webm',
        'image/webp',
        'font/woff',
        'font/woff2',
        'application/xhtml+xml',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/xml',
        'text/xml',
        'application/atom+xml',
        'application/xml',
        'application/vnd.mozilla.xul+xml',
        'application/zip',
        'video/3gpp',
        'audio/3gpp',
        'video/3gpp2',
        'audio/3gpp2',
        'application/x-7z-compressed',
    );
    expectedReturnValues(\Symfony\Component\HttpFoundation\Request::getMimeType(), argumentsSet('mime_types'));
    expectedReturnValues(\Symfony\Component\HttpFoundation\File\File::getMimeType(), argumentsSet('mime_types'));

    // -- Filter constants.
    // @see https://www.php.net/manual/en/filter.constants.php
    registerArgumentsSet('php_filters',
        \FILTER_FLAG_NONE,
        \FILTER_REQUIRE_SCALAR,
        \FILTER_REQUIRE_ARRAY,
        \FILTER_FORCE_ARRAY,
        \FILTER_NULL_ON_FAILURE,
        \FILTER_VALIDATE_INT,
        \FILTER_VALIDATE_BOOLEAN,
        \FILTER_VALIDATE_BOOL,
        \FILTER_VALIDATE_FLOAT,
        \FILTER_VALIDATE_REGEXP,
        \FILTER_VALIDATE_DOMAIN,
        \FILTER_VALIDATE_URL,
        \FILTER_VALIDATE_EMAIL,
        \FILTER_VALIDATE_IP,
        \FILTER_VALIDATE_MAC,
        \FILTER_DEFAULT,
        \FILTER_UNSAFE_RAW,
        \FILTER_SANITIZE_STRING,
        \FILTER_SANITIZE_STRIPPED,
        \FILTER_SANITIZE_ENCODED,
        \FILTER_SANITIZE_SPECIAL_CHARS,
        \FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        \FILTER_SANITIZE_EMAIL,
        \FILTER_SANITIZE_URL,
        \FILTER_SANITIZE_NUMBER_INT,
        \FILTER_SANITIZE_NUMBER_FLOAT,
        \FILTER_SANITIZE_ADD_SLASHES,
        \FILTER_CALLBACK,
        \FILTER_FLAG_ALLOW_OCTAL,
        \FILTER_FLAG_ALLOW_HEX,
        \FILTER_FLAG_STRIP_LOW,
        \FILTER_FLAG_STRIP_HIGH,
        \FILTER_FLAG_STRIP_BACKTICK,
        \FILTER_FLAG_ENCODE_LOW,
        \FILTER_FLAG_ENCODE_HIGH,
        \FILTER_FLAG_ENCODE_AMP,
        \FILTER_FLAG_NO_ENCODE_QUOTES,
        \FILTER_FLAG_EMPTY_STRING_NULL,
        \FILTER_FLAG_ALLOW_FRACTION,
        \FILTER_FLAG_ALLOW_THOUSAND,
        \FILTER_FLAG_ALLOW_SCIENTIFIC,
        \FILTER_FLAG_PATH_REQUIRED,
        \FILTER_FLAG_QUERY_REQUIRED,
        \FILTER_FLAG_IPV4,
        \FILTER_FLAG_IPV6,
        \FILTER_FLAG_NO_RES_RANGE,
        \FILTER_FLAG_NO_PRIV_RANGE,
        \FILTER_FLAG_HOSTNAME,
        \FILTER_FLAG_EMAIL_UNICODE,
    );
    expectedArguments(\Symfony\Component\HttpFoundation\InputBag::filter(), 2, argumentsSet('php_filters'));

    // -- Same site cookie.
    registerArgumentsSet('same_site_cookie',
        \Symfony\Component\HttpFoundation\Cookie::SAMESITE_NONE,
        \Symfony\Component\HttpFoundation\Cookie::SAMESITE_LAX,
        \Symfony\Component\HttpFoundation\Cookie::SAMESITE_STRICT,
    );
    expectedArguments(\Symfony\Component\HttpFoundation\Cookie::create(), 8, argumentsSet('same_site_cookie'));
    expectedArguments(\Symfony\Component\HttpFoundation\Cookie::__construct(), 8, argumentsSet('same_site_cookie'));
    expectedArguments(\Symfony\Component\HttpFoundation\Cookie::withSameSite(), 0, argumentsSet('same_site_cookie'));
    expectedReturnValues(\Symfony\Component\HttpFoundation\Cookie::getSameSite(), argumentsSet('same_site_cookie'));

    // -- HTTP headers.
    // @see https://en.wikipedia.org/wiki/List_of_HTTP_header_fields
    registerArgumentsSet('http_headers',
        // Standard request headers.
        'A-IM',
        'Accept',
        'Accept-Charset',
        'Accept-Datetime',
        'Accept-Encoding',
        'Accept-Language',
        'Access-Control-Request-Method',
        'Access-Control-Request-Headers',
        'Authorization',
        'Cache-Control',
        'Connection',
        'Content-Encoding',
        'Content-Length',
        'Content-MD5',
        'Content-Type',
        'Cookie',
        'Date',
        'Expect',
        'Forwarded',
        'From',
        'Host',
        'HTTP2-Settings',
        'If-Match',
        'If-Modified-Since',
        'If-None-Match',
        'If-Range',
        'If-Unmodified-Since',
        'Max-Forwards',
        'Origin',
        'Pragma',
        'Prefer',
        'Proxy-Authorization',
        'Range',
        'Referer',
        'TE',
        'Trailer',
        'Transfer-Encoding',
        'User-Agent',
        'Upgrade',
        'Via',
        'Warning',
        // Common non-standard request headers.
        'Upgrade-Insecure-Requests',
        'X-Requested-With',
        'DNT',
        'X-Forwarded-For',
        'X-Forwarded-Host',
        'X-Forwarded-Proto',
        'Front-End-Https',
        'X-Http-Method-Override',
        'X-ATT-DeviceId',
        'X-Wap-Profile',
        'Proxy-Connection',
        'X-UIDH',
        'X-Csrf-Token',
        'X-Request-ID',
        'X-Correlation-ID',
        'Correlation-ID',
        'Save-Data',
        // Standard response headers.
        'Accept-CH',
        'Access-Control-Allow-Origin',
        'Access-Control-Allow-Credentials',
        'Access-Control-Expose-Headers',
        'Access-Control-Max-Age',
        'Access-Control-Allow-Methods',
        'Access-Control-Allow-Headers',
        'Accept-Patch',
        'Accept-Ranges',
        'Age',
        'Allow',
        'Alt-Svc',
        'Cache-Control',
        'Connection',
        'Content-Disposition',
        'Content-Encoding',
        'Content-Language',
        'Content-Length',
        'Content-Location',
        'Content-MD5',
        'Content-Range',
        'Content-Type',
        'Date',
        'Delta-Base',
        'ETag',
        'Expires',
        'IM',
        'Last-Modified',
        'Link',
        'Location',
        'P3P',
        'Pragma',
        'Preference-Applied',
        'Proxy-Authenticate',
        'Public-Key-Pins',
        'Retry-After',
        'Server',
        'Set-Cookie',
        'Strict-Transport-Security',
        'Trailer',
        'Transfer-Encoding',
        'Tk',
        'Upgrade',
        'Vary',
        'Via',
        'Warning',
        'WWW-Authenticate',
        'X-Frame-Options',
        // Common non-standard response headers.
        'Content-Security-Policy',
        'X-Content-Security-Policy',
        'X-WebKit-CSP',
        'Expect-CT',
        'NEL',
        'Permissions-Policy',
        'Refresh',
        'Report-To',
        'Status',
        'Timing-Allow-Origin',
        'X-Content-Duration',
        'X-Content-Type-Options',
        'X-Powered-By',
        'X-Redirect-By',
        'X-Request-ID',
        'X-Correlation-ID',
        'X-UA-Compatible',
        'X-XSS-Protection',
    );
    expectedArguments(\Symfony\Component\HttpFoundation\HeaderBag::all(), 0, argumentsSet('http_headers'));
    expectedArguments(\Symfony\Component\HttpFoundation\HeaderBag::get(), 0, argumentsSet('http_headers'));
    expectedArguments(\Symfony\Component\HttpFoundation\HeaderBag::set(), 0, argumentsSet('http_headers'));
    expectedArguments(\Symfony\Component\HttpFoundation\HeaderBag::has(), 0, argumentsSet('http_headers'));
    expectedArguments(\Symfony\Component\HttpFoundation\HeaderBag::contains(), 0, argumentsSet('http_headers'));
    expectedArguments(\Symfony\Component\HttpFoundation\HeaderBag::remove(), 0, argumentsSet('http_headers'));
}
