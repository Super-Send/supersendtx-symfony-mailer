<?php

declare(strict_types=1);

namespace SuperSendTX\Symfony\Transport;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Maps a Symfony Email into SuperSend TX emails.send params.
 */
class MessageMapper
{
    public const HEADER_IDEMPOTENCY = 'X-SuperSendTX-Idempotency-Key';
    public const HEADER_SCHEDULED_AT = 'X-SuperSendTX-Scheduled-At';
    public const HEADER_TAG = 'X-SuperSendTX-Tag';

    /** Headers consumed by the transport and not forwarded as API headers. */
    private const RESERVED_HEADERS = [
        'from',
        'to',
        'cc',
        'bcc',
        'reply-to',
        'subject',
        'content-type',
        'mime-version',
        'date',
        'message-id',
        'x-supersendtx-idempotency-key',
        'x-supersendtx-scheduled-at',
        'x-supersendtx-tag',
        'idempotency-key',
    ];

    /**
     * @return array{
     *   from: string,
     *   to: list<string>|string,
     *   subject?: string,
     *   html?: string,
     *   text?: string,
     *   reply_to?: list<string>|string,
     *   cc?: list<string>,
     *   bcc?: list<string>,
     *   attachments?: list<array{filename: string, content_type: string, content: string}>,
     *   tags?: list<array{name: string, value: string}>,
     *   headers?: array<string, string>,
     *   scheduled_at?: string,
     *   idempotency_key?: string
     * }
     */
    public function toSendParams(Email $email): array
    {
        $from = $email->getFrom();
        if ($from === []) {
            throw new \InvalidArgumentException('Email is missing a From address.');
        }

        $to = $this->formatAddresses($email->getTo());
        if ($to === []) {
            throw new \InvalidArgumentException('Email is missing a To recipient.');
        }

        $params = [
            'from' => $this->formatAddress($from[0]),
            'to' => count($to) === 1 ? $to[0] : $to,
        ];

        $subject = $email->getSubject();
        if ($subject !== null && $subject !== '') {
            $params['subject'] = $subject;
        }

        $html = $email->getHtmlBody();
        if (is_string($html) && $html !== '') {
            $params['html'] = $html;
        }

        $text = $email->getTextBody();
        if (is_string($text) && $text !== '') {
            $params['text'] = $text;
        }

        if (!isset($params['html']) && !isset($params['text'])) {
            throw new \InvalidArgumentException('Email must include html or text content.');
        }

        $replyTo = $this->formatAddresses($email->getReplyTo());
        if ($replyTo !== []) {
            $params['reply_to'] = count($replyTo) === 1 ? $replyTo[0] : $replyTo;
        }

        $cc = $this->formatAddresses($email->getCc());
        if ($cc !== []) {
            $params['cc'] = $cc;
        }

        $bcc = $this->formatAddresses($email->getBcc());
        if ($bcc !== []) {
            $params['bcc'] = $bcc;
        }

        $attachments = $this->mapAttachments($email);
        if ($attachments !== []) {
            $params['attachments'] = $attachments;
        }

        $tags = $this->extractTags($email);
        if ($tags !== []) {
            $params['tags'] = $tags;
        }

        $headers = $this->extractForwardHeaders($email);
        if ($headers !== []) {
            $params['headers'] = $headers;
        }

        $idempotency = $this->headerValue($email, self::HEADER_IDEMPOTENCY)
            ?? $this->headerValue($email, 'Idempotency-Key');
        if ($idempotency !== null) {
            $params['idempotency_key'] = $idempotency;
        }

        $scheduledAt = $this->headerValue($email, self::HEADER_SCHEDULED_AT);
        if ($scheduledAt !== null) {
            $params['scheduled_at'] = $scheduledAt;
        }

        return $params;
    }

    private function formatAddress(Address $address): string
    {
        $email = $address->getAddress();
        $name = trim($address->getName());

        return $name !== '' ? sprintf('%s <%s>', $name, $email) : $email;
    }

    /**
     * @param list<Address> $addresses
     *
     * @return list<string>
     */
    private function formatAddresses(array $addresses): array
    {
        return array_values(array_map($this->formatAddress(...), $addresses));
    }

    /**
     * @return list<array{filename: string, content_type: string, content: string}>
     */
    private function mapAttachments(Email $email): array
    {
        $out = [];
        foreach ($email->getAttachments() as $part) {
            if (!$part instanceof DataPart) {
                continue;
            }

            $filename = $part->getFilename() ?: 'attachment';
            $contentType = $part->getContentType() ?: 'application/octet-stream';
            $raw = $part->getBody();
            $body = is_string($raw) ? $raw : $part->bodyToString();

            $out[] = [
                'filename' => $filename,
                'content_type' => $contentType,
                'content' => base64_encode($body),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private function extractTags(Email $email): array
    {
        $headers = $email->getHeaders();
        if (!$headers->has(self::HEADER_TAG)) {
            return [];
        }

        $tags = [];
        foreach ($headers->all(self::HEADER_TAG) as $header) {
            $raw = trim($header->getBodyAsString());
            if ($raw === '') {
                continue;
            }
            if (str_contains($raw, '=')) {
                [$name, $value] = explode('=', $raw, 2);
                $name = trim($name);
                $value = trim($value);
                if ($name !== '' && $value !== '') {
                    $tags[] = ['name' => $name, 'value' => $value];
                }
                continue;
            }
            $tags[] = ['name' => 'tag', 'value' => $raw];
        }

        return $tags;
    }

    /**
     * @return array<string, string>
     */
    private function extractForwardHeaders(Email $email): array
    {
        $out = [];
        foreach ($email->getHeaders()->all() as $header) {
            $name = strtolower($header->getName());
            if (in_array($name, self::RESERVED_HEADERS, true)) {
                continue;
            }
            if (str_starts_with($name, 'content-')) {
                continue;
            }
            $out[$header->getName()] = $header->getBodyAsString();
        }

        return $out;
    }

    private function headerValue(Email $email, string $name): ?string
    {
        $headers = $email->getHeaders();
        if (!$headers->has($name)) {
            return null;
        }

        $value = trim($headers->get($name)->getBodyAsString());

        return $value !== '' ? $value : null;
    }
}
