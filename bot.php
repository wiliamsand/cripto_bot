<?php

require 'vendor/autoload.php';

use Binance\API;

// Configuração das credenciais da API
$apiKey = "sua_api_key";
$apiSecret = "sua_api_secret";
$binance = new API($apiKey, $apiSecret);

// Parâmetros de gerenciamento de risco
$takeProfitPercentage = 0.05; // Lucro de 5%
$stopLossPercentage = 0.02;  // Perda de 2%

/**
 * Obtém a lista de símbolos disponíveis na Binance.
 */
function getCurrentSymbols($binance) {
    $exchangeInfo = $binance->exchangeInfo();
    $symbols = [];
    foreach ($exchangeInfo['symbols'] as $symbol) {
        $symbols[] = $symbol['symbol'];
    }
    return $symbols;
}

/**
 * Envia uma ordem de compra de mercado.
 */
function placeBuyOrder($binance, $symbol, $quantity) {
    try {
        $order = $binance->marketBuy($symbol, $quantity);
        echo "Ordem de compra enviada para $symbol\n";
        return $order;
    } catch (Exception $e) {
        echo "Erro ao enviar ordem de compra: " . $e->getMessage() . "\n";
        return null;
    }
}

/**
 * Envia uma ordem de venda de mercado.
 */
function placeSellOrder($binance, $symbol, $quantity) {
    try {
        $order = $binance->marketSell($symbol, $quantity);
        echo "Ordem de venda enviada para $symbol\n";
        return $order;
    } catch (Exception $e) {
        echo "Erro ao enviar ordem de venda: " . $e->getMessage() . "\n";
        return null;
    }
}

/**
 * Monitora novas listagens e gerencia ordens.
 */
function monitorNewListings($binance, $takeProfitPercentage, $stopLossPercentage) {
    $currentSymbols = getCurrentSymbols($binance);

    while (true) {
        echo "Monitorando novas listagens...\n";
        $updatedSymbols = getCurrentSymbols($binance);
        $newSymbols = array_diff($updatedSymbols, $currentSymbols);

        if (!empty($newSymbols)) {
            echo "Novas criptomoedas detectadas: " . implode(", ", $newSymbols) . "\n";
            foreach ($newSymbols as $symbol) {
                if (str_ends_with($symbol, "USDT")) { // Foco em pares USDT
                    $quantity = 10; // Quantidade fixa (ajuste conforme necessário)
                    $buyOrder = placeBuyOrder($binance, $symbol, $quantity);

                    if ($buyOrder) {
                        $buyPrice = $buyOrder['fills'][0]['price']; // Preço da compra
                        $takeProfitPrice = $buyPrice * (1 + $takeProfitPercentage);
                        $stopLossPrice = $buyPrice * (1 - $stopLossPercentage);

                        echo "Take Profit: $takeProfitPrice, Stop Loss: $stopLossPrice\n";

                        // Monitorar o preço para venda
                        while (true) {
                            $ticker = $binance->price($symbol);
                            $currentPrice = $ticker['price'];
                            echo "Preço atual de $symbol: $currentPrice\n";

                            if ($currentPrice >= $takeProfitPrice) {
                                echo "Alvo de lucro atingido!\n";
                                placeSellOrder($binance, $symbol, $quantity);
                                break;
                            } elseif ($currentPrice <= $stopLossPrice) {
                                echo "Stop Loss ativado!\n";
                                placeSellOrder($binance, $symbol, $quantity);
                                break;
                            }
                            sleep(5); // Espera 5 segundos antes de verificar novamente
                        }
                    }
                }
            }
            $currentSymbols = $updatedSymbols;
        }
        sleep(60); // Verificar a cada 60 segundos
    }
}

// Executa o monitoramento
monitorNewListings($binance, $takeProfitPercentage, $stopLossPercentage);

?>
