<?php
require_once 'efi.php';

// Aceita acesso se estiver logado como admin123 pelo login normal ou via sessão admin
$userLogado = usuarioAtual($pdo);
if ($userLogado && $userLogado['usuario'] === 'admin123') {
    $_SESSION['admin_logado'] = true;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

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

        if (is_array($lista) && isset($lista['service'])) {
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
            $msg = '✓ Senha do cliente alterada!';
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
        $msg = '✓ Status atualizado!';
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

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Controle Total — Blindagem Social</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  body{background:#09090b;color:#fafafa;padding:24px}
  .container{max-width:1240px;margin:0 auto}
  .gold{color:#fbbf24}
  .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:24px}
  .kpi{background:#121215;border:1px solid #27272a;border-radius:16px;padding:18px}
  .kpi span{font-size:11px;color:#a1a1aa;text-transform:uppercase;font-weight:700}
  .kpi strong{display:block;font-size:22px;font-weight:900;color:#fbbf24;margin-top:4px}
  .card{background:#121215;border:1px solid #27272a;border-radius:18px;padding:22px;margin-bottom:24px;overflow-x:auto}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;align-items:end}
  label{display:block;font-size:11px;color:#a1a1aa;margin-bottom:4px}
  input,select{width:100%;padding:8px 10px;border-radius:10px;background:#09090b;border:1px solid #27272a;color:#fff;font-size:12px}
  .btn{padding:8px 13px;border-radius:10px;font-weight:800;font-size:12px;border:none;cursor:pointer;text-decoration:none;display:inline-block}
  .btn-gold{background:linear-gradient(135deg,#fbbf24,#d97706);color:#09090b}
  .btn-red{background:rgba(239,68,68,.16);color:#f87171}
  .btn-dark{background:#18181b;color:#fff;border:1px solid #27272a}
  .msg{background:rgba(245,158,11,.14);border:1px solid rgba(245,158,11,.35);color:#fbbf24;padding:12px 16px;border-radius:12px;font-size:13px;font-weight:700;margin-bottom:20px}
  table{width:100%;border-collapse:collapse;font-size:12px;text-align:left;margin-top:12px}
  th{color:#a1a1aa;padding:10px;border-bottom:1px solid #27272a}
  td{padding:9px 8px;border-bottom:1px solid #1f1f23}
</style>
</head>
<body>
<div class="container">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <h1 style="font-size:24px;font-weight:900">Controle Total — <span class="gold">Blindagem Social</span></h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="cron_status.php" target="_blank" class="btn btn-dark">Sincronizar Pedidos</a>
      <a href="painel.php" class="btn btn-dark">Painel Cliente</a>
      <a href="index.php" class="btn btn-dark">Ver Site</a>
      <a href="auth.php?sair=1" class="btn btn-red">Sair</a>
    </div>
  </div>

  <div class="kpis">
    <div class="kpi"><span>Saldo no Fornecedor SMM</span><strong><?= htmlspecialchars($saldoFornecedor) ?></strong></div>
    <div class="kpi"><span>Faturamento em Pedidos</span><strong>R$ <?= number_format($faturamento, 2, ',', '.') ?></strong></div>
    <div class="kpi"><span>Clientes Cadastrados</span><strong style="color:#fff"><?= count($usuarios) ?></strong></div>
    <div class="kpi"><span>Serviços na Loja</span><strong style="color:#fff"><?= count($servicos) ?></strong></div>
  </div>

  <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="card" style="border-color:rgba(245,158,11,.4)">
    <h2 style="font-size:16px;margin-bottom:12px">Importador Automático & Reajuste em Massa</h2>
    <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:end">
      <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="acao" value="importar_fornecedor">
        <div><label>Margem Lucro (%)</label><input type="number" name="margem" value="100" min="1" style="width:120px"></div>
        <div><label>Qtd Importar</label><input type="number" name="limite" value="40" min="1" max="150" style="width:110px"></div>
        <button class="btn btn-gold">Importar do Fornecedor</button>
      </form>
      <form method="POST" style="display:flex;gap:8px;align-items:end">
        <input type="hidden" name="acao" value="reajustar_todos">
        <div><label>Reajustar Todos (%)</label><input type="number" step="1" name="percentual" placeholder="10 ou -10" style="width:130px" required></div>
        <button class="btn btn-dark">Aplicar %</button>
      </form>
      <form method="POST" onsubmit="return confirm('Apagar TODOS os serviços?')">
        <input type="hidden" name="acao" value="limpar_servicos">
        <button class="btn btn-red">Limpar Tudo</button>
      </form>
    </div>
  </div>

  <div class="card">
    <h2 style="font-size:16px;margin-bottom:12px">Cadastrar Serviço Manual</h2>
    <form method="POST" class="grid">
      <input type="hidden" name="acao" value="novo_servico">
      <div><label>ID Fornecedor</label><input type="number" name="provider_id" required></div>
      <div><label>Plataforma</label><select name="plataforma"><option>Instagram</option><option>TikTok</option><option>YouTube</option><option>Facebook</option><option>Telegram</option><option>Kwai</option></select></div>
      <div><label>Tipo</label><select name="tipo"><option>Seguidores</option><option>Curtidas</option><option>Visualizacoes</option></select></div>
      <div><label>Qualidade</label><select name="qualidade"><option>Exclusivo</option><option>Premium</option><option>Avancado</option></select></div>
      <div><label>Título na Loja</label><input type="text" name="titulo" required></div>
      <div><label>Preço Venda / 1k</label><input type="number" step="0.01" name="preco" required></div>
      <div><label>Mínimo</label><input type="number" name="minimo" value="100" required></div>
      <input type="hidden" name="descricao" value="Entrega automática sem senha. Deixe o perfil público.">
      <button class="btn btn-gold">Criar Serviço</button>
    </form>
  </div>

  <div class="card">
    <h2 style="font-size:16px">Gerenciar Serviços</h2>
    <table>
      <thead><tr><th>Plataforma</th><th>Título</th><th>ID Forn.</th><th>Preço / 1k</th><th>Mínimo</th><th>Status</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($servicos as $s): ?>
        <tr>
          <form method="POST">
            <input type="hidden" name="acao" value="editar_servico">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <td>
              <select name="plataforma" style="width:105px">
                <?php foreach (['Instagram','TikTok','YouTube','Facebook','Telegram','Kwai'] as $pl): ?>
                  <option <?= $s['plataforma'] === $pl ? 'selected' : '' ?>><?= $pl ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="text" name="titulo" value="<?= htmlspecialchars($s['titulo']) ?>" style="min-width:190px"></td>
            <td><input type="number" name="provider_id" value="<?= $s['provider_service_id'] ?>" style="width:75px"></td>
            <td><input type="number" step="0.01" name="preco" value="<?= $s['preco_por_mil'] ?>" style="width:85px"></td>
            <td><input type="number" name="minimo" value="<?= $s['minimo'] ?>" style="width:75px"></td>
            <td>
              <select name="ativo" style="width:85px">
                <option value="1" <?= $s['ativo'] ? 'selected' : '' ?>>Ativo</option>
                <option value="0" <?= !$s['ativo'] ? 'selected' : '' ?>>Pausado</option>
              </select>
            </td>
            <td style="display:flex;gap:5px">
              <button class="btn btn-gold">Salvar</button>
          </form>
          <form method="POST" onsubmit="return confirm('Excluir?')">
            <input type="hidden" name="acao" value="excluir_servico">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button class="btn btn-red">X</button>
          </form>
            </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2 style="font-size:16px">Gerenciar Clientes</h2>
    <table>
      <thead><tr><th>Usuário</th><th>E-mail</th><th>Saldo</th><th>Ajustar Saldo (R$)</th><th>Nova Senha</th><th>Excluir</th></tr></thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
        <tr>
          <td><strong>@<?= htmlspecialchars($u['usuario']) ?></strong></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td class="gold"><strong>R$ <?= number_format($u['saldo'], 2, ',', '.') ?></strong></td>
          <td>
            <form method="POST" style="display:flex;gap:5px;max-width:190px">
              <input type="hidden" name="acao" value="ajustar_saldo">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="number" step="0.01" name="valor" placeholder="10 ou -5" required>
              <button class="btn btn-gold">OK</button>
            </form>
          </td>
          <td>
            <form method="POST" style="display:flex;gap:5px;max-width:190px">
              <input type="hidden" name="acao" value="reset_senha">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="text" name="nova_senha" placeholder="Mín. 6 dígitos" required>
              <button class="btn btn-dark">Trocar</button>
            </form>
          </td>
          <td>
            <form method="POST" onsubmit="return confirm('Excluir?')">
              <input type="hidden" name="acao" value="excluir_usuario">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button class="btn btn-red">Excluir</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2 style="font-size:16px">Pedidos & Reenvio API</h2>
    <table>
      <thead><tr><th>Código</th><th>Cliente</th><th>Serviço</th><th>Link</th><th>Qtd</th><th>Valor</th><th>ID Forn.</th><th>Status</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($pedidos as $p): ?>
        <tr>
          <td class="gold"><strong><?= htmlspecialchars($p['codigo_rastreio']) ?></strong></td>
          <td>@<?= htmlspecialchars($p['nome_user'] ?? 'visitante') ?></td>
          <td><?= htmlspecialchars($p['titulo']) ?></td>
          <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($p['link_destino']) ?></td>
          <td><?= $p['quantidade'] ?></td>
          <td>R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
          <td><?= htmlspecialchars($p['provider_order_id'] ?? 'Pendente') ?></td>
          <td>
            <form method="POST" style="display:flex;gap:4px">
              <input type="hidden" name="acao" value="status_pedido">
              <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
              <select name="status" style="width:110px">
                <?php foreach (['aguardando','processando','concluido','cancelado'] as $st): ?>
                  <option value="<?= $st ?>" <?= $p['status_entrega'] === $st ? 'selected' : '' ?>><?= strtoupper($st) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-dark">OK</button>
            </form>
          </td>
          <td>
            <form method="POST">
              <input type="hidden" name="acao" value="reenviar_pedido">
              <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
              <button class="btn btn-gold">Reenviar API</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
