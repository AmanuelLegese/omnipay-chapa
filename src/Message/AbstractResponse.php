<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Message\AbstractResponse as OmnipayAbstractResponse;

/**
 * Shared reading of Chapa's response envelope:
 *
 *     {"status": "success|failed", "message": ..., "data": {...}|null}
 */
abstract class AbstractResponse extends OmnipayAbstractResponse
{
    /**
     * The envelope status, which reports whether the API call itself worked -
     * not whether the payment succeeded. Use isSuccessful() for that.
     */
    public function getCode(): ?string
    {
        $status = $this->data['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    /**
     * Chapa sends `message` as a string for most errors, but as an object of
     * per-field errors when validation fails, e.g.
     * {"message": {"amount": ["The amount field is required."]}}.
     * Flatten that so callers always get a string.
     */
    public function getMessage(): ?string
    {
        $message = $this->data['message'] ?? null;

        if (is_string($message)) {
            return $message !== '' ? $message : null;
        }

        if (is_array($message)) {
            // The field name is the outer key and the messages sit in a list
            // beneath it, so walk one level at a time to keep them paired.
            $parts = [];
            foreach ($message as $field => $errors) {
                foreach ((array) $errors as $error) {
                    if (!is_scalar($error)) {
                        continue;
                    }
                    $parts[] = is_string($field) ? $field . ': ' . $error : (string) $error;
                }
            }

            return $parts === [] ? null : implode(', ', $parts);
        }

        return null;
    }

    /**
     * The `data` object, or an empty array when Chapa sent null - which it
     * does on every error response.
     */
    protected function getPaymentData(): array
    {
        $data = $this->data['data'] ?? null;

        return is_array($data) ? $data : [];
    }

    protected function getPaymentValue(string $key): ?string
    {
        $value = $this->getPaymentData()[$key] ?? null;

        if ($value === null || is_array($value) || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
