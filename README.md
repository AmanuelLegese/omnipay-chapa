# Omnipay: Chapa

**Chapa driver for the Omnipay PHP payment processing library**

[![Unit Tests](https://github.com/AmanuelLegese/omnipay-chapa/actions/workflows/run-tests.yml/badge.svg)](https://github.com/AmanuelLegese/omnipay-chapa/actions/workflows/run-tests.yml)

[Omnipay](https://github.com/thephpleague/omnipay) is a framework agnostic, multi-gateway
payment processing library for PHP. This package implements Chapa support for Omnipay.

[Chapa](https://chapa.co) is an Ethiopian payment gateway. It settles in ETB and supports
local payment methods such as telebirr, CBE Birr, M-Pesa and Amole alongside card payments.

## Status

**Under development. Not yet released.**

The gateway classes are not implemented yet, so this package is not usable and has not been
published to Packagist. The usage section below describes the intended interface, not
working code.

## Installation

Once released, install via [Composer](https://getcomposer.org/):

```
composer require amanuellegese/omnipay-chapa
```

This package requires PHP 8.0+ and `omnipay/common` ^3.5. It does not depend on Chapa's
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
}
```

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
