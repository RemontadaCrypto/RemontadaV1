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
        return implode('',explode(',',number_format($number, 9)));
    }

    public static function getRequestDataByCoin($coin, $from = null, $to = null, $sig = null, $amount = null, $fee = null): array
    {
        if ($coin['short_name'] == 'ETH') {
            $network = env('CRYPTO_NETWORK_2');
            $suffix = 'new-pvtkey';
            $key = 'privateKey';
            $trxData = [
                "fromAddress" =>  $from,
                "toAddress" => $to,
                "gasPrice" => $fee['gasPrice'] ?? null,
                "gasLimit" => $fee['gasLimit'] ?? null,
                "value" => $fee ? round($amount - ($fee['gasPrice'] * $fee['gasLimit'] * pow(10,-9)), 9) : $amount,
                "privateKey" => $sig
            ];
            $trxSizeData = [
                "fromAddress" =>  $trxData['fromAddress'],
                "toAddress" => $trxData['toAddress'],
                "value" => $trxData['value'],
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
                            "value" => round(($amount - $fee), 9)
                        ]
                    ],
                    "outputs" => [
                        [
                            "address" => $to,
                            "value" => round(($amount - $fee), 9)
                        ]
                    ],
                    "fee" => [
                        "address" => $from,
                        "value" =>  round($fee, 9)
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
