<?php
require_once 'efi.php';

$user = usuarioAtual($pdo);
if (!$user) {
    header('Location: auth.php?modo=login');
    exit;
}

$msgSucesso = '';
$msgErro = '';
$pixGerado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'novo_pedido') {
        $servicoId  = (int)($_POST['servico_id'] ?? 0);
        $link       = trim($_POST['link'] ?? '');
        $quantidade = (int)($_POST['quantidade'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM servicos WHERE id = ? AND ativo = 1");
        $stmt->execute([$servicoId]);
        $serv = $stmt->fetch();

        if (!$serv || empty($link) || $quantidade < $serv['minimo'] || $quantidade > $serv['maximo']) {
            $msgErro = 'Verifique o link e a quantidade mínima/máxima do serviço.';
        } else {
            $valorTotal = round(($serv['preco_por_mil'] / 1000) * $quantidade, 2);
            if ((float)$user['saldo'] < $valorTotal) {
                $msgErro = 'Saldo insuficiente! Adicione saldo via PIX ao lado.';
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE usuarios SET saldo = saldo - ? WHERE id = ?")->execute([$valorTotal, $user['id']]);
                    $respSmm = enviarParaProvedorSMM((int)$serv['provider_service_id'], $link, $quantidade);
                    $orderId = $respSmm['order'] ?? null;
                    $statusEntrega = $orderId ? 'processando' : 'aguardando';
                    $codigo = 'PED-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

                    $ins = $pdo->prepare("INSERT INTO pedidos (usuario_id, codigo_rastreio, servico_id, link_destino, quantidade, valor_total, email_cliente, status_pagamento, status_entrega, provider_order_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'pago', ?, ?)");
                    $ins->execute([$user['id'], $codigo, $serv['id'], $link, $quantidade, $valorTotal, $user['email'], $statusEntrega, $orderId ? (string)$orderId : null]);

                    $pdo->commit();
                    $user['saldo'] -= $valorTotal;
                    $msgSucesso = "Pedido #{$codigo} enviado! Valor debitado: R$ " . number_format($valorTotal, 2, ',', '.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msgErro = 'Erro ao processar pedido: ' . $e->getMessage();
                }
            }
        }
    }

    if ($acao === 'gerar_pix') {
        $valorRecarga = round((float)str_replace(',', '.', $_POST['valor'] ?? '0'), 2);
        if ($valorRecarga < 1.00) {
            $msgErro = 'O valor mínimo para adicionar saldo via PIX é R$ 1,00.';
        } else {
            try {
                $txid = 'PIX' . bin2hex(random_bytes(13));
                $cob = efiCriarCobrancaPix($txid, $valorRecarga, "Recarga Saldo - {$user['usuario']}");
                $copiaCola = $cob['pixCopiaECola'];

                $pdo->prepare("INSERT INTO transacoes_pix (usuario_id, txid, valor, pix_copia_cola) VALUES (?, ?, ?, ?)")
                    ->execute([$user['id'], $txid, $valorRecarga, $copiaCola]);

                $pixGerado = ['valor' => $valorRecarga, 'codigo' => $copiaCola, 'txid' => $txid];
            } catch (Exception $e) {
                $msgErro = 'Erro ao gerar PIX Efí: ' . $e->getMessage();
            }
        }
    }

    if ($acao === 'nova_api_key') {
        $novaKey = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE usuarios SET api_key = ? WHERE id = ?")->execute([$novaKey, $user['id']]);
        $user['api_key'] = $novaKey;
        $msgSucesso = 'Nova chave de API gerada com sucesso!';
    }
}

$servicos = $pdo->query("SELECT * FROM servicos WHERE ativo = 1 ORDER BY plataforma ASC, id ASC")->fetchAll();
$pedidos  = $pdo->prepare("SELECT p.*, s.titulo FROM pedidos p JOIN servicos s ON p.servico_id = s.id WHERE p.usuario_id = ? ORDER BY p.id DESC LIMIT 30");
$pedidos->execute([$user['id']]);
$listaPedidos = $pedidos->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel SMM — Blindagem Social</title>
    <script src="https://tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">

    <header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-30">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="font-extrabold text-lg text-white flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-emerald-500 text-slate-950 flex items-center justify-center font-black text-sm">S</span>
                Painel SMM
            </a>
            <div class="flex items-center gap-3">
                <div class="bg-emerald-500/10 border border-emerald-500/30 px-3.5 py-1.5 rounded-xl text-xs">
                    <span class="text-slate-400">Saldo:</span>
                    <strong class="text-emerald-400 text-sm ml-1">R$ <?= number_format($user['saldo'], 2, ',', '.') ?></strong>
                </div>
                <span class="hidden sm:inline text-xs text-slate-400">@<?= htmlspecialchars($user['usuario']) ?></span>
                <a href="auth.php?sair=1" class="text-xs bg-slate-800 hover:bg-red-500/20 hover:text-red-400 px-3 py-2 rounded-xl transition">Sair</a>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-8 w-full flex-1 space-y-8">
        <?php if ($msgSucesso): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm p-4 rounded-2xl"><?= htmlspecialchars($msgSucesso) ?></div>
        <?php endif; ?>
        <?php if ($msgErro): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-300 text-sm p-4 rounded-2xl"><?= htmlspecialchars($msgErro) ?></div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-6">
                <h2 class="text-lg font-bold mb-1">Novo Pedido</h2>
                <p class="text-xs text-slate-400 mb-5">Escolha a plataforma, o serviço e informe o link público. Entrega automática.</p>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="acao" value="novo_pedido">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Plataforma</label>
                        <select id="sel-plataforma" onchange="filtrarServicosPainel()" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm">
                            <option value="Todas">Todas as plataformas</option>
                            <option value="Instagram">Instagram</option>
                            <option value="TikTok">TikTok</option>
                            <option value="YouTube">YouTube</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Serviço</label>
                        <select name="servico_id" id="sel-servico" onchange="atualizarResumoPainel()" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm">
                            <?php foreach ($servicos as $s): ?>
                                <option value="<?= $s['id'] ?>" data-plat="<?= htmlspecialchars($s['plataforma']) ?>" data-preco="<?= $s['preco_por_mil'] ?>" data-min="<?= $s['minimo'] ?>" data-max="<?= $s['maximo'] ?>" data-desc="<?= htmlspecialchars($s['descricao']) ?>">
                                    [<?= $s['plataforma'] ?>] <?= htmlspecialchars($s['titulo']) ?> — R$ <?= number_format($s['preco_por_mil'], 2, ',', '.') ?> / 1.000
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p id="desc-servico" class="text-xs text-slate-400 mt-1.5"></p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Link do Perfil ou Post (Perfil Público)</label>
                        <input type="text" name="link" placeholder="https://instagram.com" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm">
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Quantidade <span id="limites-qtd" class="text-slate-500"></span></label>
                            <input type="number" name="quantidade" id="inp-qtd" value="1000" min="100" oninput="atualizarResumoPainel()" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm">
                        </div>
                        <div class="bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 flex flex-col justify-center">
                            <span class="text-[11px] text-slate-400">Valor total do pedido</span>
                            <strong id="valor-pedido" class="text-xl text-emerald-400">R$ 0,00</strong>
                        </div>
                    </div>
                    <button type="submit" class="w-full py-3 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm transition cursor-pointer">
                        Enviar Pedido Agora &rarr;
                    </button>
                </form>
            </div>

            <div class="space-y-6">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
                    <h2 class="text-lg font-bold mb-1">Adicionar Saldo via PIX</h2>
                    <p class="text-xs text-slate-400 mb-4">Recarga instantânea a partir de R$ 1,00.</p>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="acao" value="gerar_pix">
                        <div>
                            <label class="text-xs text-slate-400">Valor (R$)</label>
                            <input type="number" step="0.01" min="1" name="valor" value="10.00" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm mt-1">
                        </div>
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 font-semibold text-sm cursor-pointer">Gerar PIX Efí</button>
                    </form>

                    <?php if ($pixGerado): ?>
                        <div class="mt-4 pt-4 border-t border-slate-800 space-y-2">
                            <p class="text-xs text-emerald-400 font-semibold">PIX de R$ <?= number_format($pixGerado['valor'], 2, ',', '.') ?> gerado!</p>
                            <textarea id="pix-copia" rows="3" readonly class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2 text-xs text-slate-300"><?= htmlspecialchars($pixGerado['codigo']) ?></textarea>
                            <button onclick="navigator.clipboard.writeText(document.getElementById('pix-copia').value); alert('PIX copiado!')" class="w-full py-2 rounded-xl bg-emerald-600 text-xs font-bold">Copiar PIX Copia e Cola</button>
                            <p class="text-[11px] text-slate-500">Assim que pagar, atualize a página e o saldo já estará disponível.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6">
                    <h3 class="text-sm font-bold mb-1">Sua Chave de API (Revenda)</h3>
                    <p class="text-xs text-slate-400 mb-3">Endpoint: <code class="text-emerald-400">/api_v2.php</code></p>
                    <input type="text" readonly value="<?= htmlspecialchars($user['api_key']) ?>" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-300 mb-2">
                    <form method="POST">
                        <input type="hidden" name="acao" value="nova_api_key">
                        <button class="text-xs text-slate-400 hover:text-white underline">Gerar nova chave</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 overflow-x-auto">
            <h2 class="text-lg font-bold mb-4">Meus Últimos Pedidos</h2>
            <table class="w-full text-xs text-left">
                <thead class="text-slate-400 border-b border-slate-800">
                    <tr><th class="py-2.5">Código</th><th>Serviço</th><th>Link</th><th>Qtd</th><th>Valor</th><th>Status</th><th>Data</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php foreach ($listaPedidos as $p): ?>
                    <tr>
                        <td class="py-3 font-bold text-emerald-400"><?= htmlspecialchars($p['codigo_rastreio']) ?></td>
                        <td><?= htmlspecialchars($p['titulo']) ?></td>
                        <td class="max-w-[180px] truncate text-slate-400"><?= htmlspecialchars($p['link_destino']) ?></td>
                        <td><?= number_format($p['quantidade'], 0, ',', '.') ?></td>
                        <td>R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
                        <td><span class="uppercase px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 font-semibold"><?= htmlspecialchars($p['status_entrega']) ?></span></td>
                        <td class="text-slate-500"><?= date('d/m H:i', strtotime($p['criado_em'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($listaPedidos)): ?>
                        <tr><td colspan="7" class="py-6 text-center text-slate-500">Você ainda não realizou nenhum pedido.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function filtrarServicosPainel() {
            const plat = document.getElementById('sel-plataforma').value;
            const sel = document.getElementById('sel-servico');
            let primeiroVisivel = null;
            Array.from(sel.options).forEach(opt => {
                const ok = plat === 'Todas' || opt.dataset.plat === plat;
                opt.hidden = !ok;
                if (ok && !primeiroVisivel) primeiroVisivel = opt;
            });
            if (primeiroVisivel) sel.value = primeiroVisivel.value;
            atualizarResumoPainel();
        }

        function atualizarResumoPainel() {
            const sel = document.getElementById('sel-servico');
            const opt = sel.options[sel.selectedIndex];
            if (!opt) return;
            const preco = parseFloat(opt.dataset.preco);
            const min = parseInt(opt.dataset.min);
            const max = parseInt(opt.dataset.max);
            const inp = document.getElementById('inp-qtd');
            inp.min = min;
            inp.max = max;
            document.getElementById('limites-qtd').innerText = `(Mín: ${min} | Máx: ${max})`;
            document.getElementById('desc-servico').innerText = opt.dataset.desc;
            const qtd = parseInt(inp.value) || 0;
            const total = (preco / 1000) * qtd;
            document.getElementById('valor-pedido').innerText = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }
        atualizarResumoPainel();
    </script>
</body>
</html>
