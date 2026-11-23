<?php
require_once 'config.php';

if (isset($_POST['senha'])) {
    if ($_POST['senha'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logado'] = true;
    } else {
        $erro = 'Senha incorreta.';
    }
}
if (isset($_GET['sair'])) {
    unset($_SESSION['admin_logado']);
    header('Location: admin.php');
    exit;
}

if (empty($_SESSION['admin_logado'])): ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Blindagem Social</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  body{background:#09090b;color:#fafafa;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .box{background:#121215;border:1px solid #27272a;border-radius:20px;max-width:380px;width:100%;padding:28px}
  input{width:100%;padding:12px;border-radius:12px;background:#09090b;border:1px solid #27272a;color:#fff;margin:12px 0}
  button{width:100%;padding:12px;border-radius:12px;background:linear-gradient(135deg,#fbbf24,#d97706);color:#09090b;font-weight:800;border:none;cursor:pointer}
</style>
</head>
<body>
  <form method="POST" class="box">
    <h1 style="font-size:20px;font-weight:900">Admin <span style="color:#fbbf24">Blindagem Social</span></h1>
    <?php if (!empty($erro)): ?><p style="color:#f87171;font-size:12px;margin-top:8px"><?= $erro ?></p><?php endif; ?>
    <input type="password" name="senha" placeholder="Senha do administrador" required>
    <button>Acessar Painel Admin &rarr;</button>
  </form>
</body>
</html>
<?php exit; endif;

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    // IMPORTAÇÃO AUTOMÁTICA DO FORNECEDOR COM MARGEM DE LUCRO (PADRÃO 100%)
    if ($acao === 'importar_fornecedor') {
        $margem = max(0, (float)($_POST['margem'] ?? 100)); // 100 = +100% de lucro (2x o custo)
        $multiplicador = 1 + ($margem / 100);
        $limite = max(1, min(100, (int)($_POST['limite'] ?? 30)));

        $ch = curl_init(SMM_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'key'    => SMM_API_KEY,
                'action' => 'services'
            ])
        ]);
        $listaFornecedor = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (is_array($listaFornecedor) && !empty($listaFornecedor[0]['service'])) {
            $importados = 0;
            $ins = $pdo->prepare("INSERT INTO servicos (provider_service_id, plataforma, tipo, qualidade, titulo, descricao, preco_por_mil, minimo, maximo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($listaFornecedor as $item) {
                if ($importados >= $limite) break;
                $sid   = (int)$item['service'];
                $nome  = trim($item['name'] ?? 'Serviço SMM');
                $cat   = trim($item['category'] ?? '');
                $custo = (float)($item['rate'] ?? 0);
                if ($custo <= 0) continue;

                // Evita duplicar se o ID do fornecedor já estiver cadastrado
                $existe = $pdo->prepare("SELECT id FROM servicos WHERE provider_service_id = ?");
                $existe->execute([$sid]);
                if ($existe->fetch()) continue;

                $textoBusca = mb_strtolower($nome . ' ' . $cat);
                $plataforma = 'Instagram';
                if (str_contains($textoBusca, 'tiktok')) $plataforma = 'TikTok';
                elseif (str_contains($textoBusca, 'youtube')) $plataforma = 'YouTube';
                elseif (str_contains($textoBusca, 'facebook')) $plataforma = 'Facebook';
                elseif (str_contains($textoBusca, 'telegram')) $plataforma = 'Telegram';
                elseif (str_contains($textoBusca, 'kwai')) $plataforma = 'Kwai';

                $tipo = 'Seguidores';
                if (str_contains($textoBusca, 'curtida') || str_contains($textoBusca, 'like')) $tipo = 'Curtidas';
                elseif (str_contains($textoBusca, 'visualiza') || str_contains($textoBusca, 'view')) $tipo = 'Visualizacoes';

                $qualidade = str_contains($textoBusca, 'brasil') || str_contains($textoBusca, 'br') ? 'Exclusivo' : 'Premium';
                $precoVenda = round($custo * $multiplicador, 2);
                $min = max(10, (int)($item['min'] ?? 100));
                $max = max($min, (int)($item['max'] ?? 50000));

                $ins->execute([$sid, $plataforma, $tipo, $qualidade, $nome, "Entrega automática para {$plataforma}. Não solicitamos senha.", $precoVenda, $min, $max]);
                $importados++;
            }
            $msg = "✓ {$importados} serviços importados com {$margem}% de lucro aplicado!";
        } else {
            $msg = "⚠ Não foi possível conectar à API do fornecedor. Verifique SMM_API_URL e SMM_API_KEY no Railway.";
        }
    } elseif ($acao === 'novo_servico') {
        $pdo->prepare("INSERT INTO servicos (provider_service_id, plataforma, tipo, qualidade, titulo, descricao, preco_por_mil, minimo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([(int)$_POST['provider_id'], $_POST['plataforma'], $_POST['tipo'], $_POST['qualidade'], $_POST['titulo'], $_POST['descricao'], (float)$_POST['preco'], (int)$_POST['minimo']]);
        $msg = '✓ Serviço cadastrado!';
    } elseif ($acao === 'editar_servico') {
        $pdo->prepare("UPDATE servicos SET provider_service_id = ?, preco_por_mil = ?, minimo = ? WHERE id = ?")
            ->execute([(int)$_POST['provider_id'], (float)$_POST['preco'], (int)$_POST['minimo'], (int)$_POST['id']]);
        $msg = '✓ Serviço atualizado!';
    } elseif ($acao === 'excluir_servico') {
        $pdo->prepare("DELETE FROM servicos WHERE id = ?")->execute([(int)$_POST['id']]);
        $msg = '✓ Serviço removido!';
    } elseif ($acao === 'limpar_servicos') {
        $pdo->exec("DELETE FROM servicos");
        $msg = '✓ Todos os serviços antigos foram removidos!';
    } elseif ($acao === 'add_saldo') {
        $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?")->execute([(float)$_POST['valor'], (int)$_POST['user_id']]);
        $msg = '✓ Saldo adicionado ao usuário!';
    }
}

$servicos = $pdo->query("SELECT * FROM servicos ORDER BY plataforma ASC, id ASC")->fetchAll();
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY id DESC LIMIT 30")->fetchAll();
$pedidos  = $pdo->query("SELECT p.*, s.titulo FROM pedidos p JOIN servicos s ON p.servico_id = s.id ORDER BY p.id DESC LIMIT 30")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Blindagem Social</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  body{background:#09090b;color:#fafafa;padding:24px}
  .container{max-width:1180px;margin:0 auto}
  .gold{color:#fbbf24}
  .card{background:#121215;border:1px solid #27272a;border-radius:18px;padding:22px;margin-bottom:24px;overflow-x:auto}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;align-items:end}
  label{display:block;font-size:11px;color:#a1a1aa;margin-bottom:4px}
  input,select{width:100%;padding:9px 11px;border-radius:10px;background:#09090b;border:1px solid #27272a;color:#fff;font-size:13px}
  .btn{padding:10px 15px;border-radius:10px;font-weight:800;font-size:12px;border:none;cursor:pointer;text-decoration:none;display:inline-block}
  .btn-gold{background:linear-gradient(135deg,#fbbf24,#d97706);color:#09090b}
  .btn-red{background:rgba(239,68,68,.15);color:#f87171}
  .btn-dark{background:#18181b;color:#fff;border:1px solid #27272a}
  .msg{background:rgba(245,158,11,.14);border:1px solid rgba(245,158,11,.35);color:#fbbf24;padding:12px 16px;border-radius:12px;font-size:13px;font-weight:700;margin-bottom:20px}
  table{width:100%;border-collapse:collapse;font-size:12px;text-align:left;margin-top:12px}
  th{color:#a1a1aa;padding:10px;border-bottom:1px solid #27272a}
  td{padding:10px;border-bottom:1px solid #1f1f23}
</style>
</head>
<body>
<div class="container">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:900">Admin <span class="gold">Blindagem Social</span></h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="cron_status.php" target="_blank" class="btn btn-dark">Sincronizar Pedidos</a>
      <a href="index.php" class="btn btn-dark">Ver Site</a>
      <a href="admin.php?sair=1" class="btn btn-red">Sair</a>
    </div>
  </div>

  <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <!-- IMPORTADOR AUTOMÁTICO COM 100% DE LUCRO -->
  <div class="card" style="border-color:rgba(245,158,11,.4)">
    <h2 style="font-size:16px;margin-bottom:4px">⚡ Importar Serviços Automáticos do Fornecedor (Com Margem de Lucro)</h2>
    <p style="font-size:12px;color:#a1a1aa;margin-bottom:14px">Puxa os serviços reais da API do seu fornecedor e já aplica <strong class="gold">100% de lucro</strong> (ex.: se custa R$ 2,00 no fornecedor, vende por R$ 4,00 no seu site).</p>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
      <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;flex:1">
        <input type="hidden" name="acao" value="importar_fornecedor">
        <div style="min-width:150px">
          <label>Margem de Lucro (%)</label>
          <input type="number" name="margem" value="100" min="1" required>
        </div>
        <div style="min-width:150px">
          <label>Qtd. de serviços importar</label>
          <input type="number" name="limite" value="30" min="1" max="100" required>
        </div>
        <button class="btn btn-gold">Importar Agora com 100% de Lucro &rarr;</button>
      </form>
      <form method="POST" onsubmit="return confirm('Apagar todos os serviços atuais antes de importar?')">
        <input type="hidden" name="acao" value="limpar_servicos">
        <button class="btn btn-red">Limpar Serviços Fictícios</button>
      </form>
    </div>
  </div>

  <!-- Cadastrar Serviço Manual -->
  <div class="card">
    <h2 style="font-size:16px;margin-bottom:14px">Cadastrar Serviço Manualmente</h2>
    <form method="POST" class="grid">
      <input type="hidden" name="acao" value="novo_servico">
      <div><label>ID no Fornecedor</label><input type="number" name="provider_id" required placeholder="Ex: 452"></div>
      <div><label>Plataforma</label><select name="plataforma"><option>Instagram</option><option>TikTok</option><option>YouTube</option><option>Facebook</option><option>Telegram</option><option>Kwai</option></select></div>
      <div><label>Tipo</label><select name="tipo"><option>Seguidores</option><option>Curtidas</option><option>Visualizacoes</option></select></div>
      <div><label>Qualidade</label><select name="qualidade"><option>Exclusivo</option><option>Premium</option><option>Avancado</option></select></div>
      <div><label>Título na Loja</label><input type="text" name="titulo" required placeholder="Seguidores BR Reais"></div>
      <div><label>Preço Venda / 1k (R$)</label><input type="number" step="0.01" name="preco" required placeholder="6.90"></div>
      <div><label>Mínimo</label><input type="number" name="minimo" value="100" required></div>
      <input type="hidden" name="descricao" value="Entrega automática sem senha. Deixe o perfil público.">
      <button class="btn btn-gold">Adicionar</button>
    </form>
  </div>

  <!-- Serviços Cadastrados -->
  <div class="card">
    <h2 style="font-size:16px">Serviços Ativos na Loja (<?= count($servicos) ?>)</h2>
    <table>
      <thead><tr><th>Plataforma</th><th>Título</th><th>ID Fornecedor</th><th>Preço Venda / 1k</th><th>Mínimo</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($servicos as $s): ?>
        <tr>
          <td><strong><?= htmlspecialchars($s['plataforma']) ?></strong></td>
          <td><?= htmlspecialchars($s['titulo']) ?></td>
          <form method="POST">
            <input type="hidden" name="acao" value="editar_servico">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <td><input type="number" name="provider_id" value="<?= $s['provider_service_id'] ?>" style="width:85px"></td>
            <td><input type="number" step="0.01" name="preco" value="<?= $s['preco_por_mil'] ?>" style="width:90px"></td>
            <td><input type="number" name="minimo" value="<?= $s['minimo'] ?>" style="width:80px"></td>
            <td style="display:flex;gap:6px">
              <button class="btn btn-gold">Salvar</button>
          </form>
          <form method="POST" onsubmit="return confirm('Excluir?')">
            <input type="hidden" name="acao" value="excluir_servico">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button class="btn btn-red">Excluir</button>
          </form>
            </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Clientes e Saldo Manual -->
  <div class="card">
    <h2 style="font-size:16px">Clientes Cadastrados & Saldo</h2>
    <table>
      <thead><tr><th>Usuário</th><th>E-mail</th><th>Saldo Atual</th><th>Creditar Saldo (R$)</th></tr></thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
        <tr>
          <td><strong>@<?= htmlspecialchars($u['usuario']) ?></strong></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td class="gold"><strong>R$ <?= number_format($u['saldo'], 2, ',', '.') ?></strong></td>
          <td>
            <form method="POST" style="display:flex;gap:6px;max-width:220px">
              <input type="hidden" name="acao" value="add_saldo">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="number" step="0.01" name="valor" placeholder="10.00" required>
              <button class="btn btn-gold">+ Creditar</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pedidos Enviados -->
  <div class="card">
    <h2 style="font-size:16px">Últimos Pedidos</h2>
    <table>
      <thead><tr><th>Código</th><th>Serviço</th><th>Link</th><th>Qtd</th><th>Valor</th><th>ID Fornecedor</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($pedidos as $p): ?>
        <tr>
          <td class="gold"><strong><?= htmlspecialchars($p['codigo_rastreio']) ?></strong></td>
          <td><?= htmlspecialchars($p['titulo']) ?></td>
          <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($p['link_destino']) ?></td>
          <td><?= $p['quantidade'] ?></td>
          <td>R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
          <td><?= htmlspecialchars($p['provider_order_id'] ?? 'Aguardando API Key') ?></td>
          <td><strong><?= strtoupper($p['status_entrega']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
