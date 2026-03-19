<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Exception;

class UtilitiesService
{
    public static function hexToRgb($hex) {
        // Elimina cualquier carácter no deseado del valor hexadecimal
        $hex = preg_replace('/[^a-f0-9]/i', '', $hex);

        // Verifica si el valor hexadecimal tiene 3 o 6 caracteres y ajusta si es necesario
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        // Convierte el valor hexadecimal a valores RGB
        $r = hexdec($hex[0] . $hex[1]);
        $g = hexdec($hex[2] . $hex[3]);
        $b = hexdec($hex[4] . $hex[5]);

        // Devuelve un arreglo con los valores RGB
        return array('r' => $r, 'g' => $g, 'b' => $b);
    }

    public static function getTinyUrl(string $longUrl): string
    {
        $apiUrl = 'https://tinyurl.com/api-create.php?url=' . urlencode($longUrl);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        $shortUrl = curl_exec($curl);
        curl_close($curl);

        return trim($shortUrl);
    }

    // User-Agent actualizado
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';

    /**
     * Valida y normaliza la URL.
     */
    private static function prepareUrl(string $url): string
    {
        $url = trim($url);

        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /**
     * Alternativa 1: is.gd
     */
    public static function shortenIsGd(string $longUrl): string
    {
        $longUrl = self::prepareUrl($longUrl);
        $apiUrl = 'https://is.gd/create.php?format=simple&url=' . urlencode($longUrl);

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10
        ]);

        $shortUrl = curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);

        if (!$error && $shortUrl && filter_var(trim($shortUrl), FILTER_VALIDATE_URL)) {
            return trim($shortUrl);
        }

        return $longUrl;
    }

    /**
     * Alternativa 2: CleanURI
     */
    public static function shortenCleanUri(string $longUrl): string
    {
        $longUrl = self::prepareUrl($longUrl);
        $apiUrl = 'https://cleanuri.com/api/v1/shorten';

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['url' => $longUrl]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);

        if (!$error && $response) {
            $json = json_decode($response, true);

            if (isset($json['result_url']) && filter_var($json['result_url'], FILTER_VALIDATE_URL)) {
                return $json['result_url'];
            }
        }

        return $longUrl;
    }

    /**
     * Alternativa 3: da.gd
     */
    public static function shortenDaGd(string $longUrl): string
    {
        $longUrl = self::prepareUrl($longUrl);
        $apiUrl = 'https://da.gd/s?url=' . urlencode($longUrl);

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10
        ]);

        $shortUrl = curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);

        if (!$error && $shortUrl && filter_var(trim($shortUrl), FILTER_VALIDATE_URL)) {
            return trim($shortUrl);
        }

        return $longUrl;
    }

    /**
     * TinyURL actualizado
     */
    public static function getUpdatedTinyUrl(string $longUrl): string
    {
        $longUrl = self::prepareUrl($longUrl);
        $apiUrl = 'https://tinyurl.com/api-create.php?url=' . urlencode($longUrl);

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10
        ]);

        $shortUrl = curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);

        if (!$error && $shortUrl && filter_var(trim($shortUrl), FILTER_VALIDATE_URL)) {
            return trim($shortUrl);
        }

        return $longUrl;
    }

    public static function shortenUrl(string $url): string
    {
        $url = self::prepareUrl($url);

        $services = [
            //[self::class, 'shortenIsGd'],
            [self::class, 'shortenDaGd'],
            //[self::class, 'shortenCleanUri'],
            //[self::class, 'getUpdatedTinyUrl'],
        ];

        foreach ($services as $service) {
            $short = call_user_func($service, $url);

            if ($short !== $url) {
                return $short;
            }
        }

        return $url;
    }

}
