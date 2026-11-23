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
    <link href="https://googleapis.com" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col antialiased">

    <header class="bg-white border-b border-slate-200/80">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="text-xl font-extrabold tracking-tight text-slate-900 flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-tr from-fuchsia-600 to-indigo-600 text-white flex items-center justify-center text-sm font-black">B</span>
                Blindagem Social
            </a>
            <a href="index.php" class="text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 px-3.5 py-2 rounded-xl transition">
                &larr; Voltar aos serviços
            </a>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200/90 rounded-2xl max-w-md w-full p-6 shadow-sm">
            <span class="inline-block text-[11px] font-bold uppercase tracking-wider text-fuchsia-600 bg-fuchsia-50 px-2.5 py-1 rounded-full">
                Acompanhamento privado
            </span>
            <h1 class="text-2xl font-extrabold text-slate-900 mt-2">Consultar Pedido</h1>
            <p class="text-xs text-slate-500 mt-1 mb-5">Informe o código privado gerado após criar seu pedido.</p>

            <form method="GET" class="flex gap-2 mb-6">
                <input type="text" name="codigo" placeholder="Ex: BS8A9F12..." value="<?= htmlspecialchars($codigo) ?>" required
                       class="flex-1 bg-slate-50 border border-slate-300 focus:border-fuchsia-600 outline-none rounded-xl px-3.5 py-2.5 text-sm text-slate-900">
                <button type="submit" class="bg-slate-900 hover:bg-fuchsia-600 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition cursor-pointer">
                    Buscar
                </button>
            </form>

            <?php if ($pedido): ?>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Código:</span>
                        <span class="font-bold text-fuchsia-600 text-xs"><?= htmlspecialchars($pedido['codigo_rastreio']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Serviço:</span>
                        <span class="font-semibold text-slate-900 text-right"><?= htmlspecialchars($pedido['titulo']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Quantidade:</span>
                        <span class="font-medium"><?= number_format($pedido['quantidade'], 0, ',', '.') ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Valor:</span>
                        <span class="text-emerald-600 font-bold">R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></span>
                    </div>
                    <div class="flex justify-between pt-2.5 border-t border-slate-200">
                        <span class="text-slate-500">Pagamento:</span>
                        <span class="uppercase font-bold text-xs px-2.5 py-0.5 rounded-full <?= $pedido['status_pagamento'] === 'pago' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                            <?= htmlspecialchars($pedido['status_pagamento']) ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Entrega:</span>
                        <span class="uppercase font-bold text-xs px-2.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700">
                            <?= htmlspecialchars($pedido['status_entrega']) ?>
                        </span>
                    </div>
                </div>
            <?php elseif ($codigo !== ''): ?>
                <div class="bg-red-50 border border-red-200 text-red-600 text-xs p-3.5 rounded-xl text-center font-medium">
                    Nenhum pedido encontrado com esse código.
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
