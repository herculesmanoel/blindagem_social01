<?php
require_once 'efi.php';

$pendentes = $pdo->query("SELECT p.*, u.id AS uid FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE p.status_entrega = 'processando' AND p.provider_order_id IS NOT NULL LIMIT 30")->fetchAll();

$atualizados = 0;
foreach ($pendentes as $ped) {
    $ch = curl_init(SMM_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'key'    => SMM_API_KEY,
            'action' => 'status',
            'order'  => $ped['provider_order_id']
        ])
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($resp['status'])) {
        $st = strtolower($resp['status']);
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

header('Content-Type: application/json');
echo json_encode(['verificados' => count($pendentes), 'atualizados' => $atualizados]);
