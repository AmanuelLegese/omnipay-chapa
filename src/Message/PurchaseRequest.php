<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Initialize a Chapa transaction.
 *
 * Returns a hosted checkout URL to redirect the customer to; it never returns
 * a completed payment.
 *
 * @link https://developer.chapa.co/integrations/accept-payments
 */
class PurchaseRequest extends AbstractRequest
{
    /**
     * Chapa settles in Ethiopian birr; USD is the only other currency it takes.
     */
    public const DEFAULT_CURRENCY = 'ETB';

    public function getCustomizationTitle(): ?string
    {
        return $this->getParameter('customizationTitle');
    }

    /**
     * Store name shown on the hosted checkout page.
     *
     * @return $this
     */
    public function setCustomizationTitle(?string $value): self
    {
        return $this->setParameter('customizationTitle', $value);
    }

    public function getCustomizationDescription(): ?string
    {
        return $this->getParameter('customizationDescription') ?: $this->getDescription();
    }

    /**
     * Payment description shown on the hosted checkout page. Falls back to the
     * standard Omnipay `description` parameter when unset.
     *
     * @return $this
     */
    public function setCustomizationDescription(?string $value): self
    {
        return $this->setParameter('customizationDescription', $value);
    }

    public function getCustomizationLogo(): ?string
    {
        return $this->getParameter('customizationLogo');
    }

    /**
     * Logo shown on the hosted checkout page.
     *
     * @return $this
     */
    public function setCustomizationLogo(?string $value): self
    {
        return $this->setParameter('customizationLogo', $value);
    }

    public function getSubaccounts(): ?array
    {
        return $this->getParameter('subaccounts');
    }

    /**
     * Subaccounts to split this payment across. Each entry is either a bare
     * subaccount id or an array of `id` plus optional `split_type` and
     * `transaction_charge` overriding the subaccount's own defaults.
     *
     * @return $this
     */
    public function setSubaccounts(?array $value): self
    {
        return $this->setParameter('subaccounts', $value);
    }

    /**
     * Chapa takes subaccounts as a list of objects. A bare id string is the
     * easy mistake to make, so accept it rather than silently dropping a split.
     */
    private function normaliseSubaccounts(array $subaccounts): array
    {
        $normalised = [];

        foreach ($subaccounts as $subaccount) {
            if (is_string($subaccount) && $subaccount !== '') {
                $normalised[] = ['id' => $subaccount];
                continue;
            }

            if (is_array($subaccount) && ($subaccount['id'] ?? '') !== '') {
                $normalised[] = $subaccount;
            }
        }

        return $normalised;
    }

    public function getData(): array
    {
        $this->validate('secretKey', 'amount', 'transactionId');

        $data = [
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrency() ?: self::DEFAULT_CURRENCY,
            'tx_ref' => $this->getTransactionId(),
        ];

        if ($returnUrl = $this->getReturnUrl()) {
            $data['return_url'] = $returnUrl;
        }

        // Chapa calls callback_url server to server, which is what Omnipay
        // means by notifyUrl. returnUrl is the browser-facing one.
        if ($notifyUrl = $this->getNotifyUrl()) {
            $data['callback_url'] = $notifyUrl;
        }

        // The hosted page collects the card itself, so the card object is only
        // ever a carrier for customer identity here. Deliberately not calling
        // $card->validate(), which would demand a card number.
        if ($card = $this->getCard()) {
            $data += array_filter([
                'email' => $card->getEmail(),
                'first_name' => $card->getFirstName(),
                'last_name' => $card->getLastName(),
                'phone_number' => $card->getPhone(),
            ], static fn ($value): bool => $value !== null && $value !== '');
        }

        $customization = array_filter([
            'title' => $this->getCustomizationTitle(),
            'description' => $this->getCustomizationDescription(),
            'logo' => $this->getCustomizationLogo(),
        ], static fn ($value): bool => $value !== null && $value !== '');

        if ($customization !== []) {
            $data['customization'] = $customization;
        }

        // Chapa takes subaccounts at the root of the payload. The chapa-laravel
        // README documents `customization[subaccounts][id]`, but their nestjs
        // InitializeOptions interface and the split-payment docs both put it
        // here, and that README is wrong about several other things.
        $subaccounts = $this->normaliseSubaccounts($this->getSubaccounts() ?? []);

        if ($subaccounts !== []) {
            $data['subaccounts'] = $subaccounts;
        }

        return $data;
    }

    protected function getEndpoint(): string
    {
        return '/transaction/initialize';
    }

    protected function createResponse(array $data): ResponseInterface
    {
        return $this->response = new PurchaseResponse($this, $data);
    }
}
