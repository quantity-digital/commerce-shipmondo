<?php

/**
 * Main class for the Shipmondo plugin
 *
 * @package Shipmondo
 */

namespace QD\commerce\shipmondo\services;

use craft\base\Component;
use craft\commerce\elements\Order;
use craft\helpers\Json;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Webhooks extends Component
{
	/**
	 * Decodes JWT receiwed in Shipmondo webhook
	 *
	 * @param string $jwtSting
	 *
	 * @return array
	 */
	public function decodeWebhook(string $jwtSting): array
	{
		// $decoded = JWT::decode($jwtSting, new Key('123456', 'HS256'));

		$baseDecode = base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $jwtSting)[1])));
		// echo '<pre>';
		// print_r($baseDecode);
		// echo '</pre>';
		// die;
		$jsonDecode = Json::decode(Json::decode($baseDecode));

		// echo '<pre>';
		// print_r($jsonDecode);
		// echo '</pre>';
		// die;

		// $token = JWT::encode($jsonDecode, '123456', 'HS256');
		// echo '<pre>';
		// print_r($token);
		// echo '</pre>';
		// echo '<pre>';
		// print_r($jwtSting);
		// echo '</pre>';
		// die;

		if (isset($jsonDecode['action']) && $jsonDecode['action'] == 'connection_test') {
			return $jsonDecode;
		}

		$data = Json::decode($jsonDecode['data']);

		// echo '<pre>';
		// print_r($this->verify($jwtSting, '123456', 'HS256'));
		// echo '</pre>';
		// die;

		return $data;
	}

	// public function decode($jwsString)
	// {
	// 	$components = explode('.', $jwsString);
	// 	if (count($components) !== 3) {
	// 		throw new Exception('JWS string must contain 3 dot separated component.');
	// 	}

	// 	try {
	// 		$headers = Json::decode($this->base64Decode($components[0]));
	// 		$payload = Json::decode($this->base64Decode($components[1]));
	// 	} catch (\InvalidArgumentException $e) {
	// 		throw new Exception("Cannot decode signature headers and/or payload");
	// 	}

	// 	return [
	// 		'headers' => $headers,
	// 		'payload' => $payload
	// 	];
	// }

	// public function verify($jwsString, $key, $expectedAlgorithm = null)
	// {
	// 	$jws = $this->decode($jwsString);
	// 	$headers = $jws['headers'];

	// 	list($dataToSign, $signature) = $this->extractSignature($jwsString);

	// 	echo '<pre>';
	// 	print_r($dataToSign);
	// 	echo '</pre>';

	// 	echo '<pre>';
	// 	print_r($signature);
	// 	echo '</pre>';
	// 	die;

	// 	$basde64DecodedSignature = $this->base64Decode($signature);
	// 	$testSignature = hash_hmac('sha256', $dataToSign, $key, true);
	// 	if ($testSignature != $basde64DecodedSignature) {
	// 		throw new Exception("Invalid signature");
	// 	}

	// 	return $jws;
	// }

	// private function base64Decode($data)
	// {
	// 	return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
	// }

	// private function extractSignature($jwsString)
	// {
	// 	$p = strrpos($jwsString, '.');

	// 	return [substr($jwsString, 0, $p), substr($jwsString, $p + 1) ?: ''];
	// }
}
