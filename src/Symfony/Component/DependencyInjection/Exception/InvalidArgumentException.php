<?php

namespace Symfony\Component\DependencyInjection\Exception;

use \InvalidArgumentException as BaseInvalidArgumentException;

/**
 * InvalidArgumentException
 *
 * @package OpenSky Messaging
 * @version $Id$
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 * @copyright (c) 2010 OpenSky Project Inc
 * @license http://www.gnu.org/licenses/agpl.txt GNU Affero General Public License
 */
class InvalidArgumentException extends BaseInvalidArgumentException implements Exception
{
}