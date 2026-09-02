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
    public function isSuccessful(): bool
    {
        return $this->getCode() === 'success';
    }

    /**
     * The raw bank objects, each carrying at least id, name and code.
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
     * Bank codes keyed by bank name, for building a payout form.
     *
     * @return array<string, string>
     */
    public function getBankCodes(): array
    {
        $codes = [];

        foreach ($this->getBanks() as $bank) {
            $name = $bank['name'] ?? null;
            $code = $bank['id'] ?? null;

            if (is_scalar($name) && is_scalar($code)) {
                $codes[(string) $name] = (string) $code;
            }
        }

        return $codes;
    }
}
