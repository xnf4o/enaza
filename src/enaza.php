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
        $genres = collect();
        $getting = ['product_classes'];
        for ($i = 1; $i < 100; $i++) {
            if ($this->get_http_response_code($this->api_url . 'get_product_classes/' . $this->partnerId . '/1.' . $i . '.xml') !== "200") {
                break;
            }
            $source = file_get_contents($this->api_url . 'get_product_classes/' . $this->partnerId . '/1.' . $i . '.xml');
            $xml = Xml2Array::create($source)->toCollection();
            foreach ($xml as $key => $type){
                if(in_array($key, $getting)) {
                    foreach ($xml[$key][key($type)] as $item) {
                        $genres->push($item['@attributes']);
                    }
                }
            }
        }
        return response()->json($genres);
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
     * @param $category_id
     * @return JsonResponse
     */
    public function getProducts($category_id): JsonResponse
    {
        $response = collect();
        $products = collect();
        for ($i = 1; $i < 100; $i++) {
            if ($this->get_http_response_code($this->api_url . 'get_products/' . $this->partnerId . '/1.' . $i . '.xml') !== "200") {
                break;
            }
            $source = file_get_contents($this->api_url . 'get_products/' . $this->partnerId . '/1.' . $i . '.xml');
            $xml = Xml2Array::create($source)->toCollection();
            if($category_id){
                foreach ($xml['products']['product'] as $product) {
                    if (isset($product['classes']['class']['@attributes']) && $product['classes']['class']['@attributes']['id'] === $category_id){
                        $products->push($product);
                    }
                }
                $response->push($products->toArray());
            } else {
                $response->push($xml);
            }
        }
        if($category_id){
            return response()->json($this->paginateCollection(collect($response->first()), 1));
        }else{
            return response()->json($this->paginateCollection(collect($response[0]['products']['product']), 1));
        }
    }

    /**
     * @param $collection
     * @param $perPage
     * @param string $pageName
     * @param null $fragment
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginateCollection($collection, $perPage, $pageName = 'page', $fragment = null)
    {
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage($pageName);
        $currentPageItems = $collection->slice(($currentPage - 1) * $perPage, $perPage);
        parse_str(request()->getQueryString(), $query);
        unset($query[$pageName]);
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPageItems,
            $collection->count(),
            $perPage,
            $currentPage,
            [
                'pageName' => $pageName,
                'path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath(),
                'query' => $query,
                'fragment' => $fragment
            ]
        );

        return $paginator;
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
