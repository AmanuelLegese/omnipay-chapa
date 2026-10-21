<?php

namespace Omnipay\Chapa\Message;

/**
 * Response to completePurchase().
 *
 * Identical to FetchTransactionResponse - both come from the same verify
 * endpoint. Kept as its own class so `instanceof CompletePurchaseResponse`
 * keeps working for callers who already check it.
 */
class CompletePurchaseResponse extends FetchTransactionResponse
{
}
