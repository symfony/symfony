<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LinkedIn\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\LinkedIn\LinkedInOptions;
use Symfony\Component\Notifier\Bridge\LinkedIn\Share\AuthorShare;
use Symfony\Component\Notifier\Bridge\LinkedIn\Share\LifecycleStateShare;
use Symfony\Component\Notifier\Bridge\LinkedIn\Share\ShareContentShare;
use Symfony\Component\Notifier\Bridge\LinkedIn\Share\VisibilityShare;
use Symfony\Component\Notifier\Notification\Notification;

final class LinkedInOptionsTest extends TestCase
{
    public function testLinkedInOptions()
    {
        $options = new LinkedInOptions([
            'contentCertificationRecord' => 'test_record',
            'firstPublishedAt' => 1234567890,
        ]);

        $this->assertSame([
            'contentCertificationRecord' => 'test_record',
            'firstPublishedAt' => 1234567890,
        ], $options->toArray());

        $this->assertNull($options->getRecipientId());
    }

    public function testLinkedInOptionsWithAllMethods()
    {
        $author = new AuthorShare('123456');
        $lifecycleState = new LifecycleStateShare('PUBLISHED');
        $visibility = new VisibilityShare();
        $specificContent = new ShareContentShare('Test content');

        $options = (new LinkedInOptions())
            ->contentCertificationRecord('cert_record')
            ->firstPublishedAt(1234567890)
            ->lifecycleState($lifecycleState)
            ->origin('https://example.com')
            ->ugcOrigin('urn:li:origin:123')
            ->versionTag('v1.0')
            ->specificContent($specificContent)
            ->author($author)
            ->visibility($visibility);

        $expected = [
            'contentCertificationRecord' => 'cert_record',
            'firstPublishedAt' => 1234567890,
            'lifecycleState' => 'PUBLISHED',
            'origin' => 'https://example.com',
            'ugcOrigin' => 'urn:li:origin:123',
            'versionTag' => 'v1.0',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'attributes' => [],
                        'text' => 'Test content',
                    ],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'author' => 'urn:li:person:123456',
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $this->assertSame($expected, $options->toArray());
        $this->assertSame('urn:li:person:123456', $options->getAuthor());
    }

    public function testFromNotification()
    {
        $notification = new Notification('Test Subject');
        $notification->content('Test Content');

        $options = LinkedInOptions::fromNotification($notification);

        $expected = [
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'attributes' => [],
                        'text' => 'Test Content',
                    ],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
            'lifecycleState' => 'PUBLISHED',
        ];

        $this->assertSame($expected, $options->toArray());
    }

    public function testFromNotificationWithoutContent()
    {
        $notification = new Notification('Test Subject Only');

        $options = LinkedInOptions::fromNotification($notification);

        $expected = [
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'attributes' => [],
                        'text' => 'Test Subject Only',
                    ],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
            'lifecycleState' => 'PUBLISHED',
        ];

        $this->assertSame($expected, $options->toArray());
    }

    public function testOptionsChaining()
    {
        $options = new LinkedInOptions();

        $result = $options
            ->contentCertificationRecord('record')
            ->firstPublishedAt(1234567890);

        $this->assertSame($options, $result);
        $this->assertSame([
            'contentCertificationRecord' => 'record',
            'firstPublishedAt' => 1234567890,
        ], $options->toArray());
    }
}
