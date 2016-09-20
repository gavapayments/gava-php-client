<?php

namespace Gava;

use Gava\Exceptions\CheckoutCreationException;
use Gava\Exceptions\WebhookException;
use Requests;

class Gava
{
    /**
     * Base URL of Gava installation.
     *
     * @var string
     */
    private $apiUrl;

    /**
     * API secret key as set in Gava configs.
     *
     * @var string
     */
    private $secret;

    /**
     * Extended error messages store.
     *
     * @var string
     */
    private $error;

    /**
     * Class constructor.
     *
     * @param string $apiUrl Base URL of Gava installation
     * @param string $secret API secret key as set in Gava configs
     */
    public function __construct($apiUrl, $secret)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->secret = $secret;
    }

    /**
     * Creates a checkout on the $apiUrl and returns the checkout url.
     *
     * @param string $reference Checkout reference Id
     * @param float  $amount    Amount to be paid by customer
     * @param string $returnUrl Return URL
     * @param string $cancelUrl Cancel URL
     * @param int       (Optional)  Phone number from which payment will be/has been made
     * @param string    (Optional)  Transaction code for the payment the customer made
     * @param string    (Optional)  Set the payment method customer will use for the transaction
     *
     * @throws Gava\Exceptions\CheckoutCreationException
     *
     * @return string
     */
    public function createCheckout($reference, $amount, $returnUrl, $cancelUrl, $phone = null, $transactionCode = null, $method = null)
    {
        $payload = [
            'reference'  => $reference,
            'amount'     => $amount,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
        ];

        if ($phone) {
            $payload['phone'] = $phone;
        }
        if ($transactionCode) {
            $payload['transaction_code'] = $transactionCode;
        }
        if ($method) {
            $payload['payment_method'] = $method;
        }

        $payload['signature'] = $this->sign($payload);

        try {
            $response = Requests::post($this->apiUrl.'/create', [], $payload);
        } catch (\Exception $e) {
            $this->error = $response;
            throw new CheckoutCreationException('Request failed', 1);
        }

        if (!$response->success) {
            $this->error = $response;
            throw new CheckoutCreationException($response->body, 1);
        }

        return $response->body;
    }

    /**
     * Receives, validates and returns the checkout details
     * sent in via Gava's webhooks.
     *
     * @return object
     */
    public function processWebhook()
    {
        //Listen for callback, validate with server, close checkout
        $callback = json_decode(file_get_contents('php://input'));

        if (!$callback) {
            throw new WebhookException('Missing parameters', 1);
        }

        $expectedProperties = [
            'checkoutId',
            'checkoutHash',
            'reference',
            'paid',
            'amount',
            'phone',
            'transactionCode',
            'paymentMethod',
            'note',
            'signature',
        ];

        foreach ($expectedProperties as $property) {
            if (!property_exists($callback, $property)) {
                throw new WebhookException('Missing parameters', 1);
            }
        }

        if (!$this->signatureValid($callback)) {
            throw new WebhookException('Callback signature validation failed', 1);
        }

        if (!$checkout = $this->fetchCheckout($callback->checkoutHash)) {
            throw new WebhookException('Checkout fetch failed', 1);
        }

        //Defense: Gava doesn't yet have automated status changes from paid to not paid.
        //And Gava will not send a webhook notification for checkouts that have not been paid for
        if (!$checkout->paid) {
            throw new WebhookException('Checkout not paid', 1);
        }

        return $checkout;
    }

    /**
     * Fetches checkout with given hash.
     * A return of false generally means the checkout is not valid to us
     * Will exit with error for the other scenarios.
     *
     * @param string $hash Checkout hash
     *
     * @return object|false
     */
    private function fetchCheckout($hash)
    {
        //Get checkout, confirm signature
        $endpoint = $this->apiUrl.'/checkout/details/'.$hash;

        try {
            $response = Requests::get($endpoint);
        } catch (\Exception $e) {
            throw new WebhookException('Checkout lookup request failed', 1);
        }

        if (!$response->success) {
            return false;
        }

        if (!$checkout = json_decode($response->body)) {
            return false;
        }

        $expectedProperties = [
            'checkoutId',
            'checkoutHash',
            'reference',
            'paid',
            'amount',
            'phone',
            'transactionCode',
            'paymentMethod',
            'note',
            'signature',
        ];

        foreach ($expectedProperties as $property) {
            if (!property_exists($checkout, $property)) {
                return false;
            }
        }

        if (!$this->signatureValid($checkout)) {
            return false;
        }

        return $checkout;
    }

    /**
     * Given an iterable $payload, it signs it with the provided secret key.
     *
     * @param mixed $payload Object or array
     *
     * @return string
     */
    private function sign($payload)
    {
        $string = '';

        foreach ($payload as $key => $value) {
            if ($key === 'signature') {
                continue;
            }
            $string .= $value;
        }

        return hash('sha512', $string.$this->secret);
    }

    /**
     * Given an iterable $payload, it authenticates its signature property.
     *
     * @param mixed $request Object or array
     *
     * @return bool
     */
    private function signatureValid($request)
    {
        $string = '';

        foreach ($request as $key => $value) {
            if ($key === 'signature') {
                continue;
            }
            $string .= $value;
        }

        $signature = hash('sha512', $string.$this->secret);

        return $signature === $request->signature;
    }

    /**
     * Given a checkout URL, method will extract and return the checkout hash.
     *
     * @param string $url Checkout URL
     *
     * @return string
     */
    public function hashFromURL($url)
    {
        return str_replace(
            $this->apiUrl.'/checkout/',
            '',
            $url
        );
    }
}
