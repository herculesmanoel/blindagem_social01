<?php
require_once 'config.php';

if (isset($_GET['sair'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$erro = '';
$modo = $_GET['modo'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cadastrar') {
        $nome    = trim($_POST['nome'] ?? '');
        $usuario = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['usuario'] ?? ''));
        $email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $senha   = $_POST['senha'] ?? '';

        if (!$nome || strlen($usuario) < 3 || !$email || strlen($senha) < 6) {
            $erro = 'Preencha todos os campos (usuário mín. 3 letras e senha mín. 6 caracteres).';
            $modo = 'cadastro';
        } else {
            try {
                $apiKey = bin2hex(random_bytes(16));
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, usuario, email, senha_hash, api_key) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $usuario, strtolower($email), password_hash($senha, PASSWORD_DEFAULT), $apiKey]);
                $_SESSION['usuario_id'] = (int)$pdo->lastInsertId();
                header('Location: painel.php');
                exit;
            } catch (PDOException $e) {
                $erro = 'Este usuário ou e-mail já está cadastrado.';
                $modo = 'cadastro';
            }
        }
    } elseif ($acao === 'entrar') {
        $login = trim($_POST['login'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $loginLimpo = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $login));

        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? OR email = ?");
        $stmt->execute([$loginLimpo, strtolower($login)]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha_hash'])) {
            $_SESSION['usuario_id'] = (int)$user['id'];
            header('Location: painel.php');
            exit;
        } else {
            $erro = 'Usuário ou senha incorretos.';
            $modo = 'login';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $modo === 'cadastro' ? 'Criar Conta Grátis' : 'Acessar Painel' ?> — Blindagem Social</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  body{background:#09090b;color:#fafafa;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background-image:radial-gradient(circle at 50% 0%,rgba(245,158,11,.15),transparent 55%)}
  .box{background:#121215;border:1px solid #27272a;border-radius:22px;max-width:430px;width:100%;padding:32px;box-shadow:0 24px 60px rgba(0,0,0,.75)}
  .brand{display:flex;align-items:center;gap:10px;font-weight:900;font-size:18px;margin-bottom:20px;text-decoration:none;color:#fff}
  .badge{width:32px;height:32px;border-radius:9px;background:linear-gradient(135deg,#fbbf24,#d97706);color:#09090b;display:flex;align-items:center;justify-content:center;font-weight:900}
  .gold{color:#fbbf24}
  h1{font-size:24px;font-weight:800;margin-bottom:6px}
  .sub{font-size:13px;color:#a1a1aa;margin-bottom:20px}
  .tabs{display:grid;grid-template-columns:1fr 1fr;gap:8px;background:#09090b;padding:5px;border-radius:12px;border:1px solid #27272a;margin-bottom:20px}
  .tab{text-align:center;padding:9px;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none;color:#a1a1aa}
  .tab.active{background:linear-gradient(135deg,#fbbf24,#d97706);color:#09090b}
  .field{margin-bottom:14px}
  .field label{display:block;font-size:12px;color:#a1a1aa;margin-bottom:6px;font-weight:600}
  .field input{width:100%;padding:12px 14px;border-radius:12px;background:#09090b;border:1px solid #27272a;color:#fff;font-size:14px;outline:none}
  .field input:focus{border-color:#fbbf24}
  .btn{width:100%;padding:13px;border-radius:12px;background:linear-gradient(135deg,#fbbf24,#d97706);color:#09090b;font-weight:800;font-size:14px;border:none;cursor:pointer;margin-top:6px}
  .btn:hover{filter:brightness(1.08)}
  .erro{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);color:#f87171;padding:11px 14px;border-radius:12px;font-size:12px;margin-bottom:16px}
  .back{display:block;text-align:center;margin-top:18px;font-size:12px;color:#71717a;text-decoration:none}
  .back:hover{color:#fbbf24}
</style>
</head>
<body>
  <div class="box">
    <a href="index.php" class="brand"><span class="badge">B</span><span>Blindagem <span class="gold">Social</span></span></a>

    <div class="tabs">
      <a href="auth.php?modo=login" class="tab <?= $modo !== 'cadastro' ? 'active' : '' ?>">Entrar</a>
      <a href="auth.php?modo=cadastro" class="tab <?= $modo === 'cadastro' ? 'active' : '' ?>">Criar Conta</a>
    </div>

    <?php if ($erro): ?><div class="erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <?php if ($modo === 'cadastro'): ?>
      <h1>Criar conta grátis</h1>
      <p class="sub">Cadastre-se em segundos para acessar o painel completo.</p>
      <form method="POST">
        <input type="hidden" name="acao" value="cadastrar">
        <div class="field"><label>Seu nome</label><input type="text" name="nome" required placeholder="Ex: João Silva"></div>
        <div class="field"><label>Usuário (letras e números)</label><input type="text" name="usuario" required placeholder="joaosilva"></div>
        <div class="field"><label>E-mail</label><input type="email" name="email" required placeholder="voce@email.com"></div>
        <div class="field"><label>Senha (mínimo 6 caracteres)</label><input type="password" name="senha" required placeholder="••••••••"></div>
        <button type="submit" class="btn">Criar Conta Grátis &rarr;</button>
      </form>
    <?php else: ?>
      <h1>Bem-vindo de volta</h1>
      <p class="sub">Entre com seu usuário ou e-mail da <strong class="gold">Blindagem Social</strong>.</p>
      <form method="POST">
        <input type="hidden" name="acao" value="entrar">
        <div class="field"><label>Usuário ou E-mail</label><input type="text" name="login" required placeholder="seu_usuario ou seu@email.com"></div>
        <div class="field"><label>Senha</label><input type="password" name="senha" required placeholder="••••••••"></div>
        <button type="submit" class="btn">Acessar Painel &rarr;</button>
      </form>
    <?php endif; ?>

    <a href="index.php" class="back">&larr; Voltar para a página inicial</a>
  </div>
</body>
</html>
