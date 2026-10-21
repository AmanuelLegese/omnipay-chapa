# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-10-21

First release. Requires PHP 8.2+ and `omnipay/common` ^3.5.

Behaviour noted as confirmed below was checked against Chapa's test mode. Nothing
has yet been exercised with a live key.

### Added

- Hosted checkout: `purchase()` initializes a transaction and redirects to Chapa;
  `completePurchase()` verifies it server side, reading `trx_ref` or `tx_ref` off
  the return request. `mobileInitialize()` targets the mobile endpoint.
- `fetchTransaction()` verifies a transaction by `tx_ref` from anywhere. Pending is
  reported distinctly from failure.
- `refund()`, full or partial, and `fetchRefund()`. The refund path takes Chapa's
  `transactionReference`, not your `tx_ref` (confirmed). Set `refundReference` to
  make retries safe.
- `void()` cancels an unpaid checkout. Verify keeps reporting such a transaction as
  pending (confirmed), so the void response is the record of it.
- `acceptNotification()` verifies webhooks against `x-chapa-signature` and fails
  closed. `Chapa-Signature` is ignored: it is a constant and protects nothing.
- Notifications tell charge events from payout events (`isCharge()`, `isPayout()`),
  so a completed payout cannot pass for a paid order. Refunded and reversed charges
  are exposed through `isRefunded()` and `isReversed()`.
- Payouts: `transfer()`, `bulkTransfer()`, `fetchTransfer()` and `fetchBanks()`.
  `fetchBanks()` can filter to payout-capable banks and report each bank's account
  number length. Transfer references over 36 characters are rejected before sending
  (confirmed limit). `testStatus` forces a test-mode payout outcome.
- Split payments: `createSubaccount()`, and `subaccounts` on purchase.
- `meta` on purchase, echoed back on verify and in webhooks (confirmed). It must
  have named keys.

[Unreleased]: https://github.com/AmanuelLegese/omnipay-chapa/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/AmanuelLegese/omnipay-chapa/releases/tag/v0.1.0
