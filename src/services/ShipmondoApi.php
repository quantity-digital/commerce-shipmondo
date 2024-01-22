<?php

namespace QD\commerce\shipmondo\services;

use craft\base\Component;
use craft\helpers\App;
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

    public function cancelSalesOrder($shipmondoOrderId)
    {
        $params = [
            'order_status' => 'cancelled'
        ];

        return $this->updateSalesOrder($shipmondoOrderId, $params);
    }

    public function updateSalesOrder($shipmondoOrderId, $params)
    {
        $result = $this->_makeApiCall("/sales_orders/$shipmondoOrderId", 'PUT', $params);
        return $result;
    }

    public function createShipment($params)
    {
        $result = $this->_makeApiCall("/shipments", 'POST', $params);

        return $result;
    }

    public function getAllOrders($page = 1)
    {
        $result = $this->_makeApiCall("/sales_orders", 'GET', ['per_page' => 50, 'page' => $page, 'order_status' => 5]);
        return $result;
    }

    public function getSalesOrder($id)
    {
        $result = $this->_makeApiCall("/sales_orders/$id", 'GET');

        if (!isset($result['output'])) {
            return false;
        }
        return $result['output'];
    }

    public function getShipmentTemplates($params = [])
    {
        $this->result = $this->_makeApiCall("/shipment_templates", 'GET', $params);
        return $this;
    }

    public function getCarriers($params = [])
    {
        $this->result = $this->_makeApiCall("/carriers", 'GET', $params);
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
        //We are in dev-mode, so return dummydata instead of making an API call
        // if (App::devMode()) {
        //     return $this->dummyDroppoints();
        // }

        $this->result = $this->_makeApiCall('/pickup_points', 'GET', $params);
        return $this;
    }

    public function getOutput()
    {
        if (!isset($this->result['output'])) {
            exit('No output from API call');
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
            if (!isset($output['error'])) {
                throw new Exception(curl_exec($ch), $http_code);
            }
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

    private function dummyDroppoints()
    {
        $this->result = [
            'output' => [
                [
                    "number" => "95326",
                    "id" => "95326",
                    "company_name" => "Bog & Idé Esbjerg",
                    "name" => "Bog & Idé Esbjerg",
                    "address" => "Kongensgade 23 C",
                    "address2" => "Pakkeshop: 95326",
                    "zipcode" => "6700",
                    "city" => "Esbjerg",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.45294,
                    "latitude" => 55.466,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 10:00-17:30",
                        "Tir: 10:00-17:30",
                        "Ons: 10:00-17:30",
                        "Tor: 10:00-17:30",
                        "Fre: 10:00-18:00",
                        "Lør: 10:00-15:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "95373",
                    "id" => "95373",
                    "company_name" => "Spar Esbjerg",
                    "name" => "Spar Esbjerg",
                    "address" => "Bøndergårdsvej 1",
                    "address2" => "Pakkeshop: 95373",
                    "zipcode" => "6700",
                    "city" => "Esbjerg",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.48366,
                    "latitude" => 55.4703,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 08:00-21:00",
                        "Tir: 08:00-21:00",
                        "Ons: 08:00-21:00",
                        "Tor: 08:00-21:00",
                        "Fre: 08:00-21:00",
                        "Lør: 08:00-21:00",
                        "Søn: 08:00-21:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "95446",
                    "id" => "95446",
                    "company_name" => "Nærbutikken Gl. Vardevej",
                    "name" => "Nærbutikken Gl. Vardevej",
                    "address" => "Gl. Vardevej 19",
                    "address2" => "Pakkeshop: 95446",
                    "zipcode" => "6700",
                    "city" => "Esbjerg",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.43713,
                    "latitude" => 55.4756,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 08:00-20:00",
                        "Tir: 08:00-20:00",
                        "Ons: 08:00-20:00",
                        "Tor: 08:00-20:00",
                        "Fre: 08:00-20:00",
                        "Lør: 09:00-20:00",
                        "Søn: 09:00-20:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "96784",
                    "id" => "96784",
                    "company_name" => "Honningkrukken",
                    "name" => "Honningkrukken",
                    "address" => "Kongensgade 77",
                    "address2" => "Pakkeshop: 96784",
                    "zipcode" => "6700",
                    "city" => "Esbjerg",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.44566,
                    "latitude" => 55.467,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 11:00-21:00",
                        "Tir: 11:00-21:00",
                        "Ons: 11:00-21:00",
                        "Tor: 11:00-21:00",
                        "Fre: 11:00-21:00",
                        "Lør: 11:00-22:00",
                        "Søn: 11:00-21:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "96796",
                    "id" => "96796",
                    "company_name" => "Kiosk Skrænten",
                    "name" => "Kiosk Skrænten",
                    "address" => "Kirkegade 209",
                    "address2" => "Pakkeshop: 96796",
                    "zipcode" => "6700",
                    "city" => "Esbjerg",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.45486,
                    "latitude" => 55.4818,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 09:00-17:30",
                        "Tir: 09:00-17:30",
                        "Ons: 09:00-17:30",
                        "Tor: 09:00-17:30",
                        "Fre: 09:00-17:30",
                        "Lør: 09:00-18:00",
                        "Søn: 10:00-17:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "96798",
                    "id" => "96798",
                    "company_name" => "Shell 7-Eleven Esbjerg",
                    "name" => "Shell 7-Eleven Esbjerg",
                    "address" => "Stormgade 206",
                    "address2" => "Pakkeshop: 96798",
                    "zipcode" => "6700",
                    "city" => "Esbjerg",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.44902,
                    "latitude" => 55.4871,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 06:00-23:00",
                        "Tir: 06:00-23:00",
                        "Ons: 06:00-23:00",
                        "Tor: 06:00-23:00",
                        "Fre: 06:00-23:00",
                        "Lør: 07:00-23:00",
                        "Søn: 07:00-23:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "96867",
                    "id" => "96867",
                    "company_name" => "BI Marked Esbjerg",
                    "name" => "BI Marked Esbjerg",
                    "address" => "Torvegade 51",
                    "address2" => "Pakkeshop: 96867",
                    "zipcode" => "6700",
                    "city" => "Esbjerg",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.45368,
                    "latitude" => 55.4707,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 08:30-21:00",
                        "Tir: 08:30-21:00",
                        "Ons: 08:30-21:00",
                        "Tor: 08:30-21:00",
                        "Fre: 08:30-21:00",
                        "Lør: 08:30-21:00",
                        "Søn: 08:30-21:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "99833",
                    "id" => "99833",
                    "company_name" => "Kvickly Broen",
                    "name" => "Kvickly Broen",
                    "address" => "Exnersgade 18",
                    "address2" => "Pakkeshop: 99833",
                    "zipcode" => "6700",
                    "city" => "Esbjerg",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.45803,
                    "latitude" => 55.4654,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 08:00-20:00",
                        "Tir: 08:00-20:00",
                        "Ons: 08:00-20:00",
                        "Tor: 08:00-20:00",
                        "Fre: 08:00-20:00",
                        "Lør: 08:00-19:00",
                        "Søn: 08:00-19:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "99681",
                    "id" => "99681",
                    "company_name" => "Frejaparkens Kiosk",
                    "name" => "Frejaparkens Kiosk",
                    "address" => "Amagervej 2",
                    "address2" => "Pakkeshop: 99681",
                    "zipcode" => "6705",
                    "city" => "Esbjerg Ø",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.46783,
                    "latitude" => 55.4793,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 08:30-20:00",
                        "Tir: 08:30-20:00",
                        "Ons: 08:30-20:00",
                        "Tor: 08:30-20:00",
                        "Fre: 08:30-20:00",
                        "Lør: 09:00-20:00",
                        "Søn: 10:00-20:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "95504",
                    "id" => "95504",
                    "company_name" => "MP-Marked",
                    "name" => "MP-Marked",
                    "address" => "Kvaglundvej 47",
                    "address2" => "Pakkeshop: 95504",
                    "zipcode" => "6705",
                    "city" => "Esbjerg Ø",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.47329,
                    "latitude" => 55.4852,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 06:30-20:00",
                        "Tir: 06:30-20:00",
                        "Ons: 06:30-20:00",
                        "Tor: 06:30-20:00",
                        "Fre: 06:30-20:00",
                        "Lør: 08:00-20:00",
                        "Søn: 08:00-20:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "97074",
                    "id" => "97074",
                    "company_name" => "Silvan Esbjerg",
                    "name" => "Silvan Esbjerg",
                    "address" => "Østre Gjesingvej 10",
                    "address2" => "Pakkeshop: 97074",
                    "zipcode" => "6715",
                    "city" => "Esbjerg N",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.4551,
                    "latitude" => 55.5038,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 09:00-19:00",
                        "Tir: 09:00-19:00",
                        "Ons: 09:00-19:00",
                        "Tor: 09:00-19:00",
                        "Fre: 09:00-19:00",
                        "Lør: 09:00-17:00",
                        "Søn: 09:00-17:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "95283",
                    "id" => "95283",
                    "company_name" => "Min købmand-Søstjernen",
                    "name" => "Min købmand-Søstjernen",
                    "address" => "Norddalsvej 58",
                    "address2" => "Pakkeshop: 95283",
                    "zipcode" => "6710",
                    "city" => "Esbjerg V",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.42313,
                    "latitude" => 55.5015,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 07:30-20:00",
                        "Tir: 07:30-20:00",
                        "Ons: 07:30-20:00",
                        "Tor: 07:30-20:00",
                        "Fre: 07:30-20:00",
                        "Lør: 07:30-20:00",
                        "Søn: 07:30-20:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "99411",
                    "id" => "99411",
                    "company_name" => "Superbrugsen Esbjerg Storcenter",
                    "name" => "Superbrugsen Esbjerg Storcenter",
                    "address" => "Gammel Vardevej 230",
                    "address2" => "Pakkeshop: 99411",
                    "zipcode" => "6715",
                    "city" => "Esbjerg N",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.44966,
                    "latitude" => 55.5083,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 08:00-20:00",
                        "Tir: 08:00-20:00",
                        "Ons: 08:00-20:00",
                        "Tor: 08:00-20:00",
                        "Fre: 08:00-20:00",
                        "Lør: 08:00-20:00",
                        "Søn: 08:00-20:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "95713",
                    "id" => "95713",
                    "company_name" => "Q8 Esbjerg",
                    "name" => "Q8 Esbjerg",
                    "address" => "Storegade 225",
                    "address2" => "Pakkeshop: 95713",
                    "zipcode" => "6705",
                    "city" => "Esbjerg Ø",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.494,
                    "latitude" => 55.4786,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 00:00-24:00",
                        "Tir: 00:00-24:00",
                        "Ons: 00:00-24:00",
                        "Tor: 00:00-24:00",
                        "Fre: 00:00-24:00",
                        "Lør: 00:00-24:00",
                        "Søn: 00:00-24:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "97428",
                    "id" => "97428",
                    "company_name" => "OIL!-Butikken",
                    "name" => "OIL!-Butikken",
                    "address" => "Sædding Ringvej 6",
                    "address2" => "Pakkeshop: 97428",
                    "zipcode" => "6710",
                    "city" => "Esbjerg V",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.40742,
                    "latitude" => 55.5037,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 07:00-21:00",
                        "Tir: 07:00-21:00",
                        "Ons: 07:00-21:00",
                        "Tor: 07:00-21:00",
                        "Fre: 07:00-21:00",
                        "Lør: 07:00-21:00",
                        "Søn: 07:00-21:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "97127",
                    "id" => "97127",
                    "company_name" => "Fanø Boghandel",
                    "name" => "Fanø Boghandel",
                    "address" => "Hovedgaden 58",
                    "address2" => "Pakkeshop: 97127",
                    "zipcode" => "6720",
                    "city" => "Fanø",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.40449,
                    "latitude" => 55.4456,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 09:00-17:00",
                        "Tir: 09:00-17:00",
                        "Ons: 09:00-17:00",
                        "Tor: 09:00-17:00",
                        "Fre: 09:00-17:00",
                        "Lør: 09:00-13:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "95007",
                    "id" => "95007",
                    "company_name" => "Hjerting Kiosken",
                    "name" => "Hjerting Kiosken",
                    "address" => "Bytoften 26",
                    "address2" => "Pakkeshop: 95007",
                    "zipcode" => "6710",
                    "city" => "Esbjerg V",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.36108,
                    "latitude" => 55.5265,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 09:00-21:00",
                        "Tir: 09:00-21:00",
                        "Ons: 09:00-21:00",
                        "Tor: 09:00-21:00",
                        "Fre: 09:00-21:00",
                        "Lør: 09:00-21:00",
                        "Søn: 09:00-19:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "96175",
                    "id" => "96175",
                    "company_name" => "SuperBrugsen Tjæreborg",
                    "name" => "SuperBrugsen Tjæreborg",
                    "address" => "Skolevej 45",
                    "address2" => "Pakkeshop: 96175",
                    "zipcode" => "6731",
                    "city" => "Tjæreborg",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.58438,
                    "latitude" => 55.4632,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 08:00-20:00",
                        "Tir: 08:00-20:00",
                        "Ons: 08:00-20:00",
                        "Tor: 08:00-20:00",
                        "Fre: 08:00-20:00",
                        "Lør: 08:00-20:00",
                        "Søn: 08:00-20:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "96008",
                    "id" => "96008",
                    "company_name" => "Shell Korskro",
                    "name" => "Shell Korskro",
                    "address" => "Korskrovej 10",
                    "address2" => "Pakkeshop: 96008",
                    "zipcode" => "6705",
                    "city" => "Esbjerg Ø",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.59028,
                    "latitude" => 55.5249,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 05:00-22:00",
                        "Tir: 05:00-22:00",
                        "Ons: 05:00-22:00",
                        "Tor: 05:00-22:00",
                        "Fre: 05:00-22:00",
                        "Lør: 07:00-22:00",
                        "Søn: 07:00-22:00"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ],
                [
                    "number" => "97529",
                    "id" => "97529",
                    "company_name" => "Dagli´Brugsen Alslev",
                    "name" => "Dagli´Brugsen Alslev",
                    "address" => "Bredgade 32",
                    "address2" => "Pakkeshop: 97529",
                    "zipcode" => "6800",
                    "city" => "Varde",
                    "country" => "DK",
                    "distance" => null,
                    "longitude" => 8.41702,
                    "latitude" => 55.5884,
                    "agent" => "gls",
                    "carrier_code" => "gls",
                    "routing_code" => null,
                    "opening_hours" => [
                        "Man: 07:30-19:45",
                        "Tir: 07:30-19:45",
                        "Ons: 07:30-19:45",
                        "Tor: 07:30-19:45",
                        "Fre: 07:30-19:45",
                        "Lør: 07:30-19:45",
                        "Søn: 07:30-19:45"
                    ],
                    "in_delivery" => true,
                    "out_delivery" => true
                ]
            ]
        ];

        return $this;
    }
}
