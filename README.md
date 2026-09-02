# Omnipay: Chapa

**Chapa driver for the Omnipay PHP payment processing library**

[![Unit Tests](https://github.com/AmanuelLegese/omnipay-chapa/actions/workflows/run-tests.yml/badge.svg)](https://github.com/AmanuelLegese/omnipay-chapa/actions/workflows/run-tests.yml)

[Omnipay](https://github.com/thephpleague/omnipay) is a framework agnostic, multi-gateway
payment processing library for PHP. This package implements Chapa support for Omnipay.

[Chapa](https://chapa.co) is an Ethiopian payment gateway. It settles in ETB and supports
local payment methods such as telebirr, CBE Birr, M-Pesa and Amole alongside card payments.

## Status

**Not yet released to Packagist.**

Implemented and tested against mock fixtures: hosted checkout (`purchase`,
`completePurchase`), webhooks (`acceptNotification`), and payouts (`transfer`,
`fetchTransfer`, `fetchBanks`). Bulk transfers are not implemented.

The library has not been exercised against Chapa's live sandbox, so treat the first
integration as unverified against the real service. Chapa does not publish the full shape of
the transfer `data` object, so those accessors are the least certain part of the package.

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

## Webhooks

Point Chapa's `callback_url` at your endpoint and set the webhook secret from the Chapa
dashboard. It falls back to the API secret key if you don't set it separately.

```php
$gateway->setWebhookSecret('...');

try {
    $notification = $gateway->acceptNotification();
} catch (\Omnipay\Common\Exception\InvalidRequestException $e) {
    // Unsigned or forged. Do not process.
    http_response_code(400);
    return;
}

if ($notification->getTransactionStatus() === $notification::STATUS_COMPLETED) {
    // Settle the order for $notification->getTransactionReference().
}
```

`acceptNotification()` fails closed: it throws rather than returning an object when the
signature is missing or does not verify, so an unauthenticated event cannot be processed by
accident.

### A note on Chapa's two signature headers

Chapa sends both `x-chapa-signature` (an HMAC-SHA256 of the event payload) and
`Chapa-Signature` (an HMAC-SHA256 of your secret key, keyed by the secret key itself).

The second is a **constant** — it does not depend on the payload, so it is identical on every
webhook you receive. It shows the sender knew your secret at some point but gives no
integrity guarantee for the body, and anyone who observes it once can replay it against a
forged payload.

Chapa's documentation states either header is sufficient. This package deliberately does not
follow that: it requires `x-chapa-signature` and ignores `Chapa-Signature`. If Chapa ever
sends only the constant, those webhooks are rejected — you will see unprocessed events
rather than accepting forged ones.

## Payouts

Payouts fall outside Omnipay's `GatewayInterface`, so these are Chapa-specific methods on the
concrete gateway rather than part of the shared Omnipay contract.

```php
$banks = $gateway->fetchBanks()->send()->getBankCodes();   // ['Awash Bank' => '855', ...]

$response = $gateway->transfer([
    'amount'        => '100.00',
    'accountNumber' => '1000212659000',
    'bankCode'      => $banks['Awash Bank'],
    'accountName'   => 'Israel Goytom',
    'transactionId' => 'payout-0001',
])->send();

// Acceptance is not settlement; confirm separately.
$status = $gateway->fetchTransfer(['transactionId' => 'payout-0001'])->send();
```

A wrong `bank_code` sends money to a valid account at the wrong bank, so resolve codes
through `fetchBanks()` rather than hard-coding them.

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
