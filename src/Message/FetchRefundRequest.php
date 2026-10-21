<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Check how a refund is progressing.
 *
 * @link https://developer.chapa.co/refund
 */
class FetchRefundRequest extends AbstractRequest
{
    public function getRefundId(): ?string
    {
        return $this->getParameter('refundId');
    }

    /**
     * The ref_id returned by refund(), from RefundResponse::getRefundId().
     *
     * @return $this
     */
    public function setRefundId(?string $value): self
    {
        return $this->setParameter('refundId', $value);
    }

    public function getData(): array
    {
        $this->validate('secretKey', 'refundId');

        return [];
    }

    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    protected function getEndpoint(): string
    {
        return '/refund/' . rawurlencode((string) $this->getRefundId()) . '/verify';
    }

    protected function createResponse(array $data): ResponseInterface
    {
        return $this->response = new FetchRefundResponse($this, $data);
    }
}
