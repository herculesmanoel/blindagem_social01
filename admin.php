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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    if ($acao === 'novo_servico') {
        $pdo->prepare("INSERT INTO servicos (provider_service_id, plataforma, tipo, qualidade, titulo, descricao, preco_por_mil, minimo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([(int)$_POST['provider_id'], $_POST['plataforma'], $_POST['tipo'], $_POST['qualidade'], $_POST['titulo'], $_POST['descricao'], (float)$_POST['preco'], (int)$_POST['minimo']]);
    } elseif ($acao === 'editar_servico') {
        $pdo->prepare("UPDATE servicos SET provider_service_id = ?, preco_por_mil = ?, minimo = ? WHERE id = ?")
            ->execute([(int)$_POST['provider_id'], (float)$_POST['preco'], (int)$_POST['minimo'], (int)$_POST['id']]);
    } elseif ($acao === 'excluir_servico') {
        $pdo->prepare("DELETE FROM servicos WHERE id = ?")->execute([(int)$_POST['id']]);
    } elseif ($acao === 'add_saldo') {
        $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?")->execute([(float)$_POST['valor'], (int)$_POST['user_id']]);
    }
    header('Location: admin.php');
    exit;
}

$servicos = $pdo->query("SELECT * FROM servicos ORDER BY plataforma ASC, id ASC")->fetchAll();
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY id DESC LIMIT 30")->fetchAll();
$pedidos  = $pdo->query("SELECT p.*, s.titulo FROM pedidos p JOIN servicos s ON p.servico_id = s.id ORDER BY p.id DESC LIMIT 30")->fetchAll();
?>

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    if ($acao === 'novo_servico') {
        $pdo->prepare("INSERT INTO servicos (provider_service_id, plataforma, tipo, qualidade, titulo, descricao, preco_por_mil, minimo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([(int)$_POST['provider_id'], $_POST['plataforma'], $_POST['tipo'], $_POST['qualidade'], $_POST['titulo'], $_POST['descricao'], (float)$_POST['preco'], (int)$_POST['minimo']]);
    } elseif ($acao === 'editar_servico') {
        $pdo->prepare("UPDATE servicos SET provider_service_id = ?, preco_por_mil = ?, minimo = ? WHERE id = ?")
            ->execute([(int)$_POST['provider_id'], (float)$_POST['preco'], (int)$_POST['minimo'], (int)$_POST['id']]);
    } elseif ($acao === 'excluir_servico') {
        $pdo->prepare("DELETE FROM servicos WHERE id = ?")->execute([(int)$_POST['id']]);
    } elseif ($acao === 'add_saldo') {
        $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?")->execute([(float)$_POST['valor'], (int)$_POST['user_id']]);
    }
    header('Location: admin.php');
    exit;
}

$servicos = $pdo->query("SELECT * FROM servicos ORDER BY plataforma ASC, id ASC")->fetchAll();
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY id DESC LIMIT 30")->fetchAll();
$pedidos  = $pdo->query("SELECT p.*, s.titulo FROM pedidos p JOIN servicos s ON p.servico_id = s.id ORDER BY p.id DESC LIMIT 30")->fetchAll();
?>
