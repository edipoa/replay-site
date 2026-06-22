<?php

declare(strict_types=1);

namespace App;

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class Mp
{
    public static function configure(): void
    {
        MercadoPagoConfig::setAccessToken($_ENV['MP_ACCESS_TOKEN']);
    }

    /**
     * Cria pagamento Pix no MP.
     * Retorna: ['mp_id', 'qr_code', 'qr_code_base64']
     */
    public static function createPix(float $amount, string $description, string $externalRef): array
    {
        self::configure();
        $client = new PaymentClient();
        try {
            $payment = $client->create([
                'transaction_amount' => $amount,
                'description'        => $description,
                'payment_method_id'  => 'pix',
                'payer'              => ['email' => $_ENV['MP_PAYER_EMAIL'] ?? 'pagamento@replay.com.br'],
                'external_reference' => $externalRef,
                'date_of_expiration' => (new \DateTimeImmutable())->modify('+30 minutes')->format('Y-m-d\TH:i:s.000O'),
            ]);
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            throw new \RuntimeException(json_encode($e->getApiResponse()->getContent()));
        }

        $txData = $payment->point_of_interaction->transaction_data;

        return [
            'mp_id'          => $payment->id,
            'qr_code'        => $txData->qr_code,
            'qr_code_base64' => $txData->qr_code_base64,
        ];
    }

    /**
     * Cria preferência Checkout Pro (cartão).
     * Retorna: ['preference_id', 'init_point']
     */
    public static function createPreference(array $data): array
    {
        self::configure();
        $client = new PreferenceClient();
        try {
            $pref = $client->create($data);
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            throw new \RuntimeException(json_encode($e->getApiResponse()->getContent()));
        }

        // Usa sandbox_init_point quando token TEST- ou APP_URL é localhost
        $isSandbox = str_starts_with($_ENV['MP_ACCESS_TOKEN'] ?? '', 'TEST-')
                  || str_contains($_ENV['APP_URL'] ?? '', 'localhost');
        $initPoint = ($isSandbox && !empty($pref->sandbox_init_point))
                   ? $pref->sandbox_init_point
                   : $pref->init_point;

        return [
            'preference_id' => $pref->id,
            'init_point'    => $initPoint,
        ];
    }

    /** Consulta status de um pagamento MP pelo ID numérico. */
    public static function getPaymentStatus(int $mpId): string
    {
        self::configure();
        $client  = new PaymentClient();
        $payment = $client->get($mpId);
        return $payment->status;
    }

    /** Retorna objeto completo de um pagamento (status + external_reference). */
    public static function getPayment(int $mpId): object
    {
        self::configure();
        $client = new PaymentClient();
        return $client->get($mpId);
    }
}
