<?php
require_once 'efi.php';

if (isset($_GET['checar_novidades'])) {
    header('Content-Type: application/json');
    $abertos = (int)$pdo->query("SELECT COUNT(*) FROM tickets_suporte WHERE status = 'aberto'")->fetchColumn();
    $totPed  = (int)$pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
    echo json_encode(['abertos' => $abertos, 'pedidos' => $totPed]);
    exit;
}

$userLogado = usuarioAtual($pdo);
if ($userLogado && $userLogado['usuario'] === 'admin123') {
    $_SESSION['admin_logado'] = true;
}

try {
    $pdo->exec("ALTER TABLE tickets_suporte ADD COLUMN avaliacao INTEGER DEFAULT 0");
} catch (Exception $e) {}

$msg = '';
if (isset($_GET['sinc'])) {
    $msg = "✓ Sincronização concluída! {$_GET['sinc']} pedido(s) atualizado(s).";
}

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
    } elseif ($acao === 'responder_ticket') {
        $resposta = trim($_POST['resposta'] ?? '');
        if ($resposta !== '') {
            $pdo->prepare("UPDATE tickets_suporte SET resposta_admin = ?, status = 'respondido' WHERE id = ?")
                ->execute([$resposta, (int)$_POST['ticket_id']]);
            $msg = '✓ Resposta enviada para o cliente!';
        }
    } elseif ($acao === 'encerrar_ticket') {
        $tid = (int)$_POST['ticket_id'];
        $atual = $pdo->prepare("SELECT resposta_admin FROM tickets_suporte WHERE id = ?");
        $atual->execute([$tid]);
        $respAnterior = trim((string)($atual->fetchColumn() ?: ''));
        $avisoFim = ($respAnterior !== '' ? $respAnterior . "\n\n" : '') . "🔒 Atendimento encerrado pela equipe Blindagem Social. Avalie nosso atendimento abaixo!";
        $pdo->prepare("UPDATE tickets_suporte SET resposta_admin = ?, status = 'encerrado' WHERE id = ?")
            ->execute([$avisoFim, $tid]);
        $msg = '✓ Chat encerrado! O cliente recebeu o aviso e a opção de avaliar com estrelas.';
    } elseif ($acao === 'excluir_ticket') {
        $pdo->prepare("DELETE FROM tickets_suporte WHERE id = ?")->execute([(int)$_POST['ticket_id']]);
        $msg = '✓ Ticket excluído permanentemente!';
    }

    elseif ($acao === 'novo_servico') {
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
            $msg = '✓ Preços reajustados!';
        }
    } elseif ($acao === 'excluir_servico') {
        $pdo->prepare("DELETE FROM servicos WHERE id = ?")->execute([(int)$_POST['id']]);
        $msg = '✓ Serviço excluído!';
    } elseif ($acao === 'limpar_servicos') {
        $pdo->exec("DELETE FROM servicos");
        $msg = '✓ Lista de serviços limpa!';
    } elseif ($acao === 'ajustar_saldo') {
        $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?")->execute([(float)$_POST['valor'], (int)$_POST['user_id']]);
        $msg = '✓ Saldo atualizado!';
    } elseif ($acao === 'reset_senha') {
        $nova = trim($_POST['nova_senha'] ?? '');
        if (strlen($nova) >= 6) {
            $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?")->execute([password_hash($nova, PASSWORD_DEFAULT), (int)$_POST['user_id']]);
            $msg = '✓ Senha alterada!';
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
                $msg = "✓ Pedido reenviado! ID Fornecedor: #{$resp['order']}";
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
$tickets     = $pdo->query("SELECT t.*, u.usuario AS nome_user, u.email FROM tickets_suporte t JOIN usuarios u ON t.usuario_id = u.id ORDER BY t.id DESC LIMIT 40")->fetchAll();
$faturamento = (float)$pdo->query("SELECT COALESCE(SUM(valor_total),0) FROM pedidos WHERE status_pagamento = 'pago'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Controle Total VIP — Blindagem Social</title>
<link rel="stylesheet" href="estilo.css">
</head>
<body>

<header class="topbar">
  <div class="container topbar-inner">
    <a href="index.php" class="brand"><span class="badge-logo">B</span><span>Admin <span class="gold">Blindagem Social</span></span></a>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="cron_status.php?voltar=admin" class="btn btn-gold" style="padding:8px 14px;font-size:12px">🔄 Sincronizar Pedidos</a>
      <a href="painel.php" class="btn btn-dark" style="padding:8px 14px;font-size:12px">Painel Cliente</a>
      <a href="index.php" class="btn btn-dark" style="padding:8px 14px;font-size:12px">Ver Site</a>
      <a href="auth.php?sair=1" class="btn btn-dark" style="padding:8px 14px;font-size:12px;color:#f87171">Sair</a>
    </div>
  </div>
</header>

<main class="container" style="padding-top:24px;padding-bottom:48px">
  <div class="stats-row" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:0">
    <div class="stat-box"><span>Saldo no Fornecedor SMM</span><strong><?= htmlspecialchars($saldoFornecedor) ?></strong></div>
    <div class="stat-box"><span>Faturamento Total</span><strong>R$ <?= number_format($faturamento, 2, ',', '.') ?></strong></div>
    <div class="stat-box"><span>Chamados de Suporte</span><strong style="color:#fff"><?= count($tickets) ?></strong></div>
    <div class="stat-box"><span>Serviços na Loja</span><strong style="color:#fff"><?= count($servicos) ?></strong></div>
  </div>

  <?php if ($msg): ?><div class="alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="panel-card table-wrap" style="border-color:rgba(251,191,36,.45)">
    <h2 style="font-size:18px;font-weight:900;margin-bottom:4px">💬 Central de Atendimento & Tickets</h2>
    <p style="font-size:12px;color:#a1a1aa;margin-bottom:14px">Responda, encerre a conversa (envia aviso + estrelas para o cliente) ou exclua tickets encerrados.</p>
    <table>
      <thead><tr><th>Cliente</th><th>Assunto & Mensagem</th><th>Status & Nota</th><th>Ações do Atendimento</th></tr></thead>
      <tbody>
        <?php foreach ($tickets as $t): ?>
        <tr>
          <td><strong>@<?= htmlspecialchars($t['nome_user']) ?></strong><br><span style="font-size:11px;color:#71717a"><?= htmlspecialchars($t['email']) ?></span></td>
          <td style="max-width:300px">
            <strong class="gold-solid"><?= htmlspecialchars($t['assunto']) ?></strong>
            <p style="font-size:12px;color:#d4d4d8;margin-top:4px"><?= nl2br(htmlspecialchars($t['mensagem'])) ?></p>
          </td>
          <td>
            <span class="status-badge"><?= htmlspecialchars($t['status']) ?></span>
            <?php if (!empty($t['avaliacao'])): ?>
              <div class="gold-solid" style="font-size:14px;margin-top:6px;letter-spacing:2px"><?= str_repeat('★', (int)$t['avaliacao']) . str_repeat('☆', 5 - (int)$t['avaliacao']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;flex-direction:column;gap:8px;min-width:260px">
              <?php if ($t['status'] !== 'encerrado'): ?>
                <form method="POST" style="display:flex;gap:6px">
                  <input type="hidden" name="acao" value="responder_ticket">
                  <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                  <input type="text" name="resposta" value="<?= htmlspecialchars($t['resposta_admin'] ?? '') ?>" placeholder="Responder cliente..." required style="padding:8px 10px;border-radius:10px;background:#070709;border:1px solid #27272a;color:#fff;font-size:12px;flex:1">
                  <button class="btn btn-gold" style="padding:8px 12px;font-size:12px">Enviar</button>
                </form>
              <?php endif; ?>
              <div style="display:flex;gap:6px">
                <?php if ($t['status'] !== 'encerrado'): ?>
                  <form method="POST" style="flex:1">
                    <input type="hidden" name="acao" value="encerrar_ticket">
                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                    <button class="btn btn-dark" style="width:100%;padding:7px 10px;font-size:11px;border-color:rgba(251,191,36,.4);color:#fbbf24">🔒 Encerrar Chat</button>
                  </form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Excluir este ticket?')" style="flex:1">
                  <input type="hidden" name="acao" value="excluir_ticket">
                  <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                  <button class="btn btn-dark" style="width:100%;padding:7px 10px;font-size:11px;color:#f87171">🗑️ Excluir</button>
                </form>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($tickets)): ?>
          <tr><td colspan="4" style="text-align:center;color:#71717a;padding:20px">Nenhum chamado de suporte no momento.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="panel-card">
    <h2 style="font-size:17px;font-weight:900;margin-bottom:12px">⚡ Importador Automático & Reajuste em Massa</h2>
    <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:end">
      <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="acao" value="importar_fornecedor">
        <div class="field" style="margin-bottom:0"><label>Margem Lucro (%)</label><input type="number" name="margem" value="100" min="1" style="width:120px;padding:9px"></div>
        <div class="field" style="margin-bottom:0"><label>Qtd Importar</label><input type="number" name="limite" value="40" min="1" max="150" style="width:110px;padding:9px"></div>
        <button class="btn btn-gold" style="padding:10px 16px">Importar do Fornecedor</button>
      </form>
      <form method="POST" style="display:flex;gap:8px;align-items:end">
        <input type="hidden" name="acao" value="reajustar_todos">
        <div class="field" style="margin-bottom:0"><label>Reajustar Todos (%)</label><input type="number" step="1" name="percentual" placeholder="10 ou -10" style="width:130px;padding:9px" required></div>
        <button class="btn btn-dark" style="padding:10px 14px">Aplicar %</button>
      </form>
      <form method="POST" onsubmit="return confirm('Apagar TODOS os serviços?')">
        <input type="hidden" name="acao" value="limpar_servicos">
        <button class="btn btn-dark" style="padding:10px 14px;color:#f87171">Limpar Tudo</button>
      </form>
    </div>
  </div>

  <div class="panel-card">
    <h2 style="font-size:17px;font-weight:900;margin-bottom:12px">➕ Cadastrar Serviço Manual</h2>
    <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;align-items:end">
      <input type="hidden" name="acao" value="novo_servico">
      <div class="field" style="margin-bottom:0"><label>ID Fornecedor</label><input type="number" name="provider_id" required style="padding:9px"></div>
      <div class="field" style="margin-bottom:0"><label>Plataforma</label><select name="plataforma" style="padding:9px"><option>Instagram</option><option>TikTok</option><option>YouTube</option><option>Facebook</option><option>Telegram</option><option>Kwai</option></select></div>
      <div class="field" style="margin-bottom:0"><label>Tipo</label><select name="tipo" style="padding:9px"><option>Seguidores</option><option>Curtidas</option><option>Visualizacoes</option></select></div>
      <div class="field" style="margin-bottom:0"><label>Qualidade</label><select name="qualidade" style="padding:9px"><option>Exclusivo</option><option>Premium</option><option>Avancado</option></select></div>
      <div class="field" style="margin-bottom:0"><label>Título na Loja</label><input type="text" name="titulo" required style="padding:9px"></div>
      <div class="field" style="margin-bottom:0"><label>Preço Venda / 1k</label><input type="number" step="0.01" name="preco" required style="padding:9px"></div>
      <div class="field" style="margin-bottom:0"><label>Mínimo</label><input type="number" name="minimo" value="100" required style="padding:9px"></div>
      <input type="hidden" name="descricao" value="Entrega automática sem senha. Deixe o perfil público.">
      <button class="btn btn-gold" style="padding:10px">Criar Serviço</button>
    </form>
  </div>

  <div class="panel-card table-wrap">
    <h2 style="font-size:17px;font-weight:900;margin-bottom:10px">📋 Gerenciar Serviços</h2>
    <table>
      <thead><tr><th>Plataforma</th><th>Título</th><th>ID Forn.</th><th>Preço / 1k</th><th>Mínimo</th><th>Status</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($servicos as $s): ?>
        <tr>
          <form method="POST">
            <input type="hidden" name="acao" value="editar_servico">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <td>
              <select name="plataforma" style="width:105px;padding:7px;background:#070709;border:1px solid #27272a;color:#fff;border-radius:8px">
                <?php foreach (['Instagram','TikTok','YouTube','Facebook','Telegram','Kwai'] as $pl): ?>
                  <option <?= $s['plataforma'] === $pl ? 'selected' : '' ?>><?= $pl ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="text" name="titulo" value="<?= htmlspecialchars($s['titulo']) ?>" style="min-width:190px;padding:7px;background:#070709;border:1px solid #27272a;color:#fff;border-radius:8px"></td>
            <td><input type="number" name="provider_id" value="<?= $s['provider_service_id'] ?>" style="width:75px;padding:7px;background:#070709;border:1px solid #27272a;color:#fff;border-radius:8px"></td>
            <td><input type="number" step="0.01" name="preco" value="<?= $s['preco_por_mil'] ?>" style="width:85px;padding:7px;background:#070709;border:1px solid #27272a;color:#fff;border-radius:8px"></td>
            <td><input type="number" name="minimo" value="<?= $s['minimo'] ?>" style="width:75px;padding:7px;background:#070709;border:1px solid #27272a;color:#fff;border-radius:8px"></td>
            <td>
              <select name="ativo" style="width:85px;padding:7px;background:#070709;border:1px solid #27272a;color:#fff;border-radius:8px">
                <option value="1" <?= $s['ativo'] ? 'selected' : '' ?>>Ativo</option>
                <option value="0" <?= !$s['ativo'] ? 'selected' : '' ?>>Pausado</option>
              </select>
            </td>
            <td style="display:flex;gap:5px">
              <button class="btn btn-gold" style="padding:7px 11px;font-size:11px">Salvar</button>
          </form>
          <form method="POST" onsubmit="return confirm('Excluir?')">
            <input type="hidden" name="acao" value="excluir_servico">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button class="btn btn-dark" style="padding:7px 10px;font-size:11px;color:#f87171">X</button>
          </form>
            </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="panel-card table-wrap">
    <h2 style="font-size:17px;font-weight:900;margin-bottom:10px">👥 Gerenciar Clientes</h2>
    <table>
      <thead><tr><th>Usuário</th><th>E-mail</th><th>Saldo</th><th>Ajustar Saldo (R$)</th><th>Nova Senha</th><th>Excluir</th></tr></thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
        <tr>
          <td><strong>@<?= htmlspecialchars($u['usuario']) ?></strong></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td class="gold-solid"><strong>R$ <?= number_format($u['saldo'], 2, ',', '.') ?></strong></td>
          <td>
            <form method="POST" style="display:flex;gap:5px;max-width:190px">
              <input type="hidden" name="acao" value="ajustar_saldo">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="number" step="0.01" name="valor" placeholder="10 ou -5" required style="padding:7px;background:#070709;border:1px solid #27272a;color:#fff;border-radius:8px;width:100px">
              <button class="btn btn-gold" style="padding:7px 11px;font-size:11px">OK</button>
            </form>
          </td>
          <td>
            <form method="POST" style="display:flex;gap:5px;max-width:190px">
              <input type="hidden" name="acao" value="reset_senha">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="text" name="nova_senha" placeholder="Mín. 6 dígitos" required style="padding:7px;background:#070709;border:1px solid #27272a;color:#fff;border-radius:8px;width:110px">
              <button class="btn btn-dark" style="padding:7px 11px;font-size:11px">Trocar</button>
            </form>
          </td>
          <td>
            <form method="POST" onsubmit="return confirm('Excluir?')">
              <input type="hidden" name="acao" value="excluir_usuario">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button class="btn btn-dark" style="padding:7px 11px;font-size:11px;color:#f87171">Excluir</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="panel-card table-wrap" style="margin-bottom:0">
    <h2 style="font-size:17px;font-weight:900;margin-bottom:10px">📦 Pedidos & Reenvio API</h2>
    <table>
      <thead><tr><th>Código</th><th>Cliente</th><th>Serviço</th><th>Link</th><th>Qtd</th><th>Valor</th><th>ID Forn.</th><th>Status</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($pedidos as $p): ?>
        <tr>
          <td class="gold-solid"><strong><?= htmlspecialchars($p['codigo_rastreio']) ?></strong></td>
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
              <select name="status" style="width:110px;padding:7px;background:#070709;border:1px solid #27272a;color:#fff;border-radius:8px">
                <?php foreach (['aguardando','processando','concluido','cancelado'] as $st): ?>
                  <option value="<?= $st ?>" <?= $p['status_entrega'] === $st ? 'selected' : '' ?>><?= strtoupper($st) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-dark" style="padding:7px 10px;font-size:11px">OK</button>
            </form>
          </td>
          <td>
            <form method="POST">
              <input type="hidden" name="acao" value="reenviar_pedido">
              <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
              <button class="btn btn-gold" style="padding:7px 11px;font-size:11px">Reenviar API</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<div id="alerta-novo" style="display:none;position:fixed;bottom:22px;right:22px;z-index:99;background:linear-gradient(135deg,#fef08a,#f59e0b);color:#050507;padding:16px 20px;border-radius:16px;font-weight:900;font-size:13px;box-shadow:0 16px 40px rgba(245,158,11,.5);cursor:pointer" onclick="location.reload()">
  🔔 Novo chamado de suporte ou pedido recebido! Clique para atualizar.
</div>

<script>
let abertosAtual = <?= (int)$pdo->query("SELECT COUNT(*) FROM tickets_suporte WHERE status = 'aberto'")->fetchColumn() ?>;
let pedidosAtual = <?= (int)$pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn() ?>;

function tocarAviso() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(880, ctx.currentTime);
    osc.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.18);
  } catch (e) {}
}

setInterval(() => {
  fetch('admin.php?checar_novidades=1')
    .then(r => r.json())
    .then(d => {
      if (d.abertos > abertosAtual || d.pedidos > pedidosAtual) {
        document.getElementById('alerta-novo').style.display = 'block';
        tocarAviso();
        abertosAtual = d.abertos;
        pedidosAtual = d.pedidos;
      }
    }).catch(() => {});
}, 10000);
</script>
</body>
</html>
