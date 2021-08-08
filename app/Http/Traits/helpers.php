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

    public static function getRequestDataByCoin($coin, $from = null, $to = null, $sig = null, $amount = null, $fee = null): array
    {
        if ($coin['short_name'] == 'ETH') {
            $network = env('CRYPTO_NETWORK_2');
            $suffix = 'new-pvtkey';
            $key = 'privateKey';
            $trxData = [
                "fromAddress" =>  $from,
                "toAddress" => $to,
                "gasPrice" => 56000000000,
                "gasLimit" => 21000,
                "value" => round($amount, 8),
                "privateKey" => $sig
            ];
        } else {
            $network = env('CRYPTO_NETWORK_1');
            $suffix = 'new';
            $key = 'wif';
            $trxData = [
                "createTx" =>  [
                    "inputs" => [
                        [
                            "address" => $from,
                            "value" => round(($amount - $fee), 8)
                        ]
                    ],
                    "outputs" => [
                        [
                            "address" => $to,
                            "value" => round(($amount - $fee), 8)
                        ]
                    ],
                    "fee" => [
                        "address" => $from,
                        "value" =>  round($fee, 8)
                    ]
                ],
                "wifs" => [
                    $sig
                ]
            ];
        }
        $address = null;
        if (auth()->user()) {
            $address = auth()->user()->getAddressByCoin($coin['id'])['pth'] ?? null;
        }
        return [
            'network' => $network,
            'suffix' => $suffix,
            'trxData' => $trxData,
            'key' => $key,
            'coin' => strtolower($coin['short_name']),
            'address' => $address
        ];
    }
}
