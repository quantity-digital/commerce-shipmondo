<?php

/**
 * Main class for the Shipmondo plugin
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\services;

use craft\base\Component;
use craft\helpers\Json;
use Firebase\JWT\JWT;
use UnexpectedValueException;

class Webhooks extends Component
{
    /**
     * Decodes JWT receiwed in Shipmondo webhook
     * Is only used until Shipmondo fixes their webhook to include the payload in JSON format
     *
     * @param string $jwtSting
     *
     * @return object
     */
    public function decodeWebhook(string $jwtSting): object
    {
        $tks = \explode('.', $jwtSting);
        if (\count($tks) !== 3) {
            throw new UnexpectedValueException('Wrong number of segments');
        }
        list($headb64, $bodyb64, $cryptob64) = $tks;
        $headerRaw = JWT::urlsafeB64Decode($headb64);
        if (null === JWT::jsonDecode($headerRaw)) {
            throw new UnexpectedValueException('Invalid header encoding');
        }

        $payloadRaw = JWT::urlsafeB64Decode($bodyb64);
        $payload = JWT::jsonDecode($payloadRaw);

        //If payload is not in JSON format, try to decode it
        if (is_string($payload)) {
            $payload = Json::decode($payload);
        }

        // If payload is array, convert it to object
        if (is_array($payload)) {
            $payload = (object) $payload;
        }

        return $payload;
    }
}
