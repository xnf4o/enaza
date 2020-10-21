<?php

namespace xnf4o\enaza;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Midnite81\Xml2Array\Xml2Array;

class enaza
{
    protected $client;
    protected $partnerId;
    protected $ps;
    private $api_url = 'https://files2.enazadev.ru/';
    private $site_url = 'https://partners.enazadev.ru/';

    public function __construct()
    {
        $this->client = new Client();
        $this->partnerId = config('enaza.partner_id');
        $this->secret = config('enaza.secret');
        $this->ps = 24;
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
            foreach ($xml as $key => $type) {
                if (in_array($key, $getting, true)) {
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
            if ($category_id) {
                foreach ($xml['products']['product'] as $product) {
                    if (isset($product['classes']['class']['@attributes']) && $product['classes']['class']['@attributes']['id'] === $category_id) {
                        $products->push($product);
                    }
                }
                $response->push($products->toArray());
            } else {
                $response->push($xml);
            }
        }
        if ($category_id) {
            return response()->json($this->paginateCollection(collect($response->first()), 20));
        }

        return response()->json($this->paginateCollection(collect($response[0]['products']['product']), 20));
    }

    /**
     * @param $collection
     * @param $perPage
     * @param string $pageName
     * @param null $fragment
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginateCollection($collection, $perPage, $pageName = 'page', $fragment = null): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage($pageName);
        $currentPageItems = $collection->slice(($currentPage - 1) * $perPage, $perPage);
        parse_str(request()->getQueryString(), $query);
        unset($query[$pageName]);
        $paginator = new LengthAwarePaginator(
            $currentPageItems,
            $collection->count(),
            $perPage,
            $currentPage,
            [
                'pageName' => $pageName,
                'path' => LengthAwarePaginator::resolveCurrentPath(),
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

    public function verify($game, $user_id, $ip, $sum, $comment = 'Оплата заказа')
    {
        $request_id = random_int(111111, 999999);
        $response = $this->client->request('POST', 'https://ps.enazadev.ru/payment/verify', [
            'query' => [
                'order_description' => $this->orderDescription($game, $user_id, $ip, $sum, $request_id, $comment),
            ],
        ]);

        return $response->getBody()->getContents();
    }

    /**
     * @param array|object $game Объект с игрой
     * @param int $user_id ID покупателя на нашей стороне
     * @param string $ip IP покупателя
     * @param float $sum Сумма заказа
     * @param string $comment Комментарий (необязательно)
     * @param $request_id
     * @return string
     */
    private function orderDescription(
        $game,
        int $user_id,
        string $ip,
        float $sum,
        $request_id,
        $comment = 'Оплата заказа'
    ): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<order comment="' . $comment . '"  currency="RUR" date="' . Carbon::now()->format('d-m-Y H:i:s') . '" ip="' . $ip . '" partner_id="' . $this->partnerId . '" request_id="' . $request_id . '"  sum="' . $sum . '" user_id="' . $user_id . '">';
        $xml .= '<signature>' . $this->signature('<order comment="' . $comment . '"  currency="RUR" date="' . Carbon::now()->format('d-m-Y H:i:s') . '" ip="' . $ip . '" partner_id="' . $this->partnerId . '" request_id="' . $request_id . '"  sum="' . $sum . '" user_id="' . $user_id . '">') . '</signature>';
        $xml .= '<return_url>string</return_url>';
        $xml .= '<items>';
        $xml .= '<item product_id="' . $game['@attributes']['id'] . '" cost="' . $game['@attributes']['price'] . '" sum="' . $game['@attributes']['price'] . '" count="1"/>';
        $xml .= '</items>';
        $xml .= '</order>';

        return base64_encode($xml);
    }

    /**
     * Генерация подписи
     *
     * @param $xml
     * @return string
     */
    private function signature($xml): string
    {
        $xml = $this->XMLtoArray($xml);
        $xmlName = array_key_first($xml);
        $attributes = [];
        foreach ($xml['order'] as $key => $value) {
            $attributes[$key] = $value;
        }
        ksort($attributes);

        return md5($xmlName . implode("", $attributes) . $this->secret);
    }

