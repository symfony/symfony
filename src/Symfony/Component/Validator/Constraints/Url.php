<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Url extends Constraint
{
    const INVALID_URL_ERROR = '57c2f299-1154-4870-89bb-ef3b1f5ad229';

    protected static $errorNames = array(
        self::INVALID_URL_ERROR => 'INVALID_URL_ERROR',
    );

    public $message = 'This value is not a valid URL.';
    public $dnsMessage = 'The host could not be resolved.';
    /**
     * @see http://www.iana.org/assignments/uri-schemes/uri-schemes.xhtml
     *
     * @var array
     */
    public $protocols = array('aaa', 'aaas', 'about', 'acap', 'acct', 'acr', 'adiumxtra', 'afp', 'afs', 'aim', 'apt', 'attachment', 'aw', 'barion', 'beshare', 'bitcoin', 'blob', 'bolo', 'callto', 'cap', 'chrome', 'chrome-extension', 'cid', 'coap', 'coaps', 'com-eventbrite-attendee', 'content', 'crid', 'cvs', 'data', 'dav', 'dict', 'dlna-playcontainer', 'dlna-playsingle', 'dns', 'dntp', 'dtn', 'dvb', 'ed2k', 'example', 'facetime', 'fax', 'feed', 'feedready', 'file', 'filesystem', 'finger', 'fish', 'ftp', 'geo', 'gg', 'git', 'gizmoproject', 'go', 'gopher', 'gtalk', 'h323', 'ham', 'hcp', 'http', 'https', 'iax', 'icap', 'icon', 'im', 'imap', 'info', 'iotdisco', 'ipn', 'ipp', 'ipps', 'irc', 'irc6', 'ircs', 'iris', 'iris.beep', 'iris.lwz', 'iris.xpc', 'iris.xpcs', 'itms', 'jabber', 'jar', 'jms', 'keyparc', 'lastfm', 'ldap', 'ldaps', 'magnet', 'mailserver', 'mailto', 'maps', 'market', 'message', 'mid', 'mms', 'modem', 'ms-help', 'ms-settings', 'ms-settings-airplanemode', 'ms-settings-bluetooth', 'ms-settings-camera', 'ms-settings-cellular', 'ms-settings-cloudstorage', 'ms-settings-emailandaccounts', 'ms-settings-language', 'ms-settings-location', 'ms-settings-lock', 'ms-settings-nfctransactions', 'ms-settings-notifications', 'ms-settings-power', 'ms-settings-privacy', 'ms-settings-proximity', 'ms-settings-screenrotation', 'ms-settings-wifi', 'ms-settings-workplace', 'msnim', 'msrp', 'msrps', 'mtqp', 'mumble', 'mupdate', 'mvn', 'news', 'nfs', 'ni', 'nih', 'nntp', 'notes', 'oid', 'opaquelocktoken', 'pack', 'palm', 'paparazzi', 'pkcs11', 'platform', 'pop', 'pres', 'prospero', 'proxy', 'psyc', 'query', 'redis', 'rediss', 'reload', 'res', 'resource', 'rmi', 'rsync', 'rtmfp', 'rtmp', 'rtsp', 'rtsps', 'rtspu', 'secondlife', 'service', 'session', 'sftp', 'sgn', 'shttp', 'sieve', 'sip', 'sips', 'skype', 'smb', 'sms', 'smtp', 'snews', 'snmp', 'soap.beep', 'soap.beeps', 'soldat', 'spotify', 'ssh', 'steam', 'stun', 'stuns', 'submit', 'svn', 'tag', 'teamspeak', 'tel', 'teliaeid', 'telnet', 'tftp', 'things', 'thismessage', 'tip', 'tn3270', 'turn', 'turns', 'tv', 'udp', 'unreal', 'urn', 'ut2004', 'vemmi', 'ventrilo', 'videotex', 'view-source', 'wais', 'webcal', 'ws', 'wss', 'wtai', 'wyciwyg', 'xcon', 'xcon-userid', 'xfire', 'xmlrpc\.beep', 'xmlrpc.beeps', 'xmpp', 'xri', 'ymsgr', 'z39\.50', 'z39\.50r', 'z39\.50s');
    public $checkDNS = false;
}
