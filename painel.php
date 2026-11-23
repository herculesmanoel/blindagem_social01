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
<link rel="stylesheet" href="estilo.css">
</head>
<body>

<div class="live-bar">
  <div class="live-track">
    <span class="live-item"><span class="dot-live"></span> <strong>@lucas.mendes</strong> acabou de pedir <strong class="gold">1.000 Seguidores Instagram BR</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@camila_oliveira</strong> acabou de pedir <strong class="gold">500 Curtidas Reais</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@studiobella_sp</strong> adicionou <strong class="gold">R$ 50,00 via PIX</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@rafael.trader</strong> acabou de pedir <strong class="gold">2.000 Seguidores TikTok</strong></span>
  </div>
</div>

<header class="topbar">
  <div class="container topbar-inner">
    <a href="index.php" class="brand"><span class="badge-logo">B</span><span>Blindagem <span class="gold">Social</span></span></a>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <?php if ($user['usuario'] === 'admin123'): ?>
        <a href="admin.php" class="btn btn-gold" style="padding:8px 14px;font-size:12px">⚙️ Controle Admin</a>
      <?php endif; ?>
      <div class="saldo-pill">Saldo: R$ <?= number_format($user['saldo'], 2, ',', '.') ?></div>
      <span style="font-size:12px;color:#a1a1aa">@<?= htmlspecialchars($user['usuario']) ?></span>
      <a href="auth.php?sair=1" class="btn btn-dark" style="padding:8px 14px;font-size:12px">Sair</a>
    </div>
  </div>
</header>

