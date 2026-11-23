<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'efi.php';

$key    = trim($_REQUEST['key'] ?? '');
$action = trim($_REQUEST['action'] ?? '');

if ($key === '') {
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE api_key = ?");
$stmt->execute([$key]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

if ($action === 'balance') {
    echo json_encode(['balance' => number_format((float)$user['saldo'], 2, '.', ''), 'currency' => 'BRL']);
    exit;
}

if ($action === 'services') {
    $servicos = $pdo->query("SELECT id AS service, titulo AS name, tipo AS type, plataforma AS category, preco_por_mil AS rate, minimo AS min, maximo AS max FROM servicos WHERE ativo = 1")->fetchAll();
    echo json_encode($servicos);
    exit;
}

if ($action === 'add') {
    $serviceId = (int)($_REQUEST['service'] ?? 0);
    $link      = trim($_REQUEST['link'] ?? '');
    $quantity  = (int)($_REQUEST['quantity'] ?? 0);

    $sStmt = $pdo->prepare("SELECT * FROM servicos WHERE id = ? AND ativo = 1");
    $sStmt->execute([$serviceId]);
    $serv = $sStmt->fetch();

    if (!$serv || $link === '' || $quantity < $serv['minimo'] || $quantity > $serv['maximo']) {
        echo json_encode(['error' => 'Invalid service, link or quantity']);
        exit;
    }

    $valorTotal = round(($serv['preco_por_mil'] / 1000) * $quantity, 2);
    if ((float)$user['saldo'] < $valorTotal) {
        echo json_encode(['error' => 'Not enough funds']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE usuarios SET saldo = saldo - ? WHERE id = ?")->execute([$valorTotal, $user['id']]);
        $respSmm = enviarParaProvedorSMM((int)$serv['provider_service_id'], $link, $quantity);
        $orderId = $respSmm['order'] ?? null;
        $codigo  = 'API-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $ins = $pdo->prepare("INSERT INTO pedidos (usuario_id, codigo_rastreio, servico_id, link_destino, quantidade, valor_total, email_cliente, status_pagamento, status_entrega, provider_order_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'pago', ?, ?)");
        $ins->execute([$user['id'], $codigo, $serv['id'], $link, $quantity, $valorTotal, $user['email'], $orderId ? 'processando' : 'aguardando', $orderId ? (string)$orderId : null]);

        $idInterno = (int)$pdo->lastInsertId();
        $pdo->commit();
        echo json_encode(['order' => $idInterno]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Order failed']);
    }
    exit;
}

if ($action === 'status') {
    $orderId = (int)($_REQUEST['order'] ?? 0);
    $pStmt = $pdo->prepare("SELECT status_entrega AS status, valor_total AS charge FROM pedidos WHERE id = ? AND usuario_id = ?");
    $pStmt->execute([$orderId, $user['id']]);
    $ped = $pStmt->fetch();
    echo json_encode($ped ?: ['error' => 'Incorrect order ID']);
    exit;
}

echo json_encode(['error' => 'Incorrect request']);
