<?php
require_once 'config.php';
$pedido = null;
$codigo = trim($_GET['codigo'] ?? '');

if ($codigo !== '') {
    $stmt = $pdo->prepare("SELECT p.*, s.titulo, s.tipo, s.qualidade FROM pedidos p JOIN servicos s ON p.servico_id = s.id WHERE p.codigo_rastreio = ?");
    $stmt->execute([$codigo]);
    $pedido = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Pedido — Blindagem Social</title>
    <script src="https://tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <a href="index.php" class="text-xs text-pink-400 hover:underline">&larr; Voltar para serviços</a>
        <h1 class="text-2xl font-extrabold mt-2 mb-1">Consultar Pedido</h1>
        <p class="text-xs text-slate-400 mb-5">Digite o código privado gerado após o seu pedido.</p>

        <form method="GET" class="flex gap-2 mb-6">
            <input type="text" name="codigo" placeholder="Ex: UP-A1B2C3D4" value="<?= htmlspecialchars($codigo) ?>" required
                   class="flex-1 bg-slate-950 border border-slate-700 focus:border-pink-500 outline-none rounded-xl px-3.5 py-2.5 text-sm uppercase">
            <button type="submit" class="bg-gradient-to-r from-pink-600 to-violet-600 px-4 py-2.5 rounded-xl text-sm font-semibold cursor-pointer">
                Buscar
            </button>
        </form>

        <?php if ($pedido): ?>
            <div class="bg-slate-950 border border-slate-800 rounded-xl p-4 space-y-2.5 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-400">Código:</span>
                    <span class="font-bold text-pink-400"><?= htmlspecialchars($pedido['codigo_rastreio']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Serviço:</span>
                    <span class="font-medium text-right"><?= htmlspecialchars($pedido['titulo']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Quantidade:</span>
                    <span><?= number_format($pedido['quantidade'], 0, ',', '.') ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Valor:</span>
                    <span class="text-emerald-400 font-semibold">R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></span>
                </div>
                <div class="flex justify-between pt-2 border-t border-slate-800">
                    <span class="text-slate-400">Pagamento:</span>
                    <span class="uppercase font-bold text-amber-400"><?= htmlspecialchars($pedido['status_pagamento']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Entrega:</span>
                    <span class="uppercase font-bold text-emerald-400"><?= htmlspecialchars($pedido['status_entrega']) ?></span>
                </div>
            </div>
        <?php elseif ($codigo !== ''): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 text-xs p-3 rounded-xl text-center">
                Nenhum pedido encontrado com o código <strong><?= htmlspecialchars($codigo) ?></strong>.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
