<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Primotexto;

enum PrimotextoErrorCode: int
{
    case NO_PHONE_NUMBER_PROVIDED = 10;
    case INVALID_PHONE_NUMBER_SYNTAX = 11;
    case NUMBER_BLACKLISTED_UNSUBSCRIBED = 12;
    case NUMBER_BLACKLISTED_USER_BOUNCE = 13;
    case NUMBER_BLACKLISTED_GLOBAL_BOUNCE = 14;
    case NUMBER_ALREADY_EXISTS = 15;
    case NO_MESSAGE_FOR_THIS_QUERY = 16;
    case TOO_MANY_FIELDS_SELECTED = 17;
    case FILE_TOO_LARGE = 18;
    case PHONE_NUMBER_IS_FIXED_LINE = 19;
    case INVALID_CHARACTERS_IN_SENDER = 20;
    case SENDER_TOO_SHORT = 21;
    case SENDER_TOO_LONG = 22;
    case FULL_NUMERIC_SENDER_NOT_ALLOWED = 23;
    case NO_MESSAGE_CONTENT_PROVIDED = 30;
    case INVALID_CHARACTERS_IN_MESSAGE = 31;
    case MESSAGE_CONTENT_TOO_LONG = 32;
    case INVALID_CAMPAIGN_NAME = 40;
    case INVALID_CAMPAIGN_PROGRAMMING_TIME = 41;
    case CAMPAIGN_CANNOT_BE_DELETED = 42;
    case CAMPAIGN_CANNOT_BE_CANCELLED = 43;
    case FREE_MODE_CAMPAIGN_LIMIT_REACHED = 44;
    case INVALID_CAMPAIGN_TAG = 45;
    case TAG_ALREADY_EXISTS = 46;
    case CAMPAIGN_NOT_FOUND = 47;
    case INVALID_CAMPAIGN_SEND_LIST = 48;
    case LIST_NOT_FOUND = 60;
    case INVALID_DATE_FORMAT = 61;
    case INVALID_SCHEDULED_DATE = 62;
    case API_ACCESS_DISABLED = 70;
    case INSUFFICIENT_CREDITS = 71;
    case AUTHENTICATION_FAILED = 72;
    case INVALID_JSON = 73;
    case CONTACTS_POST_LIMIT_REACHED = 74;
    case IDENTIFIERS_COLUMN_NOT_FOUND = 75;
    case QUOTA_EXCEEDED = 76;
    case COUNTRY_NOT_SUPPORTED = 90;
    case INTERNATIONAL_MODE_NEEDED = 91;
    case BLOCKED_ACCOUNT = 92;
    case USER_NOT_FOUND = 93;
    case USER_INFO_NOT_RETRIEVABLE = 94;
    case UNABLE_TO_CREATE_ACCOUNT = 95;
    case AUTO_INVALID_CAMPAIGN = 120;
    case AUTO_INVALID_CONTACT = 121;
    case UNKNOWN_ERROR = 1000;
}
