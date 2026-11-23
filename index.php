<?php
require_once 'config.php';
$servicos = $pdo->query("SELECT * FROM servicos WHERE ativo = 1 ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blindagem Social — Seguidores, Curtidas e Visualizações</title>
    <script src="https://tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased">

    <!-- Cabeçalho -->
    <header class="border-b border-slate-800/80 bg-slate-950/90 backdrop-blur sticky top-0 z-30">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="text-xl font-extrabold tracking-tight bg-gradient-to-r from-pink-500 via-rose-500 to-violet-500 bg-clip-text text-transparent">
                Blindagem Social
            </a>
            <nav class="hidden md:flex items-center gap-6 text-sm text-slate-300">
                <a href="index.php" class="text-white font-medium">Início</a>
                <a href="#servicos" class="hover:text-white transition">Serviços</a>
                <a href="consultar.php" class="hover:text-white transition">Consultar pedido</a>
                <a href="admin.php" class="hover:text-white transition">Painel Admin</a>
            </nav>
            <div class="flex items-center gap-2">
                <a href="consultar.php" class="bg-slate-900 hover:bg-slate-800 text-xs font-medium px-3.5 py-2 rounded-xl border border-slate-800 transition">
                    Consultar pedido
                </a>
                <a href="#servicos" class="bg-gradient-to-r from-pink-600 to-violet-600 hover:opacity-90 text-xs font-semibold px-4 py-2 rounded-xl transition">
                    Escolher serviço
                </a>
            </div>
        </div>
    </header>

    <!-- Hero -->
    <section class="max-w-6xl mx-auto px-4 pt-10 pb-6 w-full">
        <span class="inline-block text-xs font-semibold tracking-widest uppercase text-pink-400 bg-pink-500/10 border border-pink-500/20 px-3 py-1 rounded-full">
            SEU PERFIL. NOVAS POSSIBILIDADES.
        </span>
        <h1 class="text-3xl md:text-5xl font-extrabold mt-4 tracking-tight">
            Um up no seu Instagram.
        </h1>
        <p class="text-slate-400 mt-2 text-base md:text-lg max-w-2xl">
            Encontre o serviço e escolha a quantidade. O próximo passo é seu.
        </p>
    </section>

    <!-- Filtros e Catálogo -->
    <main id="servicos" class="max-w-6xl mx-auto px-4 py-6 flex-1 w-full">
        <div class="grid md:grid-cols-2 gap-4 bg-slate-900/90 p-5 rounded-2xl border border-slate-800 mb-6">
            <div>
                <label class="block text-xs uppercase tracking-wider text-slate-400 mb-2.5 font-semibold">Tipo de serviço</label>
                <div class="flex flex-wrap gap-2" id="filtro-tipo">
                    <button data-val="Todos" class="filtro-btn bg-pink-600 text-white px-3.5 py-1.5 rounded-xl text-sm font-medium transition">Todos</button>
                    <button data-val="Seguidores" class="filtro-btn bg-slate-800 hover:bg-slate-700 text-slate-300 px-3.5 py-1.5 rounded-xl text-sm transition">Seguidores</button>
                    <button data-val="Curtidas" class="filtro-btn bg-slate-800 hover:bg-slate-700 text-slate-300 px-3.5 py-1.5 rounded-xl text-sm transition">Curtidas</button>
                    <button data-val="Visualizacoes" class="filtro-btn bg-slate-800 hover:bg-slate-700 text-slate-300 px-3.5 py-1.5 rounded-xl text-sm transition">Visualizações</button>
                </div>
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-slate-400 mb-2.5 font-semibold">Qualidade do serviço</label>
                <div class="flex flex-wrap gap-2" id="filtro-qualidade">
                    <button data-val="Todas" class="qual-btn bg-violet-600 text-white px-3.5 py-1.5 rounded-xl text-sm font-medium transition">Todas</button>
                    <button data-val="Avancado" class="qual-btn bg-slate-800 hover:bg-slate-700 text-slate-300 px-3.5 py-1.5 rounded-xl text-sm transition">Avançado</button>
                    <button data-val="Premium" class="qual-btn bg-slate-800 hover:bg-slate-700 text-slate-300 px-3.5 py-1.5 rounded-xl text-sm transition">Premium</button>
                    <button data-val="Exclusivo" class="qual-btn bg-slate-800 hover:bg-slate-700 text-slate-300 px-3.5 py-1.5 rounded-xl text-sm transition">Exclusivo</button>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between text-xs text-slate-400 mb-6 gap-2">
            <span><strong id="contador-opcoes" class="text-white"><?= count($servicos) ?></strong> opções disponíveis</span>
            <span class="text-emerald-400 font-medium">✓ Sem compartilhar sua senha</span>
        </div>

        <!-- Grade de Cards -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6" id="lista-servicos">
            <?php foreach ($servicos as $s):
                $badgeCor = match($s['qualidade']) {
                    'Exclusivo' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                    'Premium'   => 'bg-violet-500/10 text-violet-400 border-violet-500/20',
                    default     => 'bg-sky-500/10 text-sky-400 border-sky-500/20',
                };
            ?>
            <div class="card-servico bg-slate-900 border border-slate-800 hover:border-pink-500/50 rounded-2xl p-6 flex flex-col justify-between transition"
                 data-tipo="<?= htmlspecialchars($s['tipo']) ?>"
                 data-qualidade="<?= htmlspecialchars($s['qualidade']) ?>">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full border <?= $badgeCor ?>">
                            <?= htmlspecialchars($s['qualidade']) ?>
                        </span>
                        <span class="text-xs text-slate-500">Instagram / <?= htmlspecialchars($s['tipo']) ?></span>
                    </div>
                    <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($s['titulo']) ?></h3>
                    <p class="text-sm text-slate-400 mt-2 leading-relaxed"><?= htmlspecialchars($s['descricao']) ?></p>
                    <p class="text-xs text-slate-500 mt-3">Acompanhamento por link privado</p>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-800/80">
                    <div class="flex items-baseline justify-between mb-4">
                        <span class="text-xs text-slate-400">A partir de</span>
                        <span class="text-2xl font-extrabold text-white">
                            R$ <?= number_format($s['preco_por_mil'], 2, ',', '.') ?>
                        </span>
                    </div>
                    <button onclick='abrirModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, "UTF-8") ?>)'
                            class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-pink-600 to-violet-600 hover:opacity-90 font-semibold text-sm transition cursor-pointer">
                        Escolher quantidade
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Rodapé -->
    <footer class="border-t border-slate-800 bg-slate-900/50 mt-16">
        <div class="max-w-6xl mx-auto px-4 py-10 grid md:grid-cols-3 gap-8 text-sm">
            <div>
                <p class="text-lg font-bold text-white">Um novo passo para a sua presença digital.</p>
                <p class="text-xs text-emerald-400 mt-2 font-medium">Pagamento instantâneo via PIX</p>
                <p class="text-xs text-slate-500 mt-2">Não solicitamos a senha das suas redes sociais.</p>
            </div>
            <div>
                <p class="font-semibold text-white mb-2">Explore</p>
                <ul class="space-y-1.5 text-slate-400 text-xs">
                    <li><a href="#servicos" class="hover:text-white">Nossos serviços</a></li>
                    <li><a href="consultar.php" class="hover:text-white">Consultar pedido</a></li>
                    <li><a href="admin.php" class="hover:text-white">Área administrativa</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold text-white mb-2">Segurança</p>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Certifique-se de deixar o perfil do Instagram em modo público antes de finalizar o pedido para garantir a entrega.
                </p>
            </div>
        </div>
    </footer>
    <!-- Modal de Checkout PIX -->
    <div id="modal-compra" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <span id="modal-qualidade" class="text-[11px] uppercase tracking-wider text-pink-400 font-semibold"></span>
                    <h2 id="modal-titulo" class="text-lg font-bold text-white">Configurar Pedido</h2>
                </div>
                <button onclick="fecharModal()" class="text-slate-400 hover:text-white text-2xl leading-none">&times;</button>
            </div>

            <form id="form-pedido" class="space-y-4">
                <input type="hidden" id="servico-id">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Quantidade desejada</label>
                    <input type="number" id="quantidade" value="1000" min="100" step="100" required
                           class="w-full bg-slate-950 border border-slate-700 focus:border-pink-500 outline-none rounded-xl px-3.5 py-2.5 text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Link do Perfil ou Post (Perfil Público)</label>
                    <input type="text" id="link" placeholder="https://instagram.com" required
                           class="w-full bg-slate-950 border border-slate-700 focus:border-pink-500 outline-none rounded-xl px-3.5 py-2.5 text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Seu E-mail (para recibo e acompanhamento)</label>
                    <input type="email" id="email" placeholder="voce@email.com" required
                           class="w-full bg-slate-950 border border-slate-700 focus:border-pink-500 outline-none rounded-xl px-3.5 py-2.5 text-white">
                </div>

                <div class="bg-slate-950 p-3.5 rounded-xl flex justify-between items-center border border-slate-800">
                    <span class="text-sm text-slate-400">Total via PIX:</span>
                    <span id="preco-calculado" class="text-xl font-extrabold text-emerald-400">R$ 0,00</span>
                </div>

                <button type="submit" id="btn-gerar" class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 font-bold text-sm transition cursor-pointer">
                    Gerar Pagamento PIX
                </button>
            </form>

            <div id="resultado-pix" class="hidden space-y-3 text-center">
                <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-xl p-3">
                    <p class="text-xs text-emerald-400">Pedido registrado com sucesso!</p>
                    <p class="text-sm font-bold text-white mt-1">Código: <span id="cod-rastreio" class="text-pink-400"></span></p>
                </div>
                <p class="text-xs text-slate-400">Copie o código PIX Copia e Cola abaixo:</p>
                <textarea id="pix-texto" rows="3" readonly class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2.5 text-xs text-slate-300"></textarea>
                <button onclick="copiarPix()" class="w-full py-2.5 rounded-xl bg-pink-600 hover:bg-pink-500 text-xs font-semibold">
                    Copiar Código PIX
                </button>
                <a id="link-rastrear" href="consultar.php" class="block w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-medium">
                    Acompanhar meu pedido
                </a>
            </div>
        </div>
    </div>

    <script>
        let tipoAtual = 'Todos', qualAtual = 'Todas', precoMilAtual = 0;

        function filtrar() {
            let visiveis = 0;
            document.querySelectorAll('.card-servico').forEach(card => {
                const okTipo = tipoAtual === 'Todos' || card.dataset.tipo === tipoAtual;
                const okQual = qualAtual === 'Todas' || card.dataset.qualidade === qualAtual;
                const mostrar = okTipo && okQual;
                card.style.display = mostrar ? 'flex' : 'none';
                if (mostrar) visiveis++;
            });
            document.getElementById('contador-opcoes').innerText = visiveis;
        }

        document.querySelectorAll('.filtro-btn').forEach(btn => {
            btn.onclick = () => {
                document.querySelectorAll('.filtro-btn').forEach(b => {
                    b.classList.remove('bg-pink-600', 'text-white', 'font-medium');
                    b.classList.add('bg-slate-800', 'text-slate-300');
                });
                btn.classList.remove('bg-slate-800', 'text-slate-300');
                btn.classList.add('bg-pink-600', 'text-white', 'font-medium');
                tipoAtual = btn.dataset.val;
                filtrar();
            };
        });

        document.querySelectorAll('.qual-btn').forEach(btn => {
            btn.onclick = () => {
                document.querySelectorAll('.qual-btn').forEach(b => {
                    b.classList.remove('bg-violet-600', 'text-white', 'font-medium');
                    b.classList.add('bg-slate-800', 'text-slate-300');
                });
                btn.classList.remove('bg-slate-800', 'text-slate-300');
                btn.classList.add('bg-violet-600', 'text-white', 'font-medium');
                qualAtual = btn.dataset.val;
                filtrar();
            };
        });

        function abrirModal(servico) {
            document.getElementById('servico-id').value = servico.id;
            document.getElementById('modal-titulo').innerText = servico.titulo;
            document.getElementById('modal-qualidade').innerText = servico.qualidade + ' • ' + servico.tipo;
            document.getElementById('quantidade').min = servico.minimo;
            document.getElementById('quantidade').value = servico.minimo;
            precoMilAtual = parseFloat(servico.preco_por_mil);
            atualizarPreco();
            document.getElementById('form-pedido').classList.remove('hidden');
            document.getElementById('resultado-pix').classList.add('hidden');
            document.getElementById('modal-compra').classList.replace('hidden', 'flex');
        }

        function fecharModal() {
            document.getElementById('modal-compra').classList.replace('flex', 'hidden');
        }

        function atualizarPreco() {
            const qtd = parseInt(document.getElementById('quantidade').value) || 0;
            const total = (precoMilAtual / 1000) * qtd;
            document.getElementById('preco-calculado').innerText = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        function copiarPix() {
            const campo = document.getElementById('pix-texto');
            campo.select();
            navigator.clipboard.writeText(campo.value);
            alert('Código PIX copiado!');
        }

        document.getElementById('quantidade').addEventListener('input', atualizarPreco);

        document.getElementById('form-pedido').onsubmit = async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btn-gerar');
            btn.disabled = true;
            btn.innerText = 'Gerando...';
            try {
                const res = await fetch('api.php?acao=criar_pedido', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        servico_id: document.getElementById('servico-id').value,
                        quantidade: document.getElementById('quantidade').value,
                        link: document.getElementById('link').value,
                        email: document.getElementById('email').value
                    })
                });
                const data = await res.json();
                if (data.sucesso) {
                    document.getElementById('form-pedido').classList.add('hidden');
                    document.getElementById('resultado-pix').classList.remove('hidden');
                    document.getElementById('cod-rastreio').innerText = data.codigo;
                    document.getElementById('pix-texto').value = data.pix_copia_cola;
                    document.getElementById('link-rastrear').href = 'consultar.php?codigo=' + encodeURIComponent(data.codigo);
                } else {
                    alert(data.erro || 'Erro ao gerar pedido');
                }
            } catch (err) {
                alert('Erro de conexão com o servidor.');
            } finally {
                btn.disabled = false;
                btn.innerText = 'Gerar Pagamento PIX';
            }
        };
    </script>
</body>
</html>
