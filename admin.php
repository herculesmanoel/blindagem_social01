<?php
require_once 'efi.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    // 1. Importar serviços com margem de lucro (padrão 100%)
    if ($acao === 'importar_fornecedor') {
        $margem = max(0, (float)($_POST['margem'] ?? 100));
        $multiplicador = 1 + ($margem / 100);
        $limite = max(1, min(150, (int)($_POST['limite'] ?? 40)));

        $ch = curl_init(SMM_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['key' => SMM_API_KEY, 'action' => 'services'])
        ]);
        $lista = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (is_array($lista) && isset($lista[0]['service'])) {
            $importados = 0;
            $ins = $pdo->prepare("INSERT INTO servicos (provider_service_id, plataforma, tipo, qualidade, titulo, descricao, preco_por_mil, minimo, maximo, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

            foreach ($lista as $item) {
                if ($importados >= $limite) break;
                $sid   = (int)$item['service'];
                $nome  = trim($item['name'] ?? 'Serviço SMM');
                $cat   = trim($item['category'] ?? '');
                $custo = (float)($item['rate'] ?? 0);
                if ($custo <= 0) continue;

                $chk = $pdo->prepare("SELECT id FROM servicos WHERE provider_service_id = ?");
                $chk->execute([$sid]);
                if ($chk->fetch()) continue;

                $txt = mb_strtolower($nome . ' ' . $cat);
                $plat = 'Instagram';
                if (str_contains($txt, 'tiktok')) $plat = 'TikTok';
                elseif (str_contains($txt, 'youtube')) $plat = 'YouTube';
                elseif (str_contains($txt, 'facebook')) $plat = 'Facebook';
                elseif (str_contains($txt, 'telegram')) $plat = 'Telegram';
                elseif (str_contains($txt, 'kwai')) $plat = 'Kwai';

                $tipo = 'Seguidores';
                if (str_contains($txt, 'curtida') || str_contains($txt, 'like')) $tipo = 'Curtidas';
                elseif (str_contains($txt, 'visualiza') || str_contains($txt, 'view')) $tipo = 'Visualizacoes';

                $qual = (str_contains($txt, 'brasil') || str_contains($txt, 'br')) ? 'Exclusivo' : 'Premium';
                $venda = round($custo * $multiplicador, 2);
                $min = max(10, (int)($item['min'] ?? 100));
                $max = max($min, (int)($item['max'] ?? 50000));

                $ins->execute([$sid, $plat, $tipo, $qual, $nome, "Entrega automática para {$plat}. Não solicitamos senha.", $venda, $min, $max]);
                $importados++;
            }
            $msg = "✓ {$importados} serviços importados com {$margem}% de lucro!";
        } else {
            $msg = "⚠ Falha ao conectar na API do fornecedor. Confira SMM_API_URL e SMM_API_KEY no Railway.";
        }
    } elseif ($acao === 'novo_servico') {
        $pdo->prepare("INSERT INTO servicos (provider_service_id, plataforma, tipo, qualidade, titulo, descricao, preco_por_mil, minimo, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)")
            ->execute([(int)$_POST['provider_id'], $_POST['plataforma'], $_POST['tipo'], $_POST['qualidade'], $_POST['titulo'], $_POST['descricao'], (float)$_POST['preco'], (int)$_POST['minimo']]);
        $msg = '✓ Novo serviço criado!';
    } elseif ($acao === 'editar_servico') {
        $pdo->prepare("UPDATE servicos SET titulo = ?, plataforma = ?, provider_service_id = ?, preco_por_mil = ?, minimo = ?, ativo = ? WHERE id = ?")
            ->execute([trim($_POST['titulo']), $_POST['plataforma'], (int)$_POST['provider_id'], (float)$_POST['preco'], (int)$_POST['minimo'], (int)$_POST['ativo'], (int)$_POST['id']]);
        $msg = '✓ Serviço atualizado!';
    } elseif ($acao === 'reajustar_todos') {
        $fator = 1 + ((float)$_POST['percentual'] / 100);
        if ($fator > 0) {
            $pdo->prepare("UPDATE servicos SET preco_por_mil = ROUND(preco_por_mil * ?, 2)")->execute([$fator]);
            $msg = '✓ Todos os preços foram reajustados!';
        }
    } elseif ($acao === 'excluir_servico') {
        $pdo->prepare("DELETE FROM servicos WHERE id = ?")->execute([(int)$_POST['id']]);
        $msg = '✓ Serviço excluído!';
    } elseif ($acao === 'limpar_servicos') {
        $pdo->exec("DELETE FROM servicos");
        $msg = '✓ Lista de serviços limpa!';
    } elseif ($acao === 'ajustar_saldo') {
        $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?")->execute([(float)$_POST['valor'], (int)$_POST['user_id']]);
        $msg = '✓ Saldo do cliente atualizado!';
    } elseif ($acao === 'reset_senha') {
        $nova = trim($_POST['nova_senha'] ?? '');
        if (strlen($nova) >= 6) {
            $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?")->execute([password_hash($nova, PASSWORD_DEFAULT), (int)$_POST['user_id']]);
            $msg = '✓ Senha do cliente alterada com sucesso!';
        }
    } elseif ($acao === 'excluir_usuario') {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([(int)$_POST['user_id']]);
        $msg = '✓ Usuário removido!';
    } elseif ($acao === 'reenviar_pedido') {
        $pStmt = $pdo->prepare("SELECT p.*, s.provider_service_id FROM pedidos p JOIN servicos s ON p.servico_id = s.id WHERE p.id = ?");
        $pStmt->execute([(int)$_POST['pedido_id']]);
        $ped = $pStmt->fetch();
        if ($ped) {
            $resp = enviarParaProvedorSMM((int)$ped['provider_service_id'], $ped['link_destino'], (int)$ped['quantidade']);
            if (!empty($resp['order'])) {
                $pdo->prepare("UPDATE pedidos SET provider_order_id = ?, status_entrega = 'processando' WHERE id = ?")->execute([(string)$resp['order'], $ped['id']]);
                $msg = "✓ Pedido reenviado ao fornecedor! ID: #{$resp['order']}";
            } else {
                $msg = "⚠ Fornecedor recusou: " . json_encode($resp);
            }
        }
    } elseif ($acao === 'status_pedido') {
        $pdo->prepare("UPDATE pedidos SET status_entrega = ? WHERE id = ?")->execute([$_POST['status'], (int)$_POST['pedido_id']]);
        $msg = '✓ Status do pedido atualizado!';
    }
}