<main class="container" style="flex:1;padding-bottom:40px">
  <div class="stats-row">
    <div class="stat-box"><span>Saldo Disponível</span><strong>R$ <?= number_format($user['saldo'], 2, ',', '.') ?></strong></div>
    <div class="stat-box"><span>Pedidos Realizados</span><strong style="color:#fff"><?= count($listaPedidos) ?></strong></div>
    <div class="stat-box"><span>Total Investido</span><strong style="color:#fff">R$ <?= number_format($totalGasto, 2, ',', '.') ?></strong></div>
  </div>

  <?php if ($msgSucesso): ?><div class="alert-ok">✓ <?= htmlspecialchars($msgSucesso) ?></div><?php endif; ?>
  <?php if ($msgErro): ?><div class="alert-err">⚠ <?= htmlspecialchars($msgErro) ?></div><?php endif; ?>

  <div class="menu-tabs">
    <button type="button" class="tab-btn <?= $abaAtiva === 'pedido' ? 'active' : '' ?>" onclick="mudarAba('pedido', this)">🚀 Novo Pedido</button>
    <button type="button" class="tab-btn <?= $abaAtiva === 'saldo' ? 'active' : '' ?>" onclick="mudarAba('saldo', this)">💳 Adicionar Saldo (PIX)</button>
    <button type="button" class="tab-btn <?= $abaAtiva === 'historico' ? 'active' : '' ?>" onclick="mudarAba('historico', this)">📦 Meus Pedidos</button>
    <button type="button" class="tab-btn <?= $abaAtiva === 'catalogo' ? 'active' : '' ?>" onclick="mudarAba('catalogo', this)">📋 Tabela de Serviços</button>
    <button type="button" class="tab-btn <?= $abaAtiva === 'conta' ? 'active' : '' ?>" onclick="mudarAba('conta', this)">🔑 Conta & API</button>
    <button type="button" class="tab-btn <?= $abaAtiva === 'suporte' ? 'active' : '' ?>" onclick="mudarAba('suporte', this)">💬 Suporte</button>
  </div>

  <div id="aba-pedido" class="tab-pane <?= $abaAtiva === 'pedido' ? 'active' : '' ?>">
    <div class="panel-card">
      <h2 style="font-size:20px;font-weight:900;margin-bottom:4px">Fazer Novo Pedido</h2>
      <p style="font-size:13px;color:#a1a1aa;margin-bottom:20px">Deixe o perfil em modo público antes de enviar. Nunca solicitamos sua senha.</p>

      <form method="POST">
        <input type="hidden" name="acao" value="novo_pedido">
        <div class="field">
          <label>Plataforma</label>
          <select id="sel-plat" onchange="filtrarPainel()">
            <option value="Todas">Todas as plataformas</option>
            <option value="Instagram">Instagram</option>
            <option value="TikTok">TikTok</option>
            <option value="YouTube">YouTube</option>
            <option value="Facebook">Facebook</option>
            <option value="Telegram">Telegram</option>
            <option value="Kwai">Kwai</option>
          </select>
        </div>
        <div class="field">
          <label>Escolha o Serviço</label>
          <select name="servico_id" id="sel-srv" onchange="calcularPainel()" required>
            <?php foreach ($servicos as $s): ?>
              <option value="<?= $s['id'] ?>" data-plat="<?= htmlspecialchars($s['plataforma']) ?>" data-preco="<?= $s['preco_por_mil'] ?>" data-min="<?= $s['minimo'] ?>" data-max="<?= $s['maximo'] ?>" data-desc="<?= htmlspecialchars($s['descricao']) ?>">
                [<?= htmlspecialchars($s['plataforma']) ?>] <?= htmlspecialchars($s['titulo']) ?> — R$ <?= number_format($s['preco_por_mil'], 2, ',', '.') ?> / 1.000
              </option>
            <?php endforeach; ?>
          </select>
          <p id="srv-desc" style="font-size:12px;color:#fbbf24;margin-top:6px"></p>
        </div>
        <div class="field">
          <label>Link do Perfil ou Publicação</label>
          <input type="text" name="link" placeholder="https://instagram.com" required>
        </div>
        <div class="grid-2" style="margin-bottom:18px">
          <div class="field" style="margin-bottom:0">
            <label>Quantidade <span id="srv-limites" style="color:#71717a"></span></label>
            <input type="number" name="quantidade" id="inp-qtd" value="1000" min="100" oninput="calcularPainel()" required>
          </div>
          <div class="total-box">
            <span style="font-size:11px;color:#a1a1aa">Valor total do pedido</span>
            <strong id="valor-total" style="font-size:22px;color:#fbbf24">R$ 0,00</strong>
          </div>
        </div>
        <button type="submit" class="btn btn-gold" style="width:100%;padding:14px;font-size:15px">Confirmar e Enviar Pedido &rarr;</button>
      </form>
    </div>
  </div>

  <div id="aba-saldo" class="tab-pane <?= $abaAtiva === 'saldo' ? 'active' : '' ?>">
    <div class="panel-card" style="max-width:560px">
      <h2 style="font-size:20px;font-weight:900;margin-bottom:4px">Adicionar Saldo via PIX (Efí)</h2>
      <p style="font-size:13px;color:#a1a1aa;margin-bottom:20px">Recarga automática 24/7 a partir de <strong class="gold">R$ 1,00</strong>.</p>

      <form method="POST">
        <input type="hidden" name="acao" value="gerar_pix">
        <div class="field">
          <label>Valor da Recarga (R$)</label>
          <input type="number" step="0.01" min="1" name="valor" value="10.00" required>
        </div>
        <button type="submit" class="btn btn-gold" style="width:100%">Gerar QR Code & PIX Copia e Cola &rarr;</button>
      </form>

      <?php if ($pixGerado): ?>
        <div style="margin-top:22px;padding-top:20px;border-top:1px solid #27272a;text-align:center">
          <p style="font-size:14px;color:#34d399;font-weight:800;margin-bottom:12px">✓ PIX de R$ <?= number_format($pixGerado['valor'], 2, ',', '.') ?> gerado!</p>
          <img src="https://qrserver.com<?= urlencode($pixGerado['codigo']) ?>" alt="QR Code PIX" style="border-radius:14px;border:4px solid #fbbf24;margin-bottom:14px;background:#fff;padding:6px">
          <div class="field" style="text-align:left">
            <label>PIX Copia e Cola:</label>
            <textarea id="pix-code" rows="3" readonly><?= htmlspecialchars($pixGerado['codigo']) ?></textarea>
          </div>
          <div style="display:flex;gap:10px">
            <button type="button" onclick="copiarTexto('pix-code')" class="btn btn-gold" style="flex:1">Copiar Código PIX</button>
            <a href="painel.php?aba=saldo" class="btn btn-dark" style="flex:1">Já Paguei / Atualizar</a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

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
