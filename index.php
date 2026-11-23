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
    <link href="https://googleapis.com" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col antialiased">

    <!-- Cabeçalho -->
    <header class="bg-white/95 backdrop-blur border-b border-slate-200/80 sticky top-0 z-30">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="text-xl font-extrabold tracking-tight text-slate-900 flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-tr from-fuchsia-600 to-indigo-600 text-white flex items-center justify-center text-sm font-black">B</span>
                Blindagem Social
            </a>
            <nav class="hidden md:flex items-center gap-7 text-sm font-medium text-slate-600">
                <a href="index.php" class="text-slate-900 font-semibold">Início</a>
                <a href="#servicos" class="hover:text-slate-900 transition">Serviços</a>
                <a href="consultar.php" class="hover:text-slate-900 transition">Consultar pedido</a>
                <a href="admin.php" class="hover:text-slate-900 transition">Admin</a>
            </nav>
            <div class="flex items-center gap-2.5">
                <a href="consultar.php" class="hidden sm:inline-block text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 px-3.5 py-2 rounded-xl transition">
                    Consultar pedido
                </a>
                <a href="#servicos" class="text-xs font-semibold text-white bg-slate-900 hover:bg-slate-800 px-4 py-2.5 rounded-xl shadow-sm transition">
                    Escolher serviço
                </a>
            </div>
        </div>
    </header>

    <!-- Hero -->
    <section class="bg-white border-b border-slate-200/80">
        <div class="max-w-6xl mx-auto px-4 pt-10 pb-12">
            <span class="inline-block text-xs font-bold tracking-widest uppercase text-fuchsia-600 bg-fuchsia-50 border border-fuchsia-200/70 px-3 py-1 rounded-full">
                SEU PERFIL. NOVAS POSSIBILIDADES.
            </span>
            <h1 class="text-3xl md:text-5xl font-extrabold text-slate-900 mt-4 tracking-tight">
                Um up no seu Instagram.
            </h1>
            <p class="text-slate-500 mt-3 text-base md:text-lg max-w-xl">
                Encontre o serviço e escolha a quantidade. O próximo passo é seu.
            </p>
        </div>
    </section>

    <!-- Filtros e Catálogo -->
    <main id="servicos" class="max-w-6xl mx-auto px-4 py-8 flex-1 w-full">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-sm mb-6 grid md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs uppercase tracking-wider text-slate-500 mb-2.5 font-bold">Tipo de serviço</label>
                <div class="flex flex-wrap gap-2" id="filtro-tipo">
                    <button data-val="Todos" class="filtro-btn bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-semibold transition">Todos</button>
                    <button data-val="Seguidores" class="filtro-btn bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold transition">Seguidores</button>
                    <button data-val="Curtidas" class="filtro-btn bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold transition">Curtidas</button>
                    <button data-val="Visualizacoes" class="filtro-btn bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold transition">Visualizações</button>
                </div>
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-slate-500 mb-2.5 font-bold">Qualidade do serviço</label>
                <div class="flex flex-wrap gap-2" id="filtro-qualidade">
                    <button data-val="Todas" class="qual-btn bg-fuchsia-600 text-white px-4 py-2 rounded-xl text-xs font-semibold transition">Todas</button>
                    <button data-val="Avancado" class="qual-btn bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold transition">Avançado</button>
                    <button data-val="Premium" class="qual-btn bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold transition">Premium</button>
                    <button data-val="Exclusivo" class="qual-btn bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold transition">Exclusivo</button>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between text-xs text-slate-500 mb-6 gap-2">
            <span><strong id="contador-opcoes" class="text-slate-900"><?= count($servicos) ?></strong> opções disponíveis</span>
            <span class="inline-flex items-center gap-1.5 text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full font-semibold">
                Sem compartilhar sua senha
            </span>
        </div>

        <!-- Grade de Serviços -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6" id="lista-servicos">
            <?php foreach ($servicos as $s):
                $badgeCor = match($s['qualidade']) {
                    'Exclusivo' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'Premium'   => 'bg-purple-50 text-purple-700 border-purple-200',
                    default     => 'bg-sky-50 text-sky-700 border-sky-200',
                };
            ?>
            <div class="card-servico bg-white border border-slate-200/90 hover:border-fuchsia-300 hover:shadow-md rounded-2xl p-6 flex flex-col justify-between transition"
                 data-tipo="<?= htmlspecialchars($s['tipo']) ?>"
                 data-qualidade="<?= htmlspecialchars($s['qualidade']) ?>">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="text-[11px] font-bold px-2.5 py-1 rounded-full border <?= $badgeCor ?>">
                            <?= htmlspecialchars($s['qualidade']) ?>
                        </span>
                        <span class="text-xs text-slate-400 font-medium">Instagram / <?= htmlspecialchars($s['tipo']) ?></span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900"><?= htmlspecialchars($s['titulo']) ?></h3>
                    <p class="text-sm text-slate-500 mt-2 leading-relaxed"><?= htmlspecialchars($s['descricao']) ?></p>
                    <p class="text-xs text-slate-400 mt-3 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Acompanhamento por link privado
                    </p>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100">
                    <div class="flex items-baseline justify-between mb-4">
                        <span class="text-xs text-slate-400 font-medium">A partir de</span>
                        <span class="text-2xl font-extrabold text-slate-900">
                            R$ <?= number_format($s['preco_por_mil'], 2, ',', '.') ?>
                        </span>
                    </div>
                    <button onclick='abrirModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, "UTF-8") ?>)'
                            class="w-full py-3 px-4 rounded-xl bg-slate-900 hover:bg-fuchsia-600 text-white font-semibold text-sm transition cursor-pointer">
                        Escolher quantidade
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Rodapé -->
    <footer class="bg-white border-t border-slate-200 mt-16">
        <div class="max-w-6xl mx-auto px-4 py-12 grid md:grid-cols-3 gap-8 text-sm">
            <div>
                <p class="text-lg font-extrabold text-slate-900">Um novo passo para a sua presença digital.</p>
                <span class="inline-block mt-3 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full">
                    Pagamento via PIX
                </span>
                <p class="text-xs text-slate-400 mt-3">Não solicitamos a senha das suas redes sociais.</p>
            </div>
            <div>
                <p class="font-bold text-slate-900 mb-3">Explore</p>
                <ul class="space-y-2 text-slate-500 text-xs">
                    <li><a href="#servicos" class="hover:text-slate-900">Nossos serviços</a></li>
                    <li><a href="consultar.php" class="hover:text-slate-900">Consultar pedido</a></li>
                    <li><a href="admin.php" class="hover:text-slate-900">Painel administrativo</a></li>
                </ul>
            </div>
            <div>
                <p class="font-bold text-slate-900 mb-3">Informações</p>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Deixe o perfil em modo público antes de concluir o pedido. Entrega automática após confirmação do PIX.
                </p>
            </div>
        </div>
    </footer>

    <!-- Modal de Checkout PIX -->
    <div id="modal-compra" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-2xl">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span id="modal-qualidade" class="text-[11px] uppercase tracking-wider text-fuchsia-600 font-bold"></span>
                    <h2 id="modal-titulo" class="text-lg font-extrabold text-slate-900">Configurar Pedido</h2>
                </div>
                <button onclick="fecharModal()" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
            </div>

            <form id="form-pedido" class="space-y-4">
                <input type="hidden" id="servico-id">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Quantidade desejada</label>
                    <input type="number" id="quantidade" value="1000" min="100" step="100" required
                           class="w-full bg-slate-50 border border-slate-300 focus:border-fuchsia-600 outline-none rounded-xl px-3.5 py-2.5 text-slate-900 font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Link do Perfil ou Post (Perfil Público)</label>
                    <input type="text" id="link" placeholder="https://instagram.com" required
                           class="w-full bg-slate-50 border border-slate-300 focus:border-fuchsia-600 outline-none rounded-xl px-3.5 py-2.5 text-slate-900">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Seu E-mail (para recibo e acompanhamento)</label>
                    <input type="email" id="email" placeholder="voce@email.com" required
                           class="w-full bg-slate-50 border border-slate-300 focus:border-fuchsia-600 outline-none rounded-xl px-3.5 py-2.5 text-slate-900">
                </div>

                <div class="bg-slate-50 p-3.5 rounded-xl flex justify-between items-center border border-slate-200">
                    <span class="text-sm text-slate-500 font-medium">Total via PIX:</span>
                    <span id="preco-calculado" class="text-xl font-extrabold text-emerald-600">R$ 0,00</span>
                </div>

                <button type="submit" id="btn-gerar" class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-sm transition cursor-pointer">
                    Gerar Pagamento PIX
                </button>
            </form>

            <div id="resultado-pix" class="hidden space-y-3 text-center">
                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3">
                    <p class="text-xs text-emerald-700 font-medium">Pedido registrado com sucesso!</p>
                    <p class="text-sm font-extrabold text-slate-900 mt-1">Código: <span id="cod-rastreio" class="text-fuchsia-600"></span></p>
                </div>
                <p class="text-xs text-slate-500">Copie o código PIX Copia e Cola abaixo:</p>
                <textarea id="pix-texto" rows="3" readonly class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs text-slate-700"></textarea>
                <button onclick="copiarPix()" class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-fuchsia-600 text-white text-xs font-semibold transition">
                    Copiar Código PIX
                </button>
                <a id="link-rastrear" href="consultar.php" class="block w-full py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold transition">
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
                    b.classList.remove('bg-slate-900', 'text-white');
                    b.classList.add('bg-slate-100', 'text-slate-700');
                });
                btn.classList.remove('bg-slate-100', 'text-slate-700');
                btn.classList.add('bg-slate-900', 'text-white');
                tipoAtual = btn.dataset.val;
                filtrar();
            };
        });

        document.querySelectorAll('.qual-btn').forEach(btn => {
            btn.onclick = () => {
                document.querySelectorAll('.qual-btn').forEach(b => {
                    b.classList.remove('bg-fuchsia-600', 'text-white');
                    b.classList.add('bg-slate-100', 'text-slate-700');
                });
                btn.classList.remove('bg-slate-100', 'text-slate-700');
                btn.classList.add('bg-fuchsia-600', 'text-white');
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
