<?php
require_once 'efi.php';

$user = usuarioAtual($pdo);
if (!$user) {
    header('Location: auth.php?modo=login');
    exit;
}

$msgSucesso = '';
$msgErro = '';
$pixGerado = null;
$abaAtiva = $_GET['aba'] ?? 'pedido';

if (isset($_GET['sincronizar'])) {
    $abaAtiva = 'historico';
    $pends = $pdo->prepare("SELECT * FROM pedidos WHERE usuario_id = ? AND status_entrega = 'processando' AND provider_order_id IS NOT NULL AND provider_order_id != ''");
    $pends->execute([$user['id']]);
    foreach ($pends->fetchAll() as $ped) {
        $ch = curl_init(SMM_API_URL);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 5, CURLOPT_POSTFIELDS => http_build_query(['key' => SMM_API_KEY, 'action' => 'status', 'order' => $ped['provider_order_id']])]);
        $r = json_decode(curl_exec($ch), true);
        curl_close($ch);
        if (!empty($r['status'])) {
            $st = strtolower(trim($r['status']));
            if ($st === 'completed') {
                $pdo->prepare("UPDATE pedidos SET status_entrega = 'concluido' WHERE id = ?")->execute([$ped['id']]);
            } elseif ($st === 'canceled' || $st === 'refunded') {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE pedidos SET status_entrega = 'cancelado' WHERE id = ?")->execute([$ped['id']]);
                $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?")->execute([$ped['valor_total'], $user['id']]);
                $pdo->commit();
                $user['saldo'] += (float)$ped['valor_total'];
            }
        }
    }
    $msgSucesso = 'Status dos pedidos sincronizados com sucesso!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'novo_pedido') {
        $abaAtiva   = 'pedido';
        $servicoId  = (int)($_POST['servico_id'] ?? 0);
        $link       = trim($_POST['link'] ?? '');
        $quantidade = (int)($_POST['quantidade'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM servicos WHERE id = ? AND ativo = 1");
        $stmt->execute([$servicoId]);
        $serv = $stmt->fetch();

        if (!$serv || empty($link) || $quantidade < $serv['minimo'] || $quantidade > $serv['maximo']) {
            $msgErro = 'Verifique o link e a quantidade mínima/máxima do serviço.';
        } else {
            $valorTotal = round(($serv['preco_por_mil'] / 1000) * $quantidade, 2);
            if ((float)$user['saldo'] < $valorTotal) {
                $msgErro = 'Saldo insuficiente! Adicione saldo via PIX na aba "Adicionar Saldo".';
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE usuarios SET saldo = saldo - ? WHERE id = ?")->execute([$valorTotal, $user['id']]);
                    $respSmm = enviarParaProvedorSMM((int)$serv['provider_service_id'], $link, $quantidade);
                    $orderId = $respSmm['order'] ?? null;
                    $statusEntrega = $orderId ? 'processando' : 'aguardando';
                    $codigo = 'BS-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

                    $ins = $pdo->prepare("INSERT INTO pedidos (usuario_id, codigo_rastreio, servico_id, link_destino, quantidade, valor_total, email_cliente, status_pagamento, status_entrega, provider_order_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'pago', ?, ?)");
                    $ins->execute([$user['id'], $codigo, $serv['id'], $link, $quantidade, $valorTotal, $user['email'], $statusEntrega, $orderId ? (string)$orderId : null]);

                    $pdo->commit();
                    $user['saldo'] -= $valorTotal;
                    $msgSucesso = "Pedido #{$codigo} enviado com sucesso! Valor debitado: R$ " . number_format($valorTotal, 2, ',', '.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msgErro = 'Erro ao processar pedido: ' . $e->getMessage();
                }
            }
        }
    } elseif ($acao === 'gerar_pix') {
        $abaAtiva = 'saldo';
        $valorRecarga = round((float)str_replace(',', '.', $_POST['valor'] ?? '0'), 2);
        if ($valorRecarga < 1.00) {
            $msgErro = 'O valor mínimo para adicionar saldo via PIX é R$ 1,00.';
        } else {
            try {
                $txid = 'PIX' . bin2hex(random_bytes(13));
                $cob = efiCriarCobrancaPix($txid, $valorRecarga, "Recarga Blindagem Social - {$user['usuario']}");
                $copiaCola = $cob['pixCopiaECola'];

                $pdo->prepare("INSERT INTO transacoes_pix (usuario_id, txid, valor, pix_copia_cola) VALUES (?, ?, ?, ?)")
                    ->execute([$user['id'], $txid, $valorRecarga, $copiaCola]);

                $pixGerado = ['valor' => $valorRecarga, 'codigo' => $copiaCola, 'txid' => $txid];
            } catch (Exception $e) {
                $msgErro = 'Erro ao gerar PIX Efí: ' . $e->getMessage();
            }
        }
    } elseif ($acao === 'nova_api_key') {
        $abaAtiva = 'conta';
        $novaKey = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE usuarios SET api_key = ? WHERE id = ?")->execute([$novaKey, $user['id']]);
        $user['api_key'] = $novaKey;
        $msgSucesso = 'Nova chave de API gerada com sucesso!';
    } elseif ($acao === 'alterar_senha') {
        $abaAtiva = 'conta';
        $novaSenha = $_POST['nova_senha'] ?? '';
        if (strlen($novaSenha) < 6) {
            $msgErro = 'A nova senha deve ter no mínimo 6 caracteres.';
        } else {
            $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?")->execute([password_hash($novaSenha, PASSWORD_DEFAULT), $user['id']]);
            $msgSucesso = 'Sua senha foi atualizada com sucesso!';
        }
    } elseif ($acao === 'enviar_suporte') {
        $abaAtiva = 'suporte';
        $assunto  = trim($_POST['assunto'] ?? '');
        $mensagem = trim($_POST['mensagem'] ?? '');
        if ($assunto !== '' && $mensagem !== '') {
            $pdo->prepare("INSERT INTO tickets_suporte (usuario_id, assunto, mensagem) VALUES (?, ?, ?)")
                ->execute([$user['id'], $assunto, $mensagem]);
            $msgSucesso = 'Solicitação enviada! Você verá a resposta do administrador abaixo assim que for respondida.';
        }
    }
}

