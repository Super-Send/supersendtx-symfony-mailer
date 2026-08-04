<?php

declare(strict_types=1);

namespace SuperSendTX\Symfony\Transport;

use SuperSendTX\Client;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * DSN schemes: supersendtx://default?api_key=stx_…  or  supersendtx+api://stx_…@default
 */
final class SuperSendTXTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (!\in_array($dsn->getScheme(), $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, 'supersendtx', $this->getSupportedSchemes());
        }

        $apiKey = $dsn->getUser() ?: $dsn->getOption('api_key');
        if (!\is_string($apiKey) || $apiKey === '') {
            $apiKey = getenv('SUPERSENDTX_API_KEY') ?: '';
        }
        if ($apiKey === '') {
            throw new \InvalidArgumentException(
                'SuperSend TX Mailer DSN requires an API key (userinfo, api_key query, or SUPERSENDTX_API_KEY).',
            );
        }

        $baseUrl = $dsn->getOption('base_url');
        if (!\is_string($baseUrl) || $baseUrl === '') {
            $baseUrl = 'https://api.supersendtx.com';
        }

        return new SuperSendTXTransport(new Client($apiKey, $baseUrl));
    }

    protected function getSupportedSchemes(): array
    {
        return ['supersendtx', 'supersendtx+api'];
    }
}
