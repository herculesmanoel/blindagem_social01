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
                $stmt->execute([$nome, $usuario, $email, password_hash($senha, PASSWORD_DEFAULT), $apiKey]);
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

        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? OR email = ?");
        $stmt->execute([strtolower($login), strtolower($login)]);
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
    <title><?= $modo === 'cadastro' ? 'Criar Conta Grátis' : 'Entrar no Painel' ?> — Blindagem Social</title>
    <script src="https://tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-7 shadow-2xl">
        <a href="index.php" class="text-xs text-emerald-400 hover:underline">&larr; Voltar para a página inicial</a>
        <h1 class="text-2xl font-extrabold mt-2"><?= $modo === 'cadastro' ? 'Criar conta grátis' : 'Entrar no Painel SMM' ?></h1>
        <p class="text-xs text-slate-400 mt-1 mb-5">Use seu login do nosso site (nunca sua senha do Instagram).</p>

        <?php if ($erro): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-xs p-3 rounded-xl mb-4"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if ($modo === 'cadastro'): ?>
        <form method="POST" class="space-y-3.5">
            <input type="hidden" name="acao" value="cadastrar">
            <div>
                <label class="text-xs text-slate-400">Seu nome</label>
                <input type="text" name="nome" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm mt-1">
            </div>
            <div>
                <label class="text-xs text-slate-400">Usuário (sem espaços)</label>
                <input type="text" name="usuario" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm mt-1">
            </div>
            <div>
                <label class="text-xs text-slate-400">E-mail</label>
                <input type="email" name="email" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm mt-1">
            </div>
            <div>
                <label class="text-xs text-slate-400">Senha (mínimo 6 caracteres)</label>
                <input type="password" name="senha" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm mt-1">
            </div>
            <button class="w-full py-3 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm">Criar Conta Grátis</button>
            <p class="text-center text-xs text-slate-400 pt-2">Já tem conta? <a href="auth.php?modo=login" class="text-emerald-400 font-semibold">Fazer login</a></p>
        </form>
        <?php else: ?>
        <form method="POST" class="space-y-3.5">
            <input type="hidden" name="acao" value="entrar">
            <div>
                <label class="text-xs text-slate-400">Usuário ou E-mail</label>
                <input type="text" name="login" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm mt-1">
            </div>
            <div>
                <label class="text-xs text-slate-400">Senha</label>
                <input type="password" name="senha" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm mt-1">
            </div>
            <button class="w-full py-3 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm">Fazer Login &rarr;</button>
            <p class="text-center text-xs text-slate-400 pt-2">Ainda não é membro? <a href="auth.php?modo=cadastro" class="text-emerald-400 font-semibold">Cadastre-se grátis</a></p>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
