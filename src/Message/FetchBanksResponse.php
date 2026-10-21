<?php

namespace Omnipay\Chapa\Message;

/**
 * Response to listing payout banks.
 *
 * Here `data` is a list of bank objects rather than a single object, so the
 * inherited getPaymentData() accessors do not apply.
 */
class FetchBanksResponse extends AbstractResponse
{
    /**
     * Unlike every other Chapa endpoint, a successful bank list carries no
     * `status` field at all - the real envelope is just {"message", "data"}.
     * Error responses do carry `"status": "failed"`, so an explicit status wins
     * and its absence is read as success only when a list of banks came back.
     */
    public function isSuccessful(): bool
    {
        $status = $this->getCode();

        if ($status !== null) {
            return $status === 'success';
        }

        return is_array($this->data['data'] ?? null);
    }

    /**
     * Every bank object Chapa returned, including ones that cannot receive
     * payouts. Each carries id, name, slug, currency, acct_length and the
     * can_process_payouts / can_process_payments flags.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBanks(): array
    {
        $banks = $this->data['data'] ?? null;

        if (!is_array($banks)) {
            return [];
        }

        return array_values(array_filter($banks, 'is_array'));
    }

    /**
     * Only the banks a transfer can actually be sent to.
     *
     * Chapa lists banks that accept payments but not payouts; offering one of
     * those in a payout form produces a transfer that is rejected later. A bank
     * without the flag is kept rather than guessed about.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPayoutBanks(): array
    {
        return array_values(array_filter(
            $this->getBanks(),
            static fn (array $bank): bool => !array_key_exists('can_process_payouts', $bank)
                || (bool) $bank['can_process_payouts']
        ));
    }

    /**
     * Payout-capable bank codes keyed by bank name, for building a payout form.
     *
     * @return array<string, string>
     */
    public function getBankCodes(): array
    {
        $codes = [];

        foreach ($this->getPayoutBanks() as $bank) {
            $name = $bank['name'] ?? null;
            $code = $bank['id'] ?? null;

            if (is_scalar($name) && is_scalar($code)) {
                $codes[(string) $name] = (string) $code;
            }
        }

        return $codes;
    }

    /**
     * The account number length a bank expects, so it can be checked before a
     * transfer is sent rather than rejected after. Null when unknown.
     */
    public function getAccountLength(string $bankCode): ?int
    {
        foreach ($this->getBanks() as $bank) {
            if ((string) ($bank['id'] ?? '') === $bankCode && is_numeric($bank['acct_length'] ?? null)) {
                return (int) $bank['acct_length'];
            }
        }

        return null;
    }
}
