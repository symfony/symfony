CHANGELOG
=========

6.1
---

 * Allow multiple `TagHeaders` with `MailgunApiTransport`

5.2
---

 * Not prefixing headers with "h:" is no more deprecated

5.1.0
-----

 * Not prefixing headers with "h:" is deprecated.

4.4.0
-----

 * [BC BREAK] Renamed and moved `Symfony\Component\Mailer\Bridge\Mailgun\Http\Api\MailgunTransport`
   to `Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunApiTransport`, `Symfony\Component\Mailer\Bridge\Mailgun\Http\MailgunTransport`
   to `Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunHttpTransport`, `Symfony\Component\Mailer\Bridge\Mailgun\Smtp\MailgunTransport`
   to `Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunSmtpTransport`.

4.3.0
-----

 * Added the bridge
