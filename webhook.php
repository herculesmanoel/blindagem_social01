<?php
require_once 'efi.php';

// Permite registrar o webhook acessando: webhook.php?registrar=1&senha=SUA_SENHA_ADMIN
if (isset($_GET['registrar']) && ($_GET['senha'] ?? '') === ADMIN_PASSWORD) {
    $token = efiGetToken();
    $urlWebhook = 'https://' . $_SERVER['HTTP_HOST'] . '/webhook.php?ignorar=';
    $res = efiRequest('PUT', '/v2/webhook/' . EFI_CHAVE_PIX, ['webhookUrl' => $urlWebhook], $token);
    header('Content-Type: application/json');
    echo json_encode(['webhook_configurado' => $urlWebhook, 'resposta_efi' => $res]);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (empty($payload['pix']) || !is_array($payload['pix'])) {
    http_response_code(200);
    echo 'OK';
    exit;
}

foreach ($payload['pix'] as $pixRecebido) {
    $txid = $pixRecebido['txid'] ?? '';
    if ($txid === '') continue;

    $stmt = $pdo->prepare("SELECT p.*, s.provider_service_id FROM pedidos p JOIN servicos s ON p.servico_id = s.id WHERE p.codigo_rastreio = ? AND p.status_pagamento = 'pendente'");
    $stmt->execute([$txid]);
    $pedido = $stmt->fetch();

    if ($pedido) {
        $respSmm = enviarParaProvedorSMM((int)$pedido['provider_service_id'], $pedido['link_destino'], (int)$pedido['quantidade']);
        $orderId = $respSmm['order'] ?? null;
        $statusEntrega = $orderId ? 'processando' : 'erro';

        $upd = $pdo->prepare("UPDATE pedidos SET status_pagamento = 'pago', status_entrega = ?, provider_order_id = ? WHERE id = ?");
        $upd->execute([$statusEntrega, $orderId ? (string)$orderId : json_encode($respSmm), $pedido['id']]);
    }
}

http_response_code(200);
echo 'OK';