$servicos = $pdo->query("SELECT * FROM servicos WHERE ativo = 1 ORDER BY plataforma ASC, id ASC")->fetchAll();
$pedidosStmt = $pdo->prepare("SELECT p.*, s.titulo, s.plataforma FROM pedidos p JOIN servicos s ON p.servico_id = s.id WHERE p.usuario_id = ? ORDER BY p.id DESC LIMIT 50");
$pedidosStmt->execute([$user['id']]);
$listaPedidos = $pedidosStmt->fetchAll();

$totalGasto = 0;
foreach ($listaPedidos as $lp) {
    $totalGasto += (float)$lp['valor_total'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel VIP — Blindagem Social</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  body{background:#060608;color:#fafafa;min-height:100vh;display:flex;flex-direction:column}
  a{text-decoration:none;color:inherit}
  .container{max-width:1180px;margin:0 auto;padding:0 20px;width:100%}
  .gold{color:#fbbf24}
  .live-bar{background:linear-gradient(90deg,#12100a,#1c1608,#12100a);border-bottom:1px solid rgba(251,191,36,.25);height:34px;display:flex;align-items:center;overflow:hidden;font-size:12px}
  .live-track{display:flex;gap:48px;white-space:nowrap;animation:ticker 32s linear infinite;padding-left:100%}
  .live-item{display:inline-flex;align-items:center;gap:8px;color:#e4e4e7}
  .dot-live{width:7px;height:7px;border-radius:50%;background:#10b981;box-shadow:0 0 8px #10b981}
  @keyframes ticker{0%{transform:translateX(0)}100%{transform:translateX(-100%)}}
  .topbar{background:#121215;border-bottom:1px solid #27272a;position:sticky;top:0;z-index:40}
  .topbar-inner{height:68px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
  .brand{display:flex;align-items:center;gap:10px;font-weight:900;font-size:18px}
  .badge-logo{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#fde68a,#f59e0b,#d97706);color:#09090b;display:flex;align-items:center;justify-content:center;font-weight:900}
  .saldo-pill{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.35);padding:8px 14px;border-radius:12px;font-size:13px;font-weight:800;color:#fbbf24}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:11px 18px;border-radius:12px;font-weight:800;font-size:13px;border:none;cursor:pointer;transition:.2s}
  .btn-gold{background:linear-gradient(135deg,#fde68a,#f59e0b,#d97706);color:#09090b;box-shadow:0 6px 20px rgba(245,158,11,.22)}
  .btn-gold:hover{filter:brightness(1.06)}
  .btn-dark{background:#18181b;color:#e4e4e7;border:1px solid #27272a}
  .btn-dark:hover{border-color:#fbbf24;color:#fbbf24}
  .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:24px 0}
  .stat-box{background:#121215;border:1px solid #27272a;border-radius:16px;padding:18px 20px}
  .stat-box span{font-size:12px;color:#a1a1aa;display:block}
  .stat-box strong{font-size:22px;font-weight:900;color:#fbbf24;margin-top:4px;display:block}
  .menu-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px;background:#121215;padding:8px;border-radius:16px;border:1px solid #27272a}
  .tab-btn{padding:10px 16px;border-radius:11px;font-size:13px;font-weight:700;background:transparent;color:#a1a1aa;border:none;cursor:pointer;transition:.2s}
  .tab-btn:hover{color:#fff}
  .tab-btn.active{background:linear-gradient(135deg,#fde68a,#f59e0b,#d97706);color:#09090b}
  .panel-card{background:#121215;border:1px solid #27272a;border-radius:20px;padding:28px;margin-bottom:28px}
  .field{margin-bottom:16px}
  .field label{display:block;font-size:12px;color:#a1a1aa;margin-bottom:6px;font-weight:600}
  .field input,.field select,.field textarea{width:100%;padding:12px 14px;border-radius:12px;background:#09090b;border:1px solid #27272a;color:#fff;font-size:14px;outline:none}
  .field input:focus,.field select:focus,.field textarea:focus{border-color:#fbbf24}
  .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .total-box{background:#09090b;border:1px solid rgba(251,191,36,.3);border-radius:12px;padding:12px 16px;display:flex;flex-direction:column;justify-content:center}
  .alert-ok{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.35);color:#34d399;padding:14px 18px;border-radius:14px;font-size:13px;margin-bottom:20px;font-weight:600}
  .alert-err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);color:#f87171;padding:14px 18px;border-radius:14px;font-size:13px;margin-bottom:20px;font-weight:600}
  .table-wrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;font-size:13px;text-align:left}
  th{color:#a1a1aa;font-weight:700;padding:12px;border-bottom:1px solid #27272a;font-size:12px;text-transform:uppercase}
  td{padding:13px 12px;border-bottom:1px solid #1f1f23}
  .status-badge{padding:4px 10px;border-radius:99px;font-size:11px;font-weight:800;background:rgba(245,158,11,.14);color:#fbbf24;text-transform:uppercase}
  .tab-pane{display:none}
  .tab-pane.active{display:block}
  @media(max-width:768px){.stats-row,.grid-2{grid-template-columns:1fr}}
</style>
</head>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel VIP — Blindagem Social</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  body{background:#060608;color:#fafafa;min-height:100vh;display:flex;flex-direction:column}
  a{text-decoration:none;color:inherit}
  .container{max-width:1180px;margin:0 auto;padding:0 20px;width:100%}
  .gold{color:#fbbf24}
  .live-bar{background:linear-gradient(90deg,#12100a,#1c1608,#12100a);border-bottom:1px solid rgba(251,191,36,.25);height:34px;display:flex;align-items:center;overflow:hidden;font-size:12px}
  .live-track{display:flex;gap:48px;white-space:nowrap;animation:ticker 32s linear infinite;padding-left:100%}
  .live-item{display:inline-flex;align-items:center;gap:8px;color:#e4e4e7}
  .dot-live{width:7px;height:7px;border-radius:50%;background:#10b981;box-shadow:0 0 8px #10b981}
  @keyframes ticker{0%{transform:translateX(0)}100%{transform:translateX(-100%)}}
  .topbar{background:#121215;border-bottom:1px solid #27272a;position:sticky;top:0;z-index:40}
  .topbar-inner{height:68px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
  .brand{display:flex;align-items:center;gap:10px;font-weight:900;font-size:18px}
  .badge-logo{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#fde68a,#f59e0b,#d97706);color:#09090b;display:flex;align-items:center;justify-content:center;font-weight:900}
  .saldo-pill{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.35);padding:8px 14px;border-radius:12px;font-size:13px;font-weight:800;color:#fbbf24}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:11px 18px;border-radius:12px;font-weight:800;font-size:13px;border:none;cursor:pointer;transition:.2s}
  .btn-gold{background:linear-gradient(135deg,#fde68a,#f59e0b,#d97706);color:#09090b;box-shadow:0 6px 20px rgba(245,158,11,.22)}
  .btn-gold:hover{filter:brightness(1.06)}
  .btn-dark{background:#18181b;color:#e4e4e7;border:1px solid #27272a}
  .btn-dark:hover{border-color:#fbbf24;color:#fbbf24}
  .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:24px 0}
  .stat-box{background:#121215;border:1px solid #27272a;border-radius:16px;padding:18px 20px}
  .stat-box span{font-size:12px;color:#a1a1aa;display:block}
  .stat-box strong{font-size:22px;font-weight:900;color:#fbbf24;margin-top:4px;display:block}
  .menu-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px;background:#121215;padding:8px;border-radius:16px;border:1px solid #27272a}
  .tab-btn{padding:10px 16px;border-radius:11px;font-size:13px;font-weight:700;background:transparent;color:#a1a1aa;border:none;cursor:pointer;transition:.2s}
  .tab-btn:hover{color:#fff}
  .tab-btn.active{background:linear-gradient(135deg,#fde68a,#f59e0b,#d97706);color:#09090b}
  .panel-card{background:#121215;border:1px solid #27272a;border-radius:20px;padding:28px;margin-bottom:28px}
  .field{margin-bottom:16px}
  .field label{display:block;font-size:12px;color:#a1a1aa;margin-bottom:6px;font-weight:600}
  .field input,.field select,.field textarea{width:100%;padding:12px 14px;border-radius:12px;background:#09090b;border:1px solid #27272a;color:#fff;font-size:14px;outline:none}
  .field input:focus,.field select:focus,.field textarea:focus{border-color:#fbbf24}
  .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .total-box{background:#09090b;border:1px solid rgba(251,191,36,.3);border-radius:12px;padding:12px 16px;display:flex;flex-direction:column;justify-content:center}
  .alert-ok{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.35);color:#34d399;padding:14px 18px;border-radius:14px;font-size:13px;margin-bottom:20px;font-weight:600}
  .alert-err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);color:#f87171;padding:14px 18px;border-radius:14px;font-size:13px;margin-bottom:20px;font-weight:600}
  .table-wrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;font-size:13px;text-align:left}
  th{color:#a1a1aa;font-weight:700;padding:12px;border-bottom:1px solid #27272a;font-size:12px;text-transform:uppercase}
  td{padding:13px 12px;border-bottom:1px solid #1f1f23}
  .status-badge{padding:4px 10px;border-radius:99px;font-size:11px;font-weight:800;background:rgba(245,158,11,.14);color:#fbbf24;text-transform:uppercase}
  .tab-pane{display:none}
  .tab-pane.active{display:block}
  @media(max-width:768px){.stats-row,.grid-2{grid-template-columns:1fr}}
</style>
</head>

  <div id="aba-historico" class="tab-pane <?= $abaAtiva === 'historico' ? 'active' : '' ?>">
    <div class="panel-card table-wrap">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h2 style="font-size:20px;font-weight:900">Histórico de Pedidos</h2>
        <a href="painel.php?sincronizar=1" class="btn btn-dark" style="padding:8px 14px;font-size:12px">🔄 Atualizar Status</a>
      </div>
      <table>
        <thead>
          <tr><th>Código</th><th>Plataforma</th><th>Serviço</th><th>Link</th><th>Qtd</th><th>Valor</th><th>Status</th><th>Data</th></tr>
        </thead>
        <tbody>
          <?php foreach ($listaPedidos as $p): ?>
          <tr>
            <td style="font-weight:800;color:#fbbf24"><?= htmlspecialchars($p['codigo_rastreio']) ?></td>
            <td><?= htmlspecialchars($p['plataforma']) ?></td>
            <td><?= htmlspecialchars($p['titulo']) ?></td>
            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#a1a1aa"><?= htmlspecialchars($p['link_destino']) ?></td>
            <td><?= number_format($p['quantidade'], 0, ',', '.') ?></td>
            <td style="font-weight:700">R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
            <td><span class="status-badge"><?= htmlspecialchars($p['status_entrega']) ?></span></td>
            <td style="color:#71717a"><?= date('d/m H:i', strtotime($p['criado_em'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($listaPedidos)): ?>
            <tr><td colspan="8" style="text-align:center;padding:30px;color:#71717a">Você ainda não fez nenhum pedido.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div id="aba-catalogo" class="tab-pane <?= $abaAtiva === 'catalogo' ? 'active' : '' ?>">
    <div class="panel-card table-wrap">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px">
        <h2 style="font-size:20px;font-weight:900">Tabela Completa de Serviços</h2>
        <input type="text" id="busca-srv" onkeyup="buscarTabela()" placeholder="Buscar serviço..." style="max-width:260px;padding:9px 12px;border-radius:10px;background:#09090b;border:1px solid #27272a;color:#fff;font-size:13px">
      </div>
      <table id="tabela-servicos">
        <thead>
          <tr><th>ID</th><th>Plataforma</th><th>Serviço</th><th>Qualidade</th><th>Mín / Máx</th><th>Preço por 1.000</th></tr>
        </thead>
        <tbody>
          <?php foreach ($servicos as $s): ?>
          <tr>
            <td style="color:#71717a">#<?= $s['id'] ?></td>
            <td><strong><?= htmlspecialchars($s['plataforma']) ?></strong></td>
            <td><?= htmlspecialchars($s['titulo']) ?></td>
            <td><span class="status-badge"><?= htmlspecialchars($s['qualidade']) ?></span></td>
            <td style="color:#a1a1aa"><?= number_format($s['minimo'], 0, ',', '.') ?> / <?= number_format($s['maximo'], 0, ',', '.') ?></td>
            <td style="font-weight:800;color:#fbbf24">R$ <?= number_format($s['preco_por_mil'], 2, ',', '.') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div id="aba-conta" class="tab-pane <?= $abaAtiva === 'conta' ? 'active' : '' ?>">
    <div class="grid-2">
      <div class="panel-card">
        <h2 style="font-size:18px;font-weight:900;margin-bottom:14px">Alterar Minha Senha</h2>
        <form method="POST">
          <input type="hidden" name="acao" value="alterar_senha">
          <div class="field"><label>Nova Senha (mínimo 6 caracteres)</label><input type="password" name="nova_senha" required placeholder="••••••••"></div>
          <button type="submit" class="btn btn-gold">Atualizar Senha</button>
        </form>
      </div>
      <div class="panel-card">
        <h2 style="font-size:18px;font-weight:900;margin-bottom:6px">Integração via API (Revenda)</h2>
        <p style="font-size:12px;color:#a1a1aa;margin-bottom:14px">Conecte seu painel externo usando sua API Key exclusiva:</p>
        <div class="field"><label>URL da API</label><input type="text" readonly value="https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'seusite.up.railway.app') ?>/api_v2.php"></div>
        <div class="field"><label>Sua Chave de API</label><input type="text" id="api-key-box" readonly value="<?= htmlspecialchars($user['api_key']) ?>"></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button type="button" onclick="copiarTexto('api-key-box')" class="btn btn-gold">Copiar API Key</button>
          <form method="POST"><input type="hidden" name="acao" value="nova_api_key"><button type="submit" class="btn btn-dark">Gerar Nova Chave</button></form>
        </div>
      </div>
    </div>
  </div>

  <div id="aba-suporte" class="tab-pane <?= $abaAtiva === 'suporte' ? 'active' : '' ?>">
    <div class="grid-2">
      <div class="panel-card">
        <h2 style="font-size:20px;font-weight:900;margin-bottom:6px">Abrir Chamado de Suporte</h2>
        <p style="font-size:13px;color:#a1a1aa;margin-bottom:18px">Envie sua dúvida ou código do pedido. O administrador responderá por aqui.</p>
        <form method="POST">
          <input type="hidden" name="acao" value="enviar_suporte">
          <div class="field"><label>Assunto / Código do Pedido</label><input type="text" name="assunto" required placeholder="Ex: Pedido #BS-..."></div>
          <div class="field"><label>Mensagem</label><textarea name="mensagem" rows="4" required placeholder="Descreva sua solicitação..."></textarea></div>
          <button type="submit" class="btn btn-gold">Enviar Solicitação &rarr;</button>
        </form>
      </div>

      <div class="panel-card">
        <h2 style="font-size:20px;font-weight:900;margin-bottom:14px">Minhas Solicitações</h2>
        <?php
        $tStmt = $pdo->prepare("SELECT * FROM tickets_suporte WHERE usuario_id = ? ORDER BY id DESC LIMIT 15");
        $tStmt->execute([$user['id']]);
        $meusTickets = $tStmt->fetchAll();
        foreach ($meusTickets as $tk): ?>
          <div style="background:#09090b;border:1px solid #27272a;border-radius:14px;padding:14px;margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <strong class="gold" style="font-size:13px"><?= htmlspecialchars($tk['assunto']) ?></strong>
              <span class="status-badge"><?= htmlspecialchars($tk['status']) ?></span>
            </div>
            <p style="font-size:12px;color:#d4d4d8"><?= nl2br(htmlspecialchars($tk['mensagem'])) ?></p>
            <?php if (!empty($tk['resposta_admin'])): ?>
              <div style="margin-top:10px;padding-top:10px;border-top:1px solid #27272a;font-size:12px;color:#34d399">
                <strong>Resposta do Admin:</strong> <?= nl2br(htmlspecialchars($tk['resposta_admin'])) ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (empty($meusTickets)): ?>
          <p style="font-size:13px;color:#71717a">Você ainda não abriu nenhuma solicitação de suporte.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<script>
function mudarAba(nome, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('aba-' + nome).classList.add('active');
  btn.classList.add('active');
}

function filtrarPainel() {
  const plat = document.getElementById('sel-plat').value;
  const sel = document.getElementById('sel-srv');
  let primeiro = null;
  Array.from(sel.options).forEach(opt => {
    const ok = plat === 'Todas' || opt.dataset.plat === plat;
    opt.hidden = !ok;
    if (ok && !primeiro) primeiro = opt;
  });
  if (primeiro) sel.value = primeiro.value;
  calcularPainel();
}

function calcularPainel() {
  const sel = document.getElementById('sel-srv');
  const opt = sel.options[sel.selectedIndex];
  if (!opt) return;
  const preco = parseFloat(opt.dataset.preco);
  const min = parseInt(opt.dataset.min);
  const max = parseInt(opt.dataset.max);
  const inp = document.getElementById('inp-qtd');
  inp.min = min;
  inp.max = max;
  document.getElementById('srv-limites').innerText = `(Mín: ${min} | Máx: ${max})`;
  document.getElementById('srv-desc').innerText = opt.dataset.desc;
  const qtd = parseInt(inp.value) || 0;
  const total = (preco / 1000) * qtd;
  document.getElementById('valor-total').innerText = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function buscarTabela() {
  const termo = document.getElementById('busca-srv').value.toLowerCase();
  document.querySelectorAll('#tabela-servicos tbody tr').forEach(tr => {
    tr.style.display = tr.innerText.toLowerCase().includes(termo) ? '' : 'none';
  });
}

function copiarTexto(id) {
  const el = document.getElementById(id);
  el.select();
  navigator.clipboard.writeText(el.value);
  alert('Copiado com sucesso!');
}

calcularPainel();
</script>
</body>
</html>
