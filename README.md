# Omnipay: Chapa

**Chapa driver for the Omnipay PHP payment processing library**

[![Unit Tests](https://github.com/AmanuelLegese/omnipay-chapa/actions/workflows/run-tests.yml/badge.svg)](https://github.com/AmanuelLegese/omnipay-chapa/actions/workflows/run-tests.yml)

[Omnipay](https://github.com/thephpleague/omnipay) is a framework agnostic, multi-gateway
payment processing library for PHP. This package implements Chapa support for Omnipay.

[Chapa](https://chapa.co) is an Ethiopian payment gateway. It settles in ETB and supports
local payment methods such as telebirr, CBE Birr, M-Pesa and Amole alongside card payments.

## Status

Pre-1.0: minor versions may still change the API. See [CHANGELOG.md](CHANGELOG.md).

Implemented and tested against mock fixtures: hosted checkout (`purchase`,
`completePurchase`, `mobileInitialize`, `fetchTransaction`), refunds (`refund`,
`fetchRefund`), cancellation (`void`), webhooks (`acceptNotification`), payouts
(`transfer`, `fetchTransfer`, `bulkTransfer`, `fetchBanks`) and split payments
(`createSubaccount`). This covers everything Chapa's official Laravel package exposes.

Where the code says behaviour was confirmed against the API, that means Chapa's test mode.
Nothing has been exercised with a live key, so treat the first live integration as
unverified. Chapa does not publish the full shape of the transfer `data` object, so those
accessors are the least certain part of the package.

## Installation

Install via [Composer](https://getcomposer.org/):

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
If you don't pass it to `completePurchase()`, it is read from the incoming request, since
Chapa appends the reference when it sends the customer back. An explicit value always wins.

`returnUrl` is where the browser lands; `notifyUrl` maps to Chapa's `callback_url`.
`currency` defaults to `ETB`.

To brand the hosted checkout page, pass `customizationTitle`, `customizationDescription` and
`customizationLogo` to `purchase()`. The description falls back to the standard Omnipay
`description` parameter.

`mobileInitialize()` takes the same parameters as `purchase()` against Chapa's mobile
endpoint.

## callback_url is not the webhook

These are two different mechanisms and it is easy to conflate them:

| | `callback_url` (`notifyUrl`) | Dashboard webhook |
|---|---|---|
| Method | `GET` | `POST` |
| Carries | `trx_ref`, `ref_id`, `status` as query params | JSON body |
| Signed | **No** | Yes, `x-chapa-signature` |
| Handle with | `completePurchase()` | `acceptNotification()` |

Pointing `notifyUrl` at the endpoint you call `acceptNotification()` on will fail every time
with `Missing x-chapa-signature header`, because the callback carries no signature.

The callback is **unauthenticated** — anyone can hit that URL with any query string. Treat it
only as a nudge to go and verify:

```php
// callback_url handler - nothing here is trusted
$response = $gateway->completePurchase()->send();   // reads trx_ref from the request

if ($response->isSuccessful()) {
    // Now you know.
}
```

## Webhooks

Register the webhook URL in the Chapa dashboard — **not** as `callback_url`, per the section
above — and set the webhook secret from there too. It falls back to the API secret key if you
don't set it separately.

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

Many payouts at once:

```php
$gateway->bulkTransfer([
    'title'    => 'January payouts',
    'bulkData' => [
        [
            'account_name'   => 'Israel Goytom',
            'account_number' => '1000212659000',
            'amount'         => 100,
            'reference'      => 'payout-0001',
            'bank_code'      => 946,
        ],
    ],
])->send();
```

Every row must carry all five fields; an incomplete one is rejected before the batch is sent.
A successful response means the batch was queued, not that rows settled — confirm each with
`fetchTransfer()` on its reference.

## Split payments

Create a subaccount, then reference it when charging:

```php
$id = $gateway->createSubaccount([
    'businessName'  => 'Example Vendor',
    'accountName'   => 'Israel Goytom',
    'accountNumber' => '1000212659000',
    'bankCode'      => '946',
    'splitType'     => 'percentage',   // or 'flat'
    'splitValue'    => 0.03,           // 3%, or a flat amount when splitType is 'flat'
])->send()->getSubaccountId();

$gateway->purchase([
    'amount'        => '100.00',
    'transactionId' => 'order-1234',
    'subaccounts'   => [$id],   // or ['id' => $id, 'split_type' => 'flat', 'transaction_charge' => 25]
])->send();
```

> **The subaccount endpoint is unverified.** Chapa gives three different answers for it: their
> split-payment docs and their NestJS SDK say `POST /v1/subaccount`, their own Laravel package
> posts to `/sub-accounts`, and its README says `GET https://api.chapa.dev/v1/transaction/sub-accounts`.
> This package defaults to `/subaccount` on the strength of two sources against one. If your
> account behaves differently, override it without forking:
> `$request->setEndpointPath('/sub-accounts')`.

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
PHP_VERSION=8.2 docker compose build
PHP_VERSION=8.2 docker compose run --rm test
```

If your user id is not 1000, put `UID` and `GID` in a `.env` file so `vendor/` is not
written as root.

### Releasing

1. Move the `[Unreleased]` entries in [CHANGELOG.md](CHANGELOG.md) under a new version
   heading, e.g. `## [0.2.0] - 2026-11-01`, update the compare links at the bottom, and
   merge that to `master`.
2. Tag the merge commit and push the tag:

   ```
   git tag -a v0.2.0 -m v0.2.0
   git push origin v0.2.0
   ```

The release workflow runs the full test matrix, checks the tag is on `master`, and
publishes a GitHub Release whose notes are that version's changelog section. It fails
if the section is missing. A tag with a suffix such as `v0.2.0-rc.1` is marked as a
pre-release. Packagist picks up the tag on its own.

## Support

If you believe you have found a bug, please report it using the
[GitHub issue tracker](https://github.com/AmanuelLegese/omnipay-chapa/issues).

## License

MIT. See [LICENSE](LICENSE).
