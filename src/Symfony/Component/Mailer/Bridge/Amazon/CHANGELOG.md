CHANGELOG
=========

4.4.0
-----

 * [BC BREAK] Renamed and moved `Symfony\Component\Mailer\Bridge\Amazon\Http\Api\SesTransport`
   to `Symfony\Component\Mailer\Bridge\Amazon\Transport\SesApiTransport`, `Symfony\Component\Mailer\Bridge\Amazon\Http\SesTransport`
   to `Symfony\Component\Mailer\Bridge\Amazon\Transport\SesHttpTransport`, `Symfony\Component\Mailer\Bridge\Amazon\Smtp\SesTransport`
   to `Symfony\Component\Mailer\Bridge\Amazon\Transport\SesSmtpTransport`.
 * [BC BREAK] changed `Symfony\Component\Mailer\Bridge\Amazon\Transport\SesApiTransport::__construct` username and password arguments to credential
 * [BC BREAK] changed `Symfony\Component\Mailer\Bridge\Amazon\Transport\SesHttpTransport::__construct` username and password arguments to credential
 * [BC BREAK] changed `Symfony\Component\Mailer\Bridge\Amazon\Transport\SesSmtpTransport::__construct` username and password arguments to credential
 * Added Instance Profile support

4.3.0
-----

 * Added the bridge
