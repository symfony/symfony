<?php

declare(strict_types=1);

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Component\HttpKernel\KernelEvents;

class NoKernelResponseInvocationOnExceptionTest extends WebTestCase
{
    public function testContainerCompilationInDebug()
    {
        $client = $this->createClient(
            array('test_case' => 'NoKernelResponseInvocationOnException', 'root_config' => 'config.yml')
        );

        $dispatcher = $client->getContainer()->get('event_dispatcher');

        $propagated = false;
        $invocationCount = 0;
        $dispatcher->addListener(KernelEvents::RESPONSE, function () use (&$invocationCount) : void {
            $invocationCount++;
        });

        try {
            $client->request('GET', 'https://localhost/no_kernel_response_invocation');
        } catch (\RuntimeException $e) {
            $propagated = true;
        }

        $this->assertTrue($propagated, 'Exception should not be caught.');
        $this->assertSame(0, $invocationCount, 'Exception should not be converted into response.');
    }
}
