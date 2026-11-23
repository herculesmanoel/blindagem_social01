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
                header('Location: painel.php?efeito=boasvindas');
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
<link rel="stylesheet" href="estilo.css">
</head>
<body style="align-items:center;justify-content:center;padding:20px">
  <div class="card" style="max-width:440px;width:100%;margin-bottom:0">
    <a href="index.php" class="brand" style="margin-bottom:20px"><span class="badge-logo">B</span><span>Blindagem <span class="gold">Social</span></span></a>

    <div class="menu-tabs" style="display:grid;grid-template-columns:1fr 1fr;padding:6px">
      <a href="auth.php?modo=login" class="tab-btn <?= $modo !== 'cadastro' ? 'active' : '' ?>" style="text-align:center">Entrar</a>
      <a href="auth.php?modo=cadastro" class="tab-btn <?= $modo === 'cadastro' ? 'active' : '' ?>" style="text-align:center">Criar Conta</a>
    </div>

    <?php if ($erro): ?><div class="alert-err"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <?php if ($modo === 'cadastro'): ?>
      <h1 style="font-size:24px;margin-bottom:6px">Criar conta grátis</h1>
      <p style="font-size:13px;color:#a1a1aa;margin-bottom:18px">Cadastre-se em segundos para acessar o painel VIP.</p>
      <form method="POST">
        <input type="hidden" name="acao" value="cadastrar">
        <div class="field"><label>Seu nome</label><input type="text" name="nome" required placeholder="Ex: João Silva"></div>
        <div class="field"><label>Usuário (letras e números)</label><input type="text" name="usuario" required placeholder="joaosilva"></div>
        <div class="field"><label>E-mail</label><input type="email" name="email" required placeholder="voce@email.com"></div>
        <div class="field"><label>Senha (mínimo 6 caracteres)</label><input type="password" name="senha" required placeholder="••••••••"></div>
        <button type="submit" class="btn btn-gold" style="width:100%">Criar Conta Grátis &rarr;</button>
      </form>
    <?php else: ?>
      <h1 style="font-size:24px;margin-bottom:6px">Bem-vindo de volta</h1>
      <p style="font-size:13px;color:#a1a1aa;margin-bottom:18px">Entre com seu usuário ou e-mail da <strong class="gold-solid">Blindagem Social</strong>.</p>
      <form method="POST">
        <input type="hidden" name="acao" value="entrar">
        <div class="field"><label>Usuário ou E-mail</label><input type="text" name="login" required placeholder="seu_usuario ou seu@email.com"></div>
        <div class="field"><label>Senha</label><input type="password" name="senha" required placeholder="••••••••"></div>
        <button type="submit" class="btn btn-gold" style="width:100%">Acessar Painel &rarr;</button>
      </form>
    <?php endif; ?>

    <a href="index.php" style="display:block;text-align:center;margin-top:18px;font-size:12px;color:#71717a">&larr; Voltar para a página inicial</a>
  </div>
</body>
</html>
