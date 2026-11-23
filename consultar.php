<?php
require_once 'config.php';
$pedido = null;
$codigo = strtoupper(trim($_GET['codigo'] ?? ''));

if ($codigo !== '') {
    $stmt = $pdo->prepare("SELECT p.*, s.titulo, s.plataforma, s.qualidade FROM pedidos p JOIN servicos s ON p.servico_id = s.id WHERE UPPER(p.codigo_rastreio) = ?");
    $stmt->execute([$codigo]);
    $pedido = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rastrear Pedido — Blindagem Social</title>
<link rel="stylesheet" href="estilo.css">
</head>
<body>

<header class="topbar">
  <div class="container topbar-inner">
    <a href="index.php" class="brand">
      <span class="badge-logo">B</span>
      <span>Blindagem <span class="gold">Social</span></span>
    </a>
    <a href="index.php" class="btn btn-dark" style="padding:9px 16px;font-size:12px">
      &larr; Voltar para o Início
    </a>
  </div>
</header>

<main class="container" style="flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px">
  <div class="card" style="max-width:480px;width:100%;margin-bottom:0">
    <span class="badge">Acompanhamento Privado</span>
    <h1 style="font-size:28px;margin-top:12px;margin-bottom:6px">Rastrear <span class="gold">Pedido</span></h1>
    <p style="font-size:13px;color:#a1a1aa;margin-bottom:22px">Informe o código do seu pedido (ex.: <strong class="gold-solid">BS-A1B2C3D4</strong>) para ver o status em tempo real.</p>

    <form method="GET" style="display:flex;gap:10px;margin-bottom:22px">
      <div class="field" style="flex:1;margin-bottom:0">
        <input type="text" name="codigo" placeholder="Ex: BS-A1B2C3D4" value="<?= htmlspecialchars($codigo) ?>" required style="text-transform:uppercase">
      </div>
      <button type="submit" class="btn btn-gold" style="padding:12px 20px">Buscar</button>
    </form>

    <?php if ($pedido): ?>
      <div style="background:#060608;border:1px solid rgba(251,191,36,.3);border-radius:16px;padding:18px;font-size:13px;display:flex;flex-direction:column;gap:12px">
        <div style="display:flex;justify-content:space-between">
          <span style="color:#a1a1aa">Código:</span>
          <strong class="gold-solid"><?= htmlspecialchars($pedido['codigo_rastreio']) ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span style="color:#a1a1aa">Plataforma:</span>
          <strong><?= htmlspecialchars($pedido['plataforma']) ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;gap:12px">
          <span style="color:#a1a1aa">Serviço:</span>
          <strong style="text-align:right"><?= htmlspecialchars($pedido['titulo']) ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span style="color:#a1a1aa">Quantidade:</span>
          <strong><?= number_format($pedido['quantidade'], 0, ',', '.') ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span style="color:#a1a1aa">Valor Total:</span>
          <strong class="gold-solid">R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding-top:12px;border-top:1px solid #1f1f23">
          <span style="color:#a1a1aa">Status da Entrega:</span>
          <span class="status-badge"><?= htmlspecialchars($pedido['status_entrega']) ?></span>
        </div>
      </div>
    <?php elseif ($codigo !== ''): ?>
      <div class="alert-err" style="margin-bottom:0;text-align:center">
        Nenhum pedido encontrado com o código <strong><?= htmlspecialchars($codigo) ?></strong>.
      </div>
    <?php endif; ?>
  </div>
</main>

<footer>
  <div class="container">
    <p>&copy; <?= date('Y') ?> <strong class="gold-solid">Blindagem Social</strong> — Todos os direitos reservados.</p>
  </div>
</footer>

</body>
</html>
