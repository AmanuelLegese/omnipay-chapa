# Omnipay: Chapa

**Chapa driver for the Omnipay PHP payment processing library**

[![Unit Tests](https://github.com/AmanuelLegese/omnipay-chapa/actions/workflows/run-tests.yml/badge.svg)](https://github.com/AmanuelLegese/omnipay-chapa/actions/workflows/run-tests.yml)

[Omnipay](https://github.com/thephpleague/omnipay) is a framework agnostic, multi-gateway
payment processing library for PHP. This package implements Chapa support for Omnipay.

[Chapa](https://chapa.co) is an Ethiopian payment gateway. It settles in ETB and supports
local payment methods such as telebirr, CBE Birr, M-Pesa and Amole alongside card payments.

## Status

**Not yet released to Packagist.**

Hosted checkout works: `purchase` and `completePurchase` are implemented and tested against
mock fixtures. Webhook handling (`acceptNotification`) and Chapa's transfer/payout API are
not implemented yet.

The library has not been exercised against Chapa's live sandbox, so treat the first
integration as unverified against the real service.

## Installation

Once released, install via [Composer](https://getcomposer.org/):

```
composer require amanuellegese/omnipay-chapa
```

This package requires PHP 8.2+ and `omnipay/common` ^3.5. It does not depend on Chapa's
official PHP SDK; it talks to the HTTP API directly, as Omnipay drivers conventionally do.

## Usage

The gateway is registered as `Chapa`. For general Omnipay usage, see the
[Omnipay documentation](https://github.com/thephpleague/omnipay).

```php
use Omnipay\Omnipay;

$gateway = Omnipay::create('Chapa');
$gateway->setSecretKey('CHASECK_TEST-...');

$response = $gateway->purchase([
    'amount'        => '100.00',
    'currency'      => 'ETB',
    'transactionId' => 'order-1234',
    'returnUrl'     => 'https://example.com/return',
    'notifyUrl'     => 'https://example.com/webhook',
])->send();

if ($response->isRedirect()) {
    // Send the customer to Chapa's hosted checkout.
    $response->redirect();
}
```

On return, verify the transaction rather than trusting the redirect:

```php
$response = $gateway->completePurchase([
    'transactionId' => 'order-1234',
])->send();

if ($response->isSuccessful()) {
    // Payment confirmed.
} elseif ($response->isPending()) {
    // Customer has not finished paying. Worth retrying, not a failure.
} else {
    echo $response->getMessage();
}
```

`transactionId` is Chapa's `tx_ref` — you choose it, and it is what you verify against later.
`returnUrl` is where the browser lands; `notifyUrl` maps to Chapa's `callback_url`, the
server-to-server callback. `currency` defaults to `ETB`.

To brand the hosted checkout page, pass `customizationTitle` and `customizationDescription`
to `purchase()`. The description falls back to the standard Omnipay `description` parameter.

## Development

PHP and Composer run in Docker, so nothing needs to be installed on the host. A single
image provides both, which keeps Composer resolving dependencies against the same PHP
version that later runs them.

```
make install     # composer install
make test        # phpunit
make style       # phpcs, PSR2
make fix         # phpcbf, PSR2
make shell       # interactive shell in the container
```

`make` is a thin wrapper; Compose works directly too:

```
docker compose run --rm test
```

To reproduce a CI matrix cell, pick the PHP version:

```
PHP_VERSION=8.1 docker compose build
PHP_VERSION=8.1 docker compose run --rm test
```

If your user id is not 1000, put `UID` and `GID` in a `.env` file so `vendor/` is not
written as root.

## Support

If you believe you have found a bug, please report it using the
[GitHub issue tracker](https://github.com/AmanuelLegese/omnipay-chapa/issues).

## License

MIT. See [LICENSE](LICENSE).
