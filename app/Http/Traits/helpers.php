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

    public static function getFormattedCoinAmount($number): string
    {
        return implode('',explode(',',number_format($number, 8)));
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
                "value" => $fee ? self::getFormattedCoinAmount($amount - ($fee['gasPrice'] * $fee['gasLimit'] * pow(10,-8)), 8) : self::getFormattedCoinAmount($amount,8),
                "privateKey" => $sig
            ];
            if ($nonce) $trxData['nonce'] = $nonce;
            $trxSizeData = [
                "fromAddress" =>  $trxData['fromAddress'],
                "toAddress" => $trxData['toAddress'],
                "value" => self::getFormattedCoinAmount($trxData['value'], 6),
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
                            "value" => self::getFormattedCoinAmount(($amount - $fee), 8)
                        ]
                    ],
                    "outputs" => [
                        [
                            "address" => $to,
                            "value" => self::getFormattedCoinAmount(($amount - $fee), 8)
                        ]
                    ],
                    "fee" => [
                        "address" => $from,
                        "value" =>  self::getFormattedCoinAmount($fee, 8)
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
