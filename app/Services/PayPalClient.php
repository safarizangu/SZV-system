<?php
namespace App\Services;

/**
 * Minimal PayPal REST client for server-side order verification.
 * Uses OAuth2 client-credentials flow against PAYPAL_API_BASE
 * (sandbox or live, selected by PAYPAL_MODE in config/app.php).
 */
class PayPalClient
{
    /**
     * Fetch a bearer token via OAuth2 client-credentials grant.
     * Returns null on any failure (no secret configured, network error, bad credentials).
     */
    public static function getAccessToken(): ?string
    {
        if (!PAYPAL_CLIENT_ID || !PAYPAL_CLIENT_SECRET) {
            error_log('[PayPalClient] Missing PAYPAL_CLIENT_ID or PAYPAL_CLIENT_SECRET.');
            return null;
        }

        $ch = curl_init(PAYPAL_API_BASE . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: en_US'],
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status !== 200) {
            error_log("[PayPalClient] Token request failed (HTTP $status): $error");
            return null;
        }

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    /**
     * Fetch an order from PayPal and return its raw data, or null on failure.
     */
    public static function getOrder(string $orderId): ?array
    {
        $token = self::getAccessToken();
        if (!$token) return null;

        $ch = curl_init(PAYPAL_API_BASE . '/v2/checkout/orders/' . rawurlencode($orderId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status !== 200) {
            error_log("[PayPalClient] Order lookup failed (HTTP $status): $error");
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Verify that an order is COMPLETED and return the amount actually paid (in PAYPAL_CURRENCY),
     * or null if the order can't be verified, isn't completed, or is in the wrong currency.
     */
    public static function verifyCompletedOrder(string $orderId): ?float
    {
        $order = self::getOrder($orderId);
        if (!$order || ($order['status'] ?? null) !== 'COMPLETED') {
            return null;
        }

        $unit = $order['purchase_units'][0] ?? null;
        $amount = $unit['payments']['captures'][0]['amount']
            ?? $unit['amount']
            ?? null;

        if (!$amount || ($amount['currency_code'] ?? '') !== PAYPAL_CURRENCY) {
            return null;
        }

        return (float)$amount['value'];
    }
}
