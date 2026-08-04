# supersendtx/symfony-mailer

[Symfony Mailer](https://symfony.com/doc/current/mailer.html) transport for [SuperSend TX](https://supersendtx.com) transactional email (HTTP API).

## Install

```bash
composer require supersendtx/symfony-mailer
```

```bash
export SUPERSENDTX_API_KEY=stx_your_key_here
```

## Usage

```php
use SuperSendTX\Client;
use SuperSendTX\Symfony\Transport\SuperSendTXTransport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

$mailer = new Mailer(new SuperSendTXTransport(new Client(getenv('SUPERSENDTX_API_KEY'))));

$email = (new Email())
    ->from('noreply@yourdomain.com')
    ->to('user@example.com')
    ->subject('Welcome')
    ->html('<p>Welcome.</p>');

$mailer->send($email);
```

### DSN

```php
use SuperSendTX\Symfony\Transport\SuperSendTXTransportFactory;
use Symfony\Component\Mailer\Transport;

$transport = Transport::fromDsns(
    'supersendtx+api://%s@default',
    null,
    null,
    null,
    [new SuperSendTXTransportFactory()],
);
// Or: supersendtx://default?api_key=stx_…
```

In Symfony Framework, register `SuperSendTXTransportFactory` as a `mailer.transport_factory` service and set:

```yaml
# config/packages/mailer.yaml
framework:
  mailer:
    dsn: 'supersendtx+api://%env(SUPERSENDTX_API_KEY)%@default'
```

## Headers

| Header | Purpose |
|--------|---------|
| `X-SuperSendTX-Tag` | `name=value` tag (repeatable) |
| `X-SuperSendTX-Idempotency-Key` | Idempotency key |
| `X-SuperSendTX-Scheduled-At` | ISO 8601 schedule |

## Docs

https://docs.supersendtx.com/frameworks/symfony

## License

MIT
