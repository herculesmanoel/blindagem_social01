<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$acao = $_GET['acao'] ?? '';

if ($acao === 'criar_pedido' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = json_decode(file_get_contents('php://input'), true);

    $servico_id = (int)($dados['servico_id'] ?? 0);
    $quantidade = (int)($dados['quantidade'] ?? 0);
    $link       = trim($dados['link'] ?? '');
    $email      = filter_var(trim($dados['email'] ?? ''), FILTER_VALIDATE_EMAIL);

    if (!$servico_id || $quantidade < 10 || empty($link) || !$email) {
        http_response_code(400);
        echo json_encode(['erro' => 'Preencha todos os campos corretamente.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM servicos WHERE id = ? AND ativo = 1");
    $stmt->execute([$servico_id]);
    $servico = $stmt->fetch();

    if (!$servico || $quantidade < $servico['minimo'] || $quantidade > $servico['maximo']) {
        http_response_code(400);
        echo json_encode(['erro' => 'Quantidade fora do limite permitido.']);
        exit;
    }

    $valor_total = round(($servico['preco_por_mil'] / 1000) * $quantidade, 2);
    $codigo = 'UP-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $pix_copia_cola = "00020126580014br.gov.bcb.pix0136pix-pedido-{$codigo}520400005303986540" . number_format($valor_total, 2, '.', '') . "5802BR";

    $ins = $pdo->prepare("INSERT INTO pedidos (codigo_rastreio, servico_id, link_destino, quantidade, valor_total, email_cliente, pix_copia_cola) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([$codigo, $servico_id, $link, $quantidade, $valor_total, $email, $pix_copia_cola]);

    echo json_encode([
        'sucesso' => true,
        'codigo' => $codigo,
        'valor_total' => number_format($valor_total, 2, ',', '.'),
        'pix_copia_cola' => $pix_copia_cola
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['erro' => 'Ação inválida.']);
