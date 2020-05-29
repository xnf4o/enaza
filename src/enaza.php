<?php

namespace xnf4o\enaza;

use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Midnite81\Xml2Array\Xml2Array;

class enaza
{
    protected $client;
    protected $partnerId;
    private $api_url = 'https://files2.enazadev.ru/';
    private $site_url = 'https://partners.enazadev.ru/';

    public function __construct()
    {
        $this->client = new Client();
        $this->partnerId = config('enaza.partner_id');
    }

    /**
     * @return JsonResponse
     */
    public function getGenres(): JsonResponse
    {
        $response = collect();
        for ($i = 1; $i < 100; $i++) {
            if ($this->get_http_response_code($this->api_url . 'get_product_classes/' . $this->partnerId . '/1.' . $i . '.xml') !== "200") {
                break;
            }
            $source = file_get_contents($this->api_url . 'get_product_classes/' . $this->partnerId . '/1.' . $i . '.xml');
            $xml = Xml2Array::create($source)->toCollection();
            $response->push($xml);
        }
        return response()->json($response);
    }

    /**
     * Get response code
     *
     * @param $url
     * @return false|string
     */
    public function get_http_response_code($url)
    {
        $headers = get_headers($url);
        return substr($headers[0], 9, 3);
    }

    /**
     * @return JsonResponse
     */
    public function getProducts(): JsonResponse
    {
        $response = collect();
        for ($i = 1; $i < 100; $i++) {
            if ($this->get_http_response_code($this->api_url . 'get_products/' . $this->partnerId . '/1.' . $i . '.xml') !== "200") {
                break;
            }
            $source = file_get_contents($this->api_url . 'get_products/' . $this->partnerId . '/1.' . $i . '.xml');
            $xml = Xml2Array::create($source)->toCollection();
            $response->push($xml);
        }
        return response()->json($response);
    }

    /**
     * @return JsonResponse
     */
    public function getPeoples(): JsonResponse
    {
        $response = collect();
        for ($i = 1; $i < 100; $i++) {
            if ($this->get_http_response_code($this->api_url . 'get_peoples/' . $this->partnerId . '/1.' . $i . '.xml') !== "200") {
                break;
            }
            $source = file_get_contents($this->api_url . 'get_peoples/' . $this->partnerId . '/1.' . $i . '.xml');
            $xml = Xml2Array::create($source)->toCollection();
            $response->push($xml);
        }
        return response()->json($response);
    }
}
