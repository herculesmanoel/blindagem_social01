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
    }

    if ($acao === 'gerar_pix') {
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
    }

    if ($acao === 'nova_api_key') {
        $abaAtiva = 'api';
        $novaKey = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE usuarios SET api_key = ? WHERE id = ?")->execute([$novaKey, $user['id']]);
        $user['api_key'] = $novaKey;
        $msgSucesso = 'Nova chave de API gerada com sucesso!';
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
<title>Painel do Cliente — Blindagem Social</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  body{background:#09090b;color:#fafafa;min-height:100vh;display:flex;flex-direction:column}
  a{text-decoration:none;color:inherit}
  .container{max-width:1180px;margin:0 auto;padding:0 20px;width:100%}
  .topbar{background:#121215;border-bottom:1px solid #27272a;position:sticky;top:0;z-index:40}
  .topbar-inner{height:68px;display:flex;align-items:center;justify-content:space-between;gap:12px}
  .brand{display:flex;align-items:center;gap:10px;font-weight:900;font-size:18px}
  .badge-logo{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#fbbf24,#d97706);color:#09090b;display:flex;align-items:center;justify-content:center;font-weight:900}
  .gold{color:#fbbf24}
  .saldo-pill{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.35);padding:8px 14px;border-radius:12px;font-size:13px;font-weight:700;color:#fbbf24}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 20px;border-radius:12px;font-weight:800;font-size:13px;border:none;cursor:pointer;transition:.2s}
  .btn-gold{background:linear-gradient(135deg,#fbbf24,#d97706);color:#09090b;box-shadow:0 6px 20px rgba(245,158,11,.22)}
  .btn-gold:hover{filter:brightness(1.08)}
  .btn-dark{background:#18181b;color:#e4e4e7;border:1px solid #27272a}
  .btn-dark:hover{border-color:#fbbf24;color:#fbbf24}
  .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:24px 0}
  .stat-box{background:#121215;border:1px solid #27272a;border-radius:16px;padding:18px 20px}
  .stat-box span{font-size:12px;color:#a1a1aa;display:block}
  .stat-box strong{font-size:22px;font-weight:900;color:#fbbf24;margin-top:4px;display:block}
  .menu-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px;background:#121215;padding:8px;border-radius:16px;border:1px solid #27272a}
  .tab-btn{padding:10px 18px;border-radius:11px;font-size:13px;font-weight:700;background:transparent;color:#a1a1aa;border:none;cursor:pointer;transition:.2s}
  .tab-btn:hover{color:#fff}
  .tab-btn.active{background:linear-gradient(135deg,#fbbf24,#d97706);color:#09090b}
  .panel-card{background:#121215;border:1px solid #27272a;border-radius:20px;padding:28px;margin-bottom:28px}
  .field{margin-bottom:16px}
  .field label{display:block;font-size:12px;color:#a1a1aa;margin-bottom:6px;font-weight:600}
  .field input,.field select,.field textarea{width:100%;padding:12px 14px;border-radius:12px;background:#09090b;border:1px solid #27272a;color:#fff;font-size:14px;outline:none}
  .field input:focus,.field select:focus{border-color:#fbbf24}
  .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .total-box{background:#09090b;border:1px solid #27272a;border-radius:12px;padding:12px 16px;display:flex;flex-direction:column;justify-content:center}
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
<body>

<header class="topbar">
  <div class="container topbar-inner">
    <a href="index.php" class="brand"><span class="badge-logo">B</span><span>Blindagem <span class="gold">Social</span></span></a>
    <div style="display:flex;align-items:center;gap:12px">
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
    <button type="button" class="tab-btn <?= $abaAtiva === 'api' ? 'active' : '' ?>" onclick="mudarAba('api', this)">🔑 API & Suporte</button>
  </div>

  <!-- ABA 1: NOVO PEDIDO -->
  <div id="aba-pedido" class="tab-pane <?= $abaAtiva === 'pedido' ? 'active' : '' ?>">
    <div class="panel-card">
      <h2 style="font-size:20px;font-weight:800;margin-bottom:4px">Fazer Novo Pedido</h2>
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

  <!-- ABA 2: ADICIONAR SALDO PIX -->
  <div id="aba-saldo" class="tab-pane <?= $abaAtiva === 'saldo' ? 'active' : '' ?>">
    <div class="panel-card" style="max-width:560px">
      <h2 style="font-size:20px;font-weight:800;margin-bottom:4px">Adicionar Saldo via PIX (Efí)</h2>
      <p style="font-size:13px;color:#a1a1aa;margin-bottom:20px">Recarga automática 24/7 a partir de <strong class="gold">R$ 1,00</strong>.</p>

      <form method="POST">
        <input type="hidden" name="acao" value="gerar_pix">
        <div class="field">
          <label>Valor da Recarga (R$)</label>
          <input type="number" step="0.01" min="1" name="valor" value="10.00" required>
        </div>
        <button type="submit" class="btn btn-gold" style="width:100%">Gerar QR / PIX Copia e Cola &rarr;</button>
      </form>

      <?php if ($pixGerado): ?>
        <div style="margin-top:22px;padding-top:20px;border-top:1px solid #27272a">
          <p style="font-size:13px;color:#34d399;font-weight:700;margin-bottom:8px">✓ PIX de R$ <?= number_format($pixGerado['valor'], 2, ',', '.') ?> gerado! Copie o código abaixo:</p>
          <div class="field">
            <textarea id="pix-code" rows="3" readonly><?= htmlspecialchars($pixGerado['codigo']) ?></textarea>
          </div>
          <button type="button" onclick="copiarTexto('pix-code')" class="btn btn-gold" style="width:100%">Copiar Código PIX</button>
          <p style="font-size:12px;color:#a1a1aa;margin-top:10px;text-align:center">Após efetuar o pagamento no app do banco, atualize esta página para ver seu novo saldo.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ABA 3: MEUS PEDIDOS -->
  <div id="aba-historico" class="tab-pane <?= $abaAtiva === 'historico' ? 'active' : '' ?>">
    <div class="panel-card table-wrap">
      <h2 style="font-size:20px;font-weight:800;margin-bottom:16px">Histórico de Pedidos</h2>
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

  <!-- ABA 4: TABELA DE SERVIÇOS -->
  <div id="aba-catalogo" class="tab-pane <?= $abaAtiva === 'catalogo' ? 'active' : '' ?>">
    <div class="panel-card table-wrap">
      <h2 style="font-size:20px;font-weight:800;margin-bottom:16px">Tabela Completa de Serviços</h2>
      <table>
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

  <!-- ABA 5: API & SUPORTE -->
  <div id="aba-api" class="tab-pane <?= $abaAtiva === 'api' ? 'active' : '' ?>">
    <div class="panel-card" style="max-width:640px">
      <h2 style="font-size:20px;font-weight:800;margin-bottom:6px">Integração via API (Revendedores)</h2>
      <p style="font-size:13px;color:#a1a1aa;margin-bottom:18px">Conecte seu painel SMM externo usando a URL e sua chave exclusiva abaixo:</p>
      <div class="field">
        <label>URL da API (POST / GET)</label>
        <input type="text" readonly value="https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'seusite.up.railway.app') ?>/api_v2.php">
      </div>
      <div class="field">
        <label>Sua Chave de API (API Key)</label>
        <input type="text" id="api-key-box" readonly value="<?= htmlspecialchars($user['api_key']) ?>">
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="button" onclick="copiarTexto('api-key-box')" class="btn btn-gold">Copiar API Key</button>
        <form method="POST">
          <input type="hidden" name="acao" value="nova_api_key">
          <button type="submit" class="btn btn-dark">Gerar Nova Chave</button>
        </form>
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
