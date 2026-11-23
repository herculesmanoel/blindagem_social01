<?php
require_once 'efi.php';

$pendentes = $pdo->query("SELECT p.*, u.id AS uid FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE p.status_entrega = 'processando' AND p.provider_order_id IS NOT NULL AND p.provider_order_id != '' LIMIT 50")->fetchAll();

$atualizados = 0;

if (!empty($pendentes)) {
    $idsFornecedor = array_column($pendentes, 'provider_order_id');
    $ch = curl_init(SMM_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_POSTFIELDS     => http_build_query([
            'key'    => SMM_API_KEY,
            'action' => 'status',
            'orders' => implode(',', $idsFornecedor)
        ])
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $mapaStatus = json_decode($raw, true);

    foreach ($pendentes as $ped) {
        $oid = (string)$ped['provider_order_id'];
        $info = $mapaStatus[$oid] ?? null;

        if (!is_array($info)) {
            $ch2 = curl_init(SMM_API_URL);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'key'    => SMM_API_KEY,
                    'action' => 'status',
                    'order'  => $oid
                ])
            ]);
            $info = json_decode(curl_exec($ch2), true);
            curl_close($ch2);
        }

        if (!empty($info['status'])) {
            $st = strtolower(trim($info['status']));
            if ($st === 'completed') {
                $pdo->prepare("UPDATE pedidos SET status_entrega = 'concluido' WHERE id = ?")->execute([$ped['id']]);
                $atualizados++;
            } elseif ($st === 'canceled' || $st === 'refunded') {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE pedidos SET status_entrega = 'cancelado' WHERE id = ?")->execute([$ped['id']]);
                if (!empty($ped['uid'])) {
                    $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?")->execute([$ped['valor_total'], $ped['uid']]);
                }
                $pdo->commit();
                $atualizados++;
            }
        }
    }
}

if (isset($_GET['json'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['verificados' => count($pendentes), 'atualizados' => $atualizados]);
    exit;
}

header("Location: admin.php?sinc={$atualizados}");
exit;
