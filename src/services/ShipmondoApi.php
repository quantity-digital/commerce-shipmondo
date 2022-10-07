<?php

namespace QD\commerce\shipmondo\services;

use craft\base\Component;
use Exception;
use QD\commerce\shipmondo\Shipmondo;

class ShipmondoApi extends Component
{
	const API_ENDPOINT = 'https://app.shipmondo.com/api/public/v3';
	const VERSION = '3.4.0';

	private $result;

	private $_api_user;
	private $_api_key;
	private $_api_base_path;

	public function __construct()
	{
		$settings = Shipmondo::$plugin->getSettings();
		$this->_api_user = $settings->apiUser;
		$this->_api_key = $settings->apiKey;
		$this->_api_base_path = self::API_ENDPOINT;
	}

	public function createSalesOrder($params)
	{
		$result = $this->_makeApiCall("/sales_orders", 'POST', $params);
		return $result;
	}

	public function getSalesOrder($id)
	{
		$result = $this->_makeApiCall("/sales_orders/$id", 'GET');
		return $result;
	}

	public function getShipmentTemplates($params = [])
	{
		$this->result = $this->_makeApiCall("/shipment_templates", 'GET', $params);
		return $this;
	}

	public function getTemplate($templateId)
	{
		$this->result = $this->_makeApiCall("/shipment_templates/" . $templateId, 'GET');
		return $this;
	}

	public function getProduct($productCode)
	{
		$this->result = $this->_makeApiCall('/products', 'GET', ['product_code' => $productCode]);
		return $this;
	}

	public function getServicePoints($params)
	{
		$this->result = $this->_makeApiCall('/pickup_points', 'GET', $params);
		return $this;
	}

	public function getOutput()
	{
		if (!isset($this->result['output'])) {
			exit('test');
		}
		return $this->result['output'];
	}

	private function _makeApiCall($path, $method = 'GET', $params = [])
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERPWD, $this->_api_user . ":" . $this->_api_key);
		$params['user_agent'] = 'smd_php_library v' . self::VERSION;

		switch ($method) {
			case 'GET':
				$query = http_build_query($params);
				curl_setopt($ch, CURLOPT_URL, $this->_api_base_path . '/' . $path . '?' . $query);
				break;
			case 'POST':
				$query = json_encode($params);
				curl_setopt($ch, CURLOPT_URL, $this->_api_base_path . '/' . $path);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					'Content-Type: application/json',
					'Content-Length: ' . strlen($query)
				]);
				break;
			case 'PUT':
				$query = json_encode($params);
				curl_setopt($ch, CURLOPT_URL, $this->_api_base_path . '/' . $path);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
				curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					'Content-Type: application/json',
					'Content-Length: ' . strlen($query)
				]);
				break;
			case 'DELETE':
				$query = http_build_query($params);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				curl_setopt($ch, CURLOPT_URL, $this->_api_base_path . '/' . $path . '?' . $query);
				break;
		}

		$headers = [];
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// this function is called by curl for each header received
		curl_setopt(
			$ch,
			CURLOPT_HEADERFUNCTION,
			function ($curl, $header) use (&$headers) {
				$len = strlen($header);
				$header = explode(':', $header, 2);
				if (count($header) < 2) // ignore invalid headers
					return $len;

				$name = strtolower(trim($header[0]));
				if (!array_key_exists($name, $headers))
					$headers[$name] = [trim($header[1])];
				else
					$headers[$name][] = trim($header[1]);

				return $len;
			}
		);

		$output = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$output = json_decode($output, true);

		if ($http_code != 200) {
			throw new Exception($output['error'], $http_code);
		}

		$pagination = $this->_extractPagination($headers);

		$output = [
			'output' => $output,
			'pagination' => $pagination
		];

		return $output;
	}

	private function _extractPagination($headers)
	{
		$arr = ['x-per-page', 'x-current-page', 'x-total-count', 'x-total-pages'];
		$pagination = [];
		foreach ($arr as &$key) {
			if (array_key_exists($key, $headers))
				$pagination[$key] = $headers[$key][0];
			else
				return $pagination;
		}

		return $pagination;
	}
}
