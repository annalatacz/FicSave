<?php
/**
 * Created by PhpStorm.
 * User: Joel
 * Date: 2016-06-15
 * Time: 6:28 PM
 */

namespace App;


use DirectoryIterator;
use Mail;
use WebSocket\Client;

class Helper
{
    public static function mailAttachment($downloadId, $fileName, $path, $email) {
        $rename_explode = explode("{$downloadId}_", $fileName);
        $rename = $rename_explode[1];
        $file = $path.DIRECTORY_SEPARATOR.$fileName;
        $from = 'delivery@ficsave.xyz';
        $emailParts = explode('@', $email);
        if (count($emailParts) != 2) return false;
        $emailDomain = $emailParts[1];
        switch ($emailDomain) {
            case 'free.kindle.com':
            case 'kindle.com':
                $from = $email;
                break;
        }
        if (!file_exists($file)) return false; // TODO: find what causes this
        Mail::raw("Here's your ebook, courtesy of FicSave.com!\r\nFollow us on Twitter @FicSave and tell your friends about us!", function ($message) use ($file, $rename, $email, $from) {
            $message->from($from, 'FicSave');
            if (strpos($email, 'kindle') === false) {
                $message->to($email)->subject("[FicSave] " . $rename);
            } else {
                $message->to($email)->subject("convert");
            }
            $message->attach($file, ['as' => $rename, 'mime' => 'application/octet-stream']);
        });
        return count(Mail::failures()) == 0;
    }

    public static function cURL($url, $referrer = '') {
        // A blank "referer" is "bad behavior"
        if ($referrer === '') {
            $referrer = "https://www.google.com";
        }
        $opts = [
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36',
                'Referer' => $referrer,
            ],
            'timeout' => 5,
        ];
        $proxy = null;
        if (\App::environment() == 'production') {
            $proxies = Helper::fileToArray(storage_path('app/proxies.txt'));
            $numProxies = count($proxies);
            if ($numProxies > 0) {
                $proxy = $proxies[mt_rand(0, $numProxies - 1)];
            }
        }
        if ($proxy) {
            $opts['proxy'] = 'http://' . $proxy;
        }

        $client = new \GuzzleHttp\Client($opts);

        $response = $client->get($url);
        return $response->getBody()->getContents();
    }

    public static function stripAttributes($html) {
        return preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/i",'<$1$2>', $html);
    }

    public static function normalizeText($html) {
        $normalization_map = array(
            // Regular Unicode     // U+0022 quotation mark (")
            // U+0027 apostrophe     (')
            chr(145) => "'",
            chr(146) => "'",
            chr(147) => '"',
            chr(148) => '"',
            chr(150) => '-',
            chr(151) => '--',
            chr(133) => '...',
            "\xC2\xAB"     => '"', // U+00AB left-pointing double angle quotation mark
            "\xC2\xBB"     => '"', // U+00BB right-pointing double angle quotation mark
            "\xE2\x80\x98" => "'", // U+2018 left single quotation mark
            "\xE2\x80\x99" => "'", // U+2019 right single quotation mark
            "\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
            "\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
            "\xE2\x80\x9C" => '"', // U+201C left double quotation mark
            "\xE2\x80\x9D" => '"', // U+201D right double quotation mark
            "\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
            "\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
            "\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
            "\xE2\x80\xBA" => "'", // U+203A single right-pointing angle quotation mark
            "\xE2\x80\x93" => "-",
            "\xE2\x80\x94" => "--",
            "\xE2\x80\xA6" => "...",
            "\xC2\x80" => "\xE2\x82\xAC", // U+20AC Euro sign
            "\xC2\x82" => "\xE2\x80\x9A", // U+201A single low-9 quotation mark
            "\xC2\x83" => "\xC6\x92",     // U+0192 latin small letter f with hook
            "\xC2\x84" => "\xE2\x80\x9E", // U+201E double low-9 quotation mark
            "\xC2\x85" => "\xE2\x80\xA6", // U+2026 horizontal ellipsis
            "\xC2\x86" => "\xE2\x80\xA0", // U+2020 dagger
            "\xC2\x87" => "\xE2\x80\xA1", // U+2021 double dagger
            "\xC2\x88" => "\xCB\x86",     // U+02C6 modifier letter circumflex accent
            "\xC2\x89" => "\xE2\x80\xB0", // U+2030 per mille sign
            "\xC2\x8A" => "\xC5\xA0",     // U+0160 latin capital letter s with caron
            "\xC2\x8B" => "\xE2\x80\xB9", // U+2039 single left-pointing angle quotation mark
            "\xC2\x8C" => "\xC5\x92",     // U+0152 latin capital ligature oe
            "\xC2\x8E" => "\xC5\xBD",     // U+017D latin capital letter z with caron
            "\xC2\x91" => "\xE2\x80\x98", // U+2018 left single quotation mark
            "\xC2\x92" => "\xE2\x80\x99", // U+2019 right single quotation mark
            "\xC2\x93" => "\xE2\x80\x9C", // U+201C left double quotation mark
            "\xC2\x94" => "\xE2\x80\x9D", // U+201D right double quotation mark
            "\xC2\x95" => "\xE2\x80\xA2", // U+2022 bullet
            "\xC2\x96" => "\xE2\x80\x93", // U+2013 en dash
            "\xC2\x97" => "\xE2\x80\x94", // U+2014 em dash
            "\xC2\x98" => "\xCB\x9C",     // U+02DC small tilde
            "\xC2\x99" => "\xE2\x84\xA2", // U+2122 trade mark sign
            "\xC2\x9A" => "\xC5\xA1",     // U+0161 latin small letter s with caron
            "\xC2\x9B" => "\xE2\x80\xBA", // U+203A single right-pointing angle quotation mark
            "\xC2\x9C" => "\xC5\x93",     // U+0153 latin small ligature oe
            "\xC2\x9E" => "\xC5\xBE",     // U+017E latin small letter z with caron
            "\xC2\x9F" => "\xC5\xB8",     // U+0178 latin capital letter y with diaeresis
        );
        $chr = array_keys  ($normalization_map); // but: for efficiency you should
        $rpl = array_values($normalization_map); // pre-calculate these two arrays
        $string = str_replace($chr, $rpl, $html);
        return $string;
    }

    public static function deleteOldFiles($dirName, $minutes) {
        if (is_dir($dirName)) {
            foreach (new DirectoryIterator($dirName) as $fileInfo) {
                if ($fileInfo->isDot() || $fileInfo->isDir()) continue;
                if (time() - $fileInfo->getCTime() > ($minutes * 60)) unlink($fileInfo->getRealPath());
            }
        }
    }

    public static function sendServerWebsocketMessage($msg, $ip = '127.0.0.1', $port = 8080)
    {
        $msg['server'] = env('APP_KEY', '');
        $client = new Client("ws://{$ip}:{$port}");
        $client->send(json_encode($msg));
    }

    public static function fileToArray($path)
    {
        return file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
}
