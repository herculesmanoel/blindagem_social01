<?php
session_start();
require_once 'config.php';

if (isset($_POST['senha'])) {
    if ($_POST['senha'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logado'] = true;
    } else {
        $erro = 'Senha incorreta.';
    }
}
if (isset($_GET['sair'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (empty($_SESSION['admin_logado'])): ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin</title>
    <script src="https://tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <form method="POST" class="bg-slate-900 border border-slate-800 p-6 rounded-2xl max-w-sm w-full space-y-4">
        <h1 class="text-xl font-bold">Painel Administrativo</h1>
        <?php if (!empty($erro)): ?><p class="text-xs text-red-400"><?= $erro ?></p><?php endif; ?>
        <input type="password" name="senha" placeholder="Senha do admin" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm">
        <button class="w-full py-2.5 rounded-xl bg-pink-600 font-semibold text-sm">Entrar</button>
    </form>
</body>
</html>
<?php exit; endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] === 'novo_servico') {
        $stmt = $pdo->prepare("INSERT INTO servicos (provider_service_id, tipo, qualidade, titulo, descricao, preco_por_mil, minimo) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([(int)$_POST['provider_id'], $_POST['tipo'], $_POST['qualidade'], $_POST['titulo'], $_POST['descricao'], (float)$_POST['preco'], (int)$_POST['minimo']]);
    } elseif ($_POST['acao'] === 'editar_preco') {
        $stmt = $pdo->prepare("UPDATE servicos SET preco_por_mil = ?, provider_service_id = ? WHERE id = ?");
        $stmt->execute([(float)$_POST['preco'], (int)$_POST['provider_id'], (int)$_POST['id']]);
    } elseif ($_POST['acao'] === 'excluir_servico') {
        $pdo->prepare("DELETE FROM servicos WHERE id = ?")->execute([(int)$_POST['id']]);
    }
    header('Location: admin.php');
    exit;
}

$servicos = $pdo->query("SELECT * FROM servicos ORDER BY id ASC")->fetchAll();
$pedidos  = $pdo->query("SELECT p.*, s.titulo FROM pedidos p JOIN servicos s ON p.servico_id = s.id ORDER BY p.id DESC LIMIT 30")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Blindagem Social</title>
    <script src="https://tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-4 md:p-8">
    <div class="max-w-6xl mx-auto space-y-8">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-extrabold">Painel Administrativo</h1>
            <div class="flex gap-3 text-xs">
                <a href="index.php" class="bg-slate-800 px-3 py-2 rounded-lg">Ver Loja</a>
                <a href="admin.php?sair=1" class="bg-red-600/20 text-red-400 px-3 py-2 rounded-lg">Sair</a>
            </div>
        </div>

        <!-- Adicionar Serviço -->
        <form method="POST" class="bg-slate-900 border border-slate-800 p-5 rounded-2xl grid sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
            <input type="hidden" name="acao" value="novo_servico">
            <div>
                <label class="text-xs text-slate-400">ID Provedor</label>
                <input type="number" name="provider_id" required class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs">
            </div>
            <div>
                <label class="text-xs text-slate-400">Tipo</label>
                <select name="tipo" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs">
                    <option value="Seguidores">Seguidores</option>
                    <option value="Curtidas">Curtidas</option>
                    <option value="Visualizacoes">Visualizações</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-400">Qualidade</label>
                <select name="qualidade" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs">
                    <option value="Avancado">Avançado</option>
                    <option value="Premium">Premium</option>
                    <option value="Exclusivo">Exclusivo</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-400">Título</label>
                <input type="text" name="titulo" required class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs">
            </div>
            <div>
                <label class="text-xs text-slate-400">Preço / 1.000</label>
                <input type="number" step="0.01" name="preco" required class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs">
            </div>
            <input type="hidden" name="descricao" value="Escolha a quantidade e informe o link. Não solicitamos sua senha.">
            <input type="hidden" name="minimo" value="1000">
            <button class="bg-emerald-600 hover:bg-emerald-500 py-2 px-4 rounded-lg text-xs font-bold">Adicionar</button>
        </form>

        <!-- Lista de Serviços -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 overflow-x-auto">
            <h2 class="font-bold mb-4">Serviços Ativos</h2>
            <table class="w-full text-xs text-left">
                <thead class="text-slate-400 border-b border-slate-800">
                    <tr><th class="py-2">Título</th><th>Tipo</th><th>Qualidade</th><th>ID API</th><th>Preço/1k</th><th>Ações</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php foreach ($servicos as $s): ?>
                    <tr>
                        <td class="py-2.5 font-medium"><?= htmlspecialchars($s['titulo']) ?></td>
                        <td><?= $s['tipo'] ?></td>
                        <td><?= $s['qualidade'] ?></td>
                        <form method="POST">
                            <input type="hidden" name="acao" value="editar_preco">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <td><input type="number" name="provider_id" value="<?= $s['provider_service_id'] ?>" class="w-16 bg-slate-950 border border-slate-700 rounded px-2 py-1"></td>
                            <td><input type="number" step="0.01" name="preco" value="<?= $s['preco_por_mil'] ?>" class="w-20 bg-slate-950 border border-slate-700 rounded px-2 py-1"></td>
                            <td class="flex gap-2 py-2">
                                <button class="bg-sky-600 px-2.5 py-1 rounded text-white">Salvar</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Excluir este serviço?')">
                            <input type="hidden" name="acao" value="excluir_servico">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="bg-red-600/30 text-red-400 px-2.5 py-1 rounded">Excluir</button>
                        </form>
                            </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Últimos Pedidos -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 overflow-x-auto">
            <h2 class="font-bold mb-4">Últimos Pedidos</h2>
            <table class="w-full text-xs text-left">
                <thead class="text-slate-400 border-b border-slate-800">
                    <tr><th class="py-2">Código</th><th>Serviço</th><th>Link</th><th>Qtd</th><th>Total</th><th>Pagamento</th><th>Entrega</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php foreach ($pedidos as $p): ?>
                    <tr>
                        <td class="py-2.5 font-bold text-pink-400"><?= $p['codigo_rastreio'] ?></td>
                        <td><?= htmlspecialchars($p['titulo']) ?></td>
                        <td class="max-w-[180px] truncate"><?= htmlspecialchars($p['link_destino']) ?></td>
                        <td><?= $p['quantidade'] ?></td>
                        <td class="text-emerald-400">R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
                        <td class="uppercase"><?= $p['status_pagamento'] ?></td>
                        <td class="uppercase"><?= $p['status_entrega'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
