<?php

declare(strict_types=1);

namespace SuperSendTX\Symfony\Tests;

use PHPUnit\Framework\TestCase;
use SuperSendTX\Symfony\Transport\SuperSendTXTransport;
use SuperSendTX\Symfony\Transport\SuperSendTXTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

final class SuperSendTXTransportFactoryTest extends TestCase
{
    public function testCreatesTransportFromApiScheme(): void
    {
        $factory = new SuperSendTXTransportFactory();
        $transport = $factory->create(Dsn::fromString('supersendtx+api://stx_test_key@default'));

        self::assertInstanceOf(SuperSendTXTransport::class, $transport);
        self::assertSame('supersendtx', (string) $transport);
    }

    public function testCreatesTransportFromQueryApiKey(): void
    {
        $factory = new SuperSendTXTransportFactory();
        $transport = $factory->create(Dsn::fromString('supersendtx://default?api_key=stx_query_key'));

        self::assertInstanceOf(SuperSendTXTransport::class, $transport);
    }

    public function testRequiresApiKey(): void
    {
        $prev = getenv('SUPERSENDTX_API_KEY');
        putenv('SUPERSENDTX_API_KEY');
        try {
            $factory = new SuperSendTXTransportFactory();
            $this->expectException(\InvalidArgumentException::class);
            $factory->create(Dsn::fromString('supersendtx://default'));
        } finally {
            if ($prev === false) {
                putenv('SUPERSENDTX_API_KEY');
            } else {
                putenv('SUPERSENDTX_API_KEY='.$prev);
            }
        }
    }
}
