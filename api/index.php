<?php

require_once __DIR__ . '/vendor/autoload.php';

use Hyperf\Nano\Factory\AppFactory;
use Hyperf\DbConnection\Db;
use Carbon\Carbon;

$app = AppFactory::createBase();

$app->config([
    'databases' => [
        'default' => [
            'driver' => 'pgsql-swoole',
            'host' => 'db',
            'database' => 'rinhadb',
            'port' => 5432,
            'username' => 'postgre',
            'password' => 'postgre',
            'pool' => [
                'min_connections' => 1,
                'max_connections' => 5,
                'connect_timeout' => 10.0,
                'wait_timeout' => 3.0,
                'heartbeat' => -1,
            ],
        ]
    ]
]);

$app->post('/clientes/{id}/transacoes', function ($id) {
    $transactionData = $this->request->all();
        if (
            !isset($transactionData['valor']) || !is_int($transactionData['valor']) ||
            !isset($transactionData['tipo']) || !in_array($transactionData['tipo'], ['c', 'd']) ||
            !isset($transactionData['descricao']) || !is_string($transactionData['descricao']) ||
            strlen($transactionData['descricao']) < 1 || strlen($transactionData['descricao']) > 10
        ) {
            return $this->response->withStatus(422);
        }

        if ($id > 5) {
            return $this->response->withStatus(400);
        }

        $pdo = Db::connection('default')->getPdo();
    try {
        $param1 = 0;
        $param2 = 0;
        $stmt = $pdo->query("CALL INSERIR_TRANSACAO_2($id, {$transactionData['valor']}, '{$transactionData['tipo']}', '{$transactionData['descricao']}', $param1, $param2)");
        $response = (array) $stmt->fetchObject();

        return [
            // 'limite' => $result['limite'],
            'saldo' => $response['saldo_atualizado'],
            'limite' => $response['limite_atualizado'],
        ];
    } catch (\Throwable $th) {
        // Tratamento de erro
        var_dump($th->getMessage());
        return $this->response->withStatus(500);
    }
});

$app->get('/clientes/{id}/extrato', function ($id) {
    $clientWithTransactions = Db::table('clients')
        ->leftJoin('transactions', 'clients.id', '=', 'transactions.client_id')
        ->where('clients.id', $id)
        ->select([
            'clients.limit',
            'clients.balance',
            'transactions.value',
            'transactions.type',
            'transactions.description',
            'transactions.created_at',
        ])
        ->orderByDesc('transactions.created_at')
        ->limit(10)
        ->get();

    if ($clientWithTransactions->isEmpty())
        return $this->response->withStatus(404);

    $transactionsClient = $clientWithTransactions->map(function ($transaction) {
        return [
            'valor' => $transaction['value'],
            'tipo' => $transaction['type'],
            'descricao' => $transaction['description'],
            'realizada_em' => Carbon::parse($transaction['created_at'])->toISOString(),
        ];
    });

    return [
        'saldo' => [
            'total' => $clientWithTransactions[0]['balance'],
            'data_extrato' => Carbon::now()->toISOString(),
            'limite' => $clientWithTransactions[0]['limit'],
        ],
        'ultimas_transacoes' => $transactionsClient,
    ];
});

$app->run();