    /**
     * @param string $XML XML строка для парсинга
     * @return mixed
     */
    private function XMLtoArray($XML)
    {
        $xml_parser = xml_parser_create();
        xml_parse_into_struct($xml_parser, $XML, $vals);
        xml_parser_free($xml_parser);
        $_tmp = '';
        foreach ($vals as $xml_elem) {
            $x_tag = $xml_elem['tag'];
            $x_level = $xml_elem['level'];
            $x_type = $xml_elem['type'];
            if ($x_level != 1 && $x_type === 'close') {
                if (isset($multi_key[$x_tag][$x_level])) {
                    $multi_key[$x_tag][$x_level] = 1;
                } else {
                    $multi_key[$x_tag][$x_level] = 0;
                }
            }
            if ($x_level != 1 && $x_type === 'complete') {
                if ($_tmp == $x_tag) {
                    $multi_key[$x_tag][$x_level] = 1;
                }
                $_tmp = $x_tag;
            }
        }
        foreach ($vals as $xml_elem) {
            $x_tag = $xml_elem['tag'];
            $x_level = $xml_elem['level'];
            $x_type = $xml_elem['type'];
            if ($x_type === 'open') {
                $level[$x_level] = $x_tag;
            }
            $start_level = 1;
            $php_stmt = '$xml_array';
            if ($x_type === 'close' && $x_level != 1) {
                $multi_key[$x_tag][$x_level]++;
            }
            while ($start_level < $x_level) {
                $php_stmt .= '[$level[' . $start_level . ']]';
                if (isset($multi_key[$level[$start_level]][$start_level]) && $multi_key[$level[$start_level]][$start_level]) {
                    $php_stmt .= '[' . ($multi_key[$level[$start_level]][$start_level] - 1) . ']';
                }
                $start_level++;
            }
            $add = '';
            if (isset($multi_key[$x_tag][$x_level]) && $multi_key[$x_tag][$x_level] && ($x_type === 'open' || $x_type === 'complete')) {
                if (!isset($multi_key2[$x_tag][$x_level])) {
                    $multi_key2[$x_tag][$x_level] = 0;
                } else {
                    $multi_key2[$x_tag][$x_level]++;
                }
                $add = '[' . $multi_key2[$x_tag][$x_level] . ']';
            }
            if (isset($xml_elem['value']) && trim($xml_elem['value']) != '' && !array_key_exists('attributes', $xml_elem)) {
                if ($x_type === 'open') {
                    $php_stmt_main = $php_stmt . '[$x_type]' . $add . '[\'content\'] = $xml_elem[\'value\'];';
                } else {
                    $php_stmt_main = $php_stmt . '[$x_tag]' . $add . ' = $xml_elem[\'value\'];';
                }
                eval($php_stmt_main);
            }
            if (array_key_exists('attributes', $xml_elem)) {
                if (isset($xml_elem['value'])) {
                    $php_stmt_main = $php_stmt . '[$x_tag]' . $add . '[\'content\'] = $xml_elem[\'value\'];';
                    eval($php_stmt_main);
                }
                foreach ($xml_elem['attributes'] as $key => $value) {
                    $php_stmt_att = $php_stmt . '[$x_tag]' . $add . '[$key] = $value;';
                    eval($php_stmt_att);
                }
            }
        }
        return $this->array_change_key_case_recursive($xml_array);
    }

    /**
     * @param array $arr Массив для смены case ключей рекурсивно
     * @return array
     */
    private function array_change_key_case_recursive($arr)
    {
        return array_map(function ($item) {
            if (is_array($item)) {
                $item = $this->array_change_key_case_recursive($item);
            }
            return $item;
        }, array_change_key_case($arr));
    }

    /**
     * @param array|object $game Объект с игрой
     * @param int $user_id ID покупателя на нашей стороне
     * @param string $ip IP покупателя
     * @param float $sum Сумма заказа
     * @param string $comment Комментарий (необязательно)
     */
    function buy($game, $user_id, $ip, $sum, $comment = 'Оплата заказа')
    {
        $request_id = random_int(111111, 999999);
        $response = $this->client->request('POST', 'https://ps.enazadev.ru/payment/pay', [
            'query' => [
                'order_description' => $this->orderDescription($game, $user_id, $ip, $sum, $request_id, $comment),
            ],
        ]);

        return $response->getBody()->getContents();
    }

    /**
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getPaySystems()
    {
        $source = file_get_contents('https://ps.enazadev.ru/payment/getpaysystems/?partner_id=' . $this->partnerId);
        $xml = Xml2Array::create($source)->toCollection();
        return response()->json($xml);
    }
}