$saldoFornecedor = 'Não conectado';
$chBal = curl_init(SMM_API_URL);
curl_setopt_array($chBal, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 4, CURLOPT_POSTFIELDS => http_build_query(['key' => SMM_API_KEY, 'action' => 'balance'])]);
$balResp = json_decode(curl_exec($chBal), true);
curl_close($chBal);
if (isset($balResp['balance'])) {
    $saldoFornecedor = ($balResp['currency'] ?? 'BRL') . ' ' . number_format((float)$balResp['balance'], 2, ',', '.');
}

$servicos    = $pdo->query("SELECT * FROM servicos ORDER BY plataforma ASC, id ASC")->fetchAll();
$usuarios    = $pdo->query("SELECT * FROM usuarios ORDER BY id DESC LIMIT 50")->fetchAll();
$pedidos     = $pdo->query("SELECT p.*, s.titulo, u.usuario AS nome_user FROM pedidos p JOIN servicos s ON p.servico_id = s.id LEFT JOIN usuarios u ON p.usuario_id = u.id ORDER BY p.id DESC LIMIT 50")->fetchAll();
$faturamento = (float)$pdo->query("SELECT COALESCE(SUM(valor_total),0) FROM pedidos WHERE status_pagamento = 'pago'")->fetchColumn();
?>
