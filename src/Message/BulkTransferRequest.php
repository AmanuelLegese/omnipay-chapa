<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Send many payouts in one call.
 *
 * @link https://developer.chapa.co/transfer/bulk-transfers
 */
class BulkTransferRequest extends AbstractRequest
{
    /**
     * Every field Chapa requires on each row. A row missing one of these is a
     * misdirected payout, so they are checked before the batch is sent rather
     * than discovered afterwards.
     */
    private const REQUIRED_ROW_KEYS = [
        'account_name',
        'account_number',
        'amount',
        'reference',
        'bank_code',
    ];

    public function getTitle(): ?string
    {
        return $this->getParameter('title');
    }

    /**
     * @return $this
     */
    public function setTitle(?string $value): self
    {
        return $this->setParameter('title', $value);
    }

    public function getBulkData(): ?array
    {
        return $this->getParameter('bulkData');
    }

    /**
     * The payout rows, each with account_name, account_number, amount,
     * reference and bank_code.
     *
     * @return $this
     */
    public function setBulkData(?array $value): self
    {
        return $this->setParameter('bulkData', $value);
    }

    public function getData(): array
    {
        $this->validate('secretKey', 'title', 'bulkData');

        $rows = $this->getBulkData() ?? [];

        if ($rows === []) {
            throw new InvalidRequestException('The bulkData parameter must not be empty');
        }

        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                throw new InvalidRequestException(
                    sprintf('bulkData row %d must be an array', $index)
                );
            }

            foreach (self::REQUIRED_ROW_KEYS as $key) {
                if (($row[$key] ?? '') === '') {
                    throw new InvalidRequestException(
                        sprintf('bulkData row %d is missing the %s field', $index, $key)
                    );
                }
            }
        }

        return [
            'title' => $this->getTitle(),
            'currency' => $this->getCurrency() ?: PurchaseRequest::DEFAULT_CURRENCY,
            'bulk_data' => array_values($rows),
        ];
    }

    protected function getEndpoint(): string
    {
        return '/bulk-transfers';
    }

    protected function createResponse(array $data): ResponseInterface
    {
        return $this->response = new BulkTransferResponse($this, $data);
    }
}
