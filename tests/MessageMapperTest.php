<?php

declare(strict_types=1);

namespace SuperSendTX\Symfony\Tests;

use PHPUnit\Framework\TestCase;
use SuperSendTX\Symfony\Transport\MessageMapper;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

final class MessageMapperTest extends TestCase
{
    public function testMapsBasicEmail(): void
    {
        $email = (new Email())
            ->from(new Address('ops@example.com', 'Ops'))
            ->to('user@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->replyTo('reply@example.com')
            ->subject('Receipt')
            ->html('<p>Thanks</p>')
            ->text('Thanks');

        $params = (new MessageMapper())->toSendParams($email);

        self::assertSame('Ops <ops@example.com>', $params['from']);
        self::assertSame('user@example.com', $params['to']);
        self::assertSame(['cc@example.com'], $params['cc']);
        self::assertSame(['bcc@example.com'], $params['bcc']);
        self::assertSame('reply@example.com', $params['reply_to']);
        self::assertSame('Receipt', $params['subject']);
        self::assertSame('<p>Thanks</p>', $params['html']);
        self::assertSame('Thanks', $params['text']);
    }

    public function testMapsAttachmentsTagsIdempotencyAndSchedule(): void
    {
        $email = (new Email())
            ->from('ops@example.com')
            ->to('user@example.com')
            ->subject('File')
            ->html('<p>Attached</p>')
            ->addPart(new DataPart('hello', 'note.txt', 'text/plain'));

        $email->getHeaders()->addTextHeader(MessageMapper::HEADER_TAG, 'campaign=welcome');
        $email->getHeaders()->addTextHeader(MessageMapper::HEADER_TAG, 'env=prod');
        $email->getHeaders()->addTextHeader(MessageMapper::HEADER_IDEMPOTENCY, 'idem-123');
        $email->getHeaders()->addTextHeader(MessageMapper::HEADER_SCHEDULED_AT, '2030-01-01T00:00:00Z');
        $email->getHeaders()->addTextHeader('X-Custom-Header', 'keep-me');

        $params = (new MessageMapper())->toSendParams($email);

        self::assertSame([
            [
                'filename' => 'note.txt',
                'content_type' => 'text/plain',
                'content' => base64_encode('hello'),
            ],
        ], $params['attachments']);
        self::assertSame([
            ['name' => 'campaign', 'value' => 'welcome'],
            ['name' => 'env', 'value' => 'prod'],
        ], $params['tags']);
        self::assertSame('idem-123', $params['idempotency_key']);
        self::assertSame('2030-01-01T00:00:00Z', $params['scheduled_at']);
        self::assertSame(['X-Custom-Header' => 'keep-me'], $params['headers']);
    }

    public function testRequiresFromToAndBody(): void
    {
        $mapper = new MessageMapper();

        $this->expectException(\InvalidArgumentException::class);
        $mapper->toSendParams((new Email())->to('user@example.com')->html('<p>x</p>'));
    }
}
