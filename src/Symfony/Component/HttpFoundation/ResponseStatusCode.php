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

use Teapot\StatusCode\RFC\RFC2295;
use Teapot\StatusCode\RFC\RFC2324;
use Teapot\StatusCode\RFC\RFC2518;
use Teapot\StatusCode\RFC\RFC2774;
use Teapot\StatusCode\RFC\RFC2817;
use Teapot\StatusCode\RFC\RFC3229;
use Teapot\StatusCode\RFC\RFC3648;
use Teapot\StatusCode\RFC\RFC4918;
use Teapot\StatusCode\RFC\RFC5842;
use Teapot\StatusCode\RFC\RFC6585;
use Teapot\StatusCode\RFC\RFC7231;
use Teapot\StatusCode\RFC\RFC7232;
use Teapot\StatusCode\RFC\RFC7233;
use Teapot\StatusCode\RFC\RFC7235;
use Teapot\StatusCode\RFC\RFC7538;
use Teapot\StatusCode\RFC\RFC7540;
use Teapot\StatusCode\RFC\RFC7725;

interface ResponseStatusCode
{
    /**
     * @see RFC7231::CONTINUING
     */
    const HTTP_CONTINUE                                                  = RFC7231::CONTINUING;
    /**
     * @see RFC7231::SWITCHING_PROTOCOLS;
     */
    const HTTP_SWITCHING_PROTOCOLS                                       = RFC7231::SWITCHING_PROTOCOLS;
    /**
     * @see RFC2518::PROCESSING
     */
    const HTTP_PROCESSING                                                = RFC2518::PROCESSING;
    /**
     * @see RFC7231::OK
     */
    const HTTP_OK                                                        = RFC7231::OK;
    /**
     * @see RFC7231::CREATED
     */
    const HTTP_CREATED                                                   = RFC7231::CREATED;
    /**
     * @see RFC7231::ACCEPTED
     */
    const HTTP_ACCEPTED                                                  = RFC7231::ACCEPTED;
    /**
     * @see RFC7231::NON_AUTHORATIVE_INFORMATION
     */
    const HTTP_NON_AUTHORITATIVE_INFORMATION                             = RFC7231::NON_AUTHORATIVE_INFORMATION;
    /**
     * @see RFC7231::NO_CONTENT
     */
    const HTTP_NO_CONTENT                                                = RFC7231::NO_CONTENT;
    /**
     * @see RFC7231::RESET_CONTENT
     */
    const HTTP_RESET_CONTENT                                             = RFC7231::RESET_CONTENT;
    /**
     * @see RFC7233::PARTIAL_CONTENT
     */
    const HTTP_PARTIAL_CONTENT                                           = RFC7233::PARTIAL_CONTENT;
    /**
     * @see RFC4918::MULTI_STATUS
     */
    const HTTP_MULTI_STATUS                                              = RFC4918::MULTI_STATUS;
    /**
     * @see RFC5842::ALREADY_REPORTED
     */
    const HTTP_ALREADY_REPORTED                                          = RFC5842::ALREADY_REPORTED;
    /**
     * @see RFC3229::IM_USED
     */
    const HTTP_IM_USED                                                   = RFC3229::IM_USED;
    /**
     * @see RFC7231::MULTIPLE_CHOICES
     */
    const HTTP_MULTIPLE_CHOICES                                          = RFC7231::MULTIPLE_CHOICES;
    /**
     * @see RFC7231::MOVED_PERMANENTLY
     */
    const HTTP_MOVED_PERMANENTLY                                         = RFC7231::MOVED_PERMANENTLY;
    /**
     * @see RFC7231::FOUND
     */
    const HTTP_FOUND                                                     = RFC7231::FOUND;
    /**
     * @see RFC7231::SEE_OTHER
     */
    const HTTP_SEE_OTHER                                                 = RFC7231::SEE_OTHER;
    /**
     * @see RFC7232::NOT_MODIFIED
     */
    const HTTP_NOT_MODIFIED                                              = RFC7232::NOT_MODIFIED;
    /**
     * @see RFC7231::USE_PROXY
     */
    const HTTP_USE_PROXY                                                 = RFC7231::USE_PROXY;
    /**
     * @see RFC7231::UNUSED
     */
    const HTTP_RESERVED                                                  = RFC7231::UNUSED;
    /**
     * @see RFC7231::TEMPORARY_REDIRECT
     */
    const HTTP_TEMPORARY_REDIRECT                                        = RFC7231::TEMPORARY_REDIRECT;
    /**
     * @see RFC7538::PERMANENT_REDIRECT
     */
    const HTTP_PERMANENTLY_REDIRECT                                      = RFC7538::PERMANENT_REDIRECT;
    /**
     * @see RFC7231::BAD_REQUEST
     */
    const HTTP_BAD_REQUEST                                               = RFC7231::BAD_REQUEST;
    /**
     * @see RFC7235::UNAUTHORIZED
     */
    const HTTP_UNAUTHORIZED                                              = RFC7235::UNAUTHORIZED;
    /**
     * @see RFC7231::PAYMENT_REQUIRED;
     */
    const HTTP_PAYMENT_REQUIRED                                          = RFC7231::PAYMENT_REQUIRED;
    /**
     * @see RFC7231::FORBIDDEN
     */
    const HTTP_FORBIDDEN                                                 = RFC7231::FORBIDDEN;
    /**
     * @see RFC7231::NOT_FOUND
     */
    const HTTP_NOT_FOUND                                                 = RFC7231::NOT_FOUND;
    /**
     * @see RFC7231::METHOD_NOT_ALLOWED
     */
    const HTTP_METHOD_NOT_ALLOWED                                        = RFC7231::METHOD_NOT_ALLOWED;
    /**
     * @see RFC7231::NOT_ACCEPTABLE
     */
    const HTTP_NOT_ACCEPTABLE                                            = RFC7231::NOT_ACCEPTABLE;
    /**
     * @see RFC7235::PROXY_AUTHENTICATION_REQUIRED
     */
    const HTTP_PROXY_AUTHENTICATION_REQUIRED                             = RFC7235::PROXY_AUTHENTICATION_REQUIRED;
    /**
     * @see RFC7231::REQUEST_TIMEOUT
     */
    const HTTP_REQUEST_TIMEOUT                                           = RFC7231::REQUEST_TIMEOUT;
    /**
     * @see RFC7231::CONFLICT
     */
    const HTTP_CONFLICT                                                  = RFC7231::CONFLICT;
    /**
     * @see RFC7231::GONE
     */
    const HTTP_GONE                                                      = RFC7231::GONE;
    /**
     * @see RFC7231::LENGTH_REQUIRED
     */
    const HTTP_LENGTH_REQUIRED                                           = RFC7231::LENGTH_REQUIRED;
    /**
     * @see RFC7232::PRECONDITION_FAILED
     */
    const HTTP_PRECONDITION_FAILED                                       = RFC7232::PRECONDITION_FAILED;
    /**
     * @see RFC7231::PAYLOAD_TOO_LARGE
     */
    const HTTP_REQUEST_ENTITY_TOO_LARGE                                  = RFC7231::PAYLOAD_TOO_LARGE;
    /**
     * @see RFC7231::URI_TOO_LONG
     */
    const HTTP_REQUEST_URI_TOO_LONG                                      = RFC7231::URI_TOO_LONG;
    /**
     * @see RFC7231::UNSUPPORTED_MEDIA_TYPE
     */
    const HTTP_UNSUPPORTED_MEDIA_TYPE                                    = RFC7231::UNSUPPORTED_MEDIA_TYPE;
    /**
     * @see RFC7233::RANGE_NOT_SATISFIABLE
     */
    const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE                           = RFC7233::RANGE_NOT_SATISFIABLE;
    /**
     * @see RFC7231::EXPECTATION_FAILED
     */
    const HTTP_EXPECTATION_FAILED                                        = RFC7231::EXPECTATION_FAILED;
    /**
     * @see RFC2324::I_AM_A_TEAPOT
     */
    const HTTP_I_AM_A_TEAPOT                                             = RFC2324::I_AM_A_TEAPOT;
    /**
     * @see RFC7540::MISDIRECTED_REQUEST
     */
    const HTTP_MISDIRECTED_REQUEST                                       = RFC7540::MISDIRECTED_REQUEST;
    /**
     * @see RFC4918::UNPROCESSABLE_ENTITY
     */
    const HTTP_UNPROCESSABLE_ENTITY                                      = RFC4918::UNPROCESSABLE_ENTITY;
    /**
     * @see RFC4918::ENTITY_LOCKED
     */
    const HTTP_LOCKED                                                    = RFC4918::ENTITY_LOCKED;
    /**
     * @see RFC4918::FAILED_DEPENDENCY
     */
    const HTTP_FAILED_DEPENDENCY                                         = RFC4918::FAILED_DEPENDENCY;
    /**
     * @see RFC3648::UNORDERED_COLLECTION
     */
    const HTTP_RESERVED_FOR_WEBDAV_ADVANCED_COLLECTIONS_EXPIRED_PROPOSAL = RFC3648::UNORDERED_COLLECTION;
    /**
     * @see RFC2817::UPDATE_REQUIRED
     */
    const HTTP_UPGRADE_REQUIRED                                          = RFC2817::UPDATE_REQUIRED;
    /**
     * @see RFC6585::PRECONDITION_REQUIRED
     */
    const HTTP_PRECONDITION_REQUIRED                                     = RFC6585::PRECONDITION_REQUIRED;
    /**
     * @see RFC6585::TOO_MANY_REQUESTS
     */
    const HTTP_TOO_MANY_REQUESTS                                         = RFC6585::TOO_MANY_REQUESTS;
    /**
     * @see RFC6585::REQUEST_HEADER_FIELDS_TOO_LARGE
     */
    const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE                           = RFC6585::REQUEST_HEADER_FIELDS_TOO_LARGE;
    /**
     * @see RFC7725::UNAVAILABLE_FOR_LEGAL_REASONS
     */
    const HTTP_UNAVAILABLE_FOR_LEGAL_REASONS                             = RFC7725::UNAVAILABLE_FOR_LEGAL_REASONS;
    /**
     * @see RFC7231::INTERNAL_SERVER_ERROR
     */
    const HTTP_INTERNAL_SERVER_ERROR                                     = RFC7231::INTERNAL_SERVER_ERROR;
    /**
     * @see RFC7231::NOT_IMPLEMENTED
     */
    const HTTP_NOT_IMPLEMENTED                                           = RFC7231::NOT_IMPLEMENTED;
    /**
     * @see RFC7231::BAD_GATEWAY
     */
    const HTTP_BAD_GATEWAY                                               = RFC7231::BAD_GATEWAY;
    /**
     * @see RFC7231::SERVICE_UNAVAILABLE
     */
    const HTTP_SERVICE_UNAVAILABLE                                       = RFC7231::SERVICE_UNAVAILABLE;
    /**
     * @see RFC7231::GATEWAY_TIMEOUT
     */
    const HTTP_GATEWAY_TIMEOUT                                           = RFC7231::GATEWAY_TIMEOUT;
    /**
     * @see RFC7231::HTTP_VERSION_NOT_SUPPORTED
     */
    const HTTP_VERSION_NOT_SUPPORTED                                     = RFC7231::HTTP_VERSION_NOT_SUPPORTED;
    /**
     * @see RFC2295::VARIANT_ALSO_NEGOTIATES
     */
    const HTTP_VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL                      = RFC2295::VARIANT_ALSO_NEGOTIATES;
    /**
     * @see RFC4918::INSUFFICIENT_STORAGE
     */
    const HTTP_INSUFFICIENT_STORAGE                                      = RFC4918::INSUFFICIENT_STORAGE;
    /**
     * @see RFC5842::LOOP_DETECTED
     */
    const HTTP_LOOP_DETECTED                                             = RFC5842::LOOP_DETECTED;
    /**
     * @see RFC2774::NOT_EXTENDED
     */
    const HTTP_NOT_EXTENDED                                              = RFC2774::NOT_EXTENDED;
    /**
     * @see RFC6585::NETWORK_AUTHENTICATION_REQUIRED
     */
    const HTTP_NETWORK_AUTHENTICATION_REQUIRED                           = RFC6585::NETWORK_AUTHENTICATION_REQUIRED;
}
