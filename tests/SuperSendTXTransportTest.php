<?php

declare(strict_types=1);

namespace SuperSendTX\Symfony\Tests;

use PHPUnit\Framework\TestCase;
use SuperSendTX\Client;
use SuperSendTX\Symfony\Transport\SuperSendTXTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

final class SuperSendTXTransportTest extends TestCase
{
    public function testSendsViaClientAndSetsMessageId(): void
    {
        $lastRequest = null;

        $client = new Client(
            'stx_test_key',
            'https://api.example.com',
            function (string $method, string $path, ?array $body, array $headers) use (&$lastRequest): array {
                $lastRequest = compact('method', 'path', 'body', 'headers');

                return ['id' => 'msg_transport_1', 'status' => 'queued'];
            },
        );

        $transport = new SuperSendTXTransport($client);
        $email = (new Email())
            ->from('ops@example.com')
            ->to('user@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $sent = $transport->send($email, Envelope::create($email));

        self::assertInstanceOf(SentMessage::class, $sent);
        self::assertSame('msg_transport_1', $sent->getMessageId());
        self::assertSame('POST', $lastRequest['method']);
        self::assertSame('/emails', $lastRequest['path']);
        self::assertSame('ops@example.com', $lastRequest['body']['from'] ?? null);
        self::assertSame('user@example.com', $lastRequest['body']['to'] ?? null);
        self::assertSame('Hello', $lastRequest['body']['subject'] ?? null);
    }
}
