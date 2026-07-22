<?php

namespace App\Services;

class PaymentService
{
    /**
     * Processes a payment transaction.
     *
     * @param float $amount The amount to charge.
     * @param string $token A payment token from the client (mock format requiring 32 chars).
     * @return bool True if payment succeeds, false otherwise.
     */
    public function processPayment(float $amount, string $token): bool
    {
        if ($amount <= 0) {
            return false; // Invalid amount
        }

        // Mock logic: Check token format (32 chars for this mock).
        if (!preg_match('/^[a-zA-Z0-9]{32}$/', $token)) {
            return false; // Invalid token format
        }

        // Simulate successful API call and processing.
        echo "Payment of ${} processed successfully using token ending in " . substr($token, -4) . "\n";
        return true;
    }

    /**
     * Refunds a payment amount given a transaction ID.
     */
    public function refundPayment(string $transactionId, float $amount): bool
    {
        if (empty($transactionId)) {
            return false;
        }
        echo "Attempting to refund ${} for transaction ID: {}\n";
        return true; // Simplified success path
    }
}
