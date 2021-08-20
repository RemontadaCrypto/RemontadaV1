<?php

namespace App\Http\Traits;

trait helpers {
    public static function getHeaders(): array
    {
        return [
            'Content-type' => 'application/json',
            'X-API-Key' => env('CRYPTO_API_KEY')
        ];
    }

    public static function getFormattedCoinAmount($number, $precision = 8, $separator = '.'): float
    {
        $numberParts = explode($separator, $number);
        $response = $numberParts[0];
        if (count($numberParts)>1 && $precision > 0) {
            $response .= $separator;
            $response .= substr($numberParts[1], 0, $precision);
        }
        return (float) $response;
    }

    public static function getRequestDataByCoin($coin, $from = null, $to = null, $sig = null, $amount = null, $fee = null, $nonce = null): array
    {
        if ($coin['short_name'] == 'ETH') {
            $network = env('CRYPTO_NETWORK_2');
            $suffix = 'new-pvtkey';
            $key = 'privateKey';
            $trxData = [
                "fromAddress" =>  $from,
                "toAddress" => $to,
                "gasPrice" => $fee ? (int) ($fee['gasPrice'] * pow(10,8)) : null,
                "gasLimit" => $fee['gasLimit'] ?? null,
                "value" => $fee ? self::getFormattedCoinAmount($amount - ($fee['gasPrice'] * $fee['gasLimit'] * pow(10,-8))) : self::getFormattedCoinAmount($amount),
                "privateKey" => $sig
            ];
            if ($nonce) $trxData['nonce'] = $nonce;
            $trxSizeData = [
                "fromAddress" =>  $trxData['fromAddress'],
                "toAddress" => $trxData['toAddress'],
                "value" => self::getFormattedCoinAmount($trxData['value']),
            ];
            $feeEndpointType = "gas";
        } else {
            $network = env('CRYPTO_NETWORK_1');
            $suffix = 'new';
            $key = 'wif';
            $trxData = [
                "createTx" =>  [
                    "inputs" => [
                        [
                            "address" => $from,
                            "value" => self::getFormattedCoinAmount(($amount - $fee))
                        ]
                    ],
                    "outputs" => [
                        [
                            "address" => $to,
                            "value" => self::getFormattedCoinAmount(($amount - $fee))
                        ]
                    ],
                    "fee" => [
                        "address" => $from,
                        "value" =>  self::getFormattedCoinAmount($fee)
                    ]
                ],
                "wifs" => [
                    $sig
                ]
            ];
            $trxSizeData = $trxData['createTx'];
            $feeEndpointType = "size";
        }
        $address = null;
        if (auth()->user()) {
            $address = auth()->user()->getAddressByCoin($coin['id'])['pth'] ?? null;
        }
        return [
            'network' => $network,
            'suffix' => $suffix,
            'trxData' => $trxData,
            'trxSizeData' => $trxSizeData,
            'feeEndpointType' => $feeEndpointType,
            'key' => $key,
            'coin' => strtolower($coin['short_name']),
            'address' => $address
        ];
    }
}
