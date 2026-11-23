<?php
require_once 'config.php';
$user = usuarioAtual($pdo);
$servicos = $pdo->query("SELECT * FROM servicos WHERE ativo = 1 ORDER BY id ASC LIMIT 6")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blindagem Social — Painel SMM Brasileiro | Seguidores, Curtidas e Views</title>
    <script src="https://tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased">

    <!-- Navbar -->
    <header class="bg-slate-950/90 backdrop-blur border-b border-slate-800/80 sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="text-xl font-extrabold flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl bg-emerald-500 text-slate-950 flex items-center justify-center font-black">S</span>
                <span>Blindagem<span class="text-emerald-400">SMM</span></span>
            </a>
            <nav class="hidden md:flex items-center gap-6 text-sm text-slate-300">
                <a href="#servicos" class="hover:text-white">Serviços</a>
                <a href="#como-funciona" class="hover:text-white">Como funciona</a>
                <a href="#api" class="hover:text-white">API</a>
                <a href="#faq" class="hover:text-white">Perguntas Frequentes</a>
                <a href="consultar.php" class="hover:text-white">Rastrear</a>
            </nav>
            <div class="flex items-center gap-2.5">
                <?php if ($user): ?>
                    <a href="painel.php" class="bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-bold px-4 py-2.5 rounded-xl">
                        Acessar Meu Painel &rarr;
                    </a>
                <?php else: ?>
                    <a href="auth.php?modo=login" class="text-xs font-semibold text-slate-300 hover:text-white px-3 py-2">Entrar</a>
                    <a href="auth.php?modo=cadastro" class="bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-bold px-4 py-2.5 rounded-xl">
                        Criar conta grátis
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero + Caixa de Login Integrada -->
    <section class="max-w-6xl mx-auto px-4 py-12 grid lg:grid-cols-12 gap-10 items-center">
        <div class="lg:col-span-7 space-y-5">
            <span class="inline-flex items-center gap-2 text-xs font-bold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-3.5 py-1.5 rounded-full">
                🇧🇷 Painel SMM Automático • Pagamento via PIX a partir de R$ 1
            </span>
            <h1 class="text-3xl sm:text-5xl font-extrabold leading-tight tracking-tight">
                Cresça nas redes sociais com entrega automática em segundos
            </h1>
            <p class="text-slate-400 text-base sm:text-lg leading-relaxed">
                Seguidores reais, curtidas, visualizações e muito mais para Instagram, TikTok, YouTube e outras plataformas. Sem precisar informar sua senha.
            </p>
            <div class="flex flex-wrap gap-3 pt-1">
                <a href="auth.php?modo=cadastro" class="bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm px-6 py-3.5 rounded-xl transition">
                    Criar conta grátis
                </a>
                <a href="#servicos" class="bg-slate-900 hover:bg-slate-800 border border-slate-800 text-white font-semibold text-sm px-6 py-3.5 rounded-xl transition">
                    Ver todos os serviços
                </a>
            </div>
            <div class="grid grid-cols-3 gap-4 pt-4 border-t border-slate-800/80 text-xs">
                <div><strong class="block text-lg text-white">+4 Milhões</strong><span class="text-slate-400">Pedidos entregues</span></div>
                <div><strong class="block text-lg text-emerald-400">R$ 1,00</strong><span class="text-slate-400">Recarga mínima PIX</span></div>
                <div><strong class="block text-lg text-white">24/7</strong><span class="text-slate-400">Entrega automática</span></div>
            </div>
        </div>

        <!-- Card de Login Rápido (estilo SMM Oficial) -->
        <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl">
            <h2 class="text-lg font-bold">Acesse seu Painel SMM</h2>
            <p class="text-xs text-amber-400 bg-amber-500/10 border border-amber-500/20 rounded-xl p-2.5 mt-2 mb-4">
                Use seu login do <strong>nosso site</strong>, nunca a senha do Instagram.
            </p>

            <?php if ($user): ?>
                <div class="bg-slate-950 border border-slate-800 rounded-xl p-4 text-center space-y-3">
                    <p class="text-sm">Você já está conectado como <strong class="text-emerald-400">@<?= htmlspecialchars($user['usuario']) ?></strong></p>
                    <p class="text-xs text-slate-400">Saldo disponível: <strong class="text-white">R$ <?= number_format($user['saldo'], 2, ',', '.') ?></strong></p>
                    <a href="painel.php" class="block w-full py-3 rounded-xl bg-emerald-500 text-slate-950 font-bold text-sm">Ir para o Painel &rarr;</a>
                </div>
            <?php else: ?>
                <form action="auth.php" method="POST" class="space-y-3.5">
                    <input type="hidden" name="acao" value="entrar">
                    <div>
                        <label class="text-xs text-slate-400">Usuário ou E-mail</label>
                        <input type="text" name="login" required placeholder="seu_usuario" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm mt-1">
                    </div>
                    <div>
                        <label class="text-xs text-slate-400">Senha</label>
                        <input type="password" name="senha" required placeholder="••••••••" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm mt-1">
                    </div>
                    <button type="submit" class="w-full py-3 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm cursor-pointer">
                        Fazer Login &rarr;
                    </button>
                </form>
                <p class="text-center text-xs text-slate-400 mt-4">
                    Ainda não tem conta? <a href="auth.php?modo=cadastro" class="text-emerald-400 font-bold hover:underline">Criar Conta Grátis</a>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Plataformas -->
    <section class="border-y border-slate-800/80 bg-slate-900/40 py-5">
        <div class="max-w-6xl mx-auto px-4 flex flex-wrap justify-center gap-3 text-xs font-semibold text-slate-300">
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">📸 Instagram</span>
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">🎵 TikTok</span>
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">▶️ YouTube</span>
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">👍 Facebook</span>
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">✈️ Telegram</span>
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">🐦 Twitter / X</span>
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">🎵 Kwai</span>
        </div>
    </section>

<?php
require_once 'config.php';
$user = usuarioAtual($pdo);
$servicos = $pdo->query("SELECT * FROM servicos WHERE ativo = 1 ORDER BY id ASC LIMIT 6")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blindagem Social — Painel SMM Brasileiro | Seguidores, Curtidas e Views</title>
    <script src="https://tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased">

    <!-- Navbar -->
    <header class="bg-slate-950/90 backdrop-blur border-b border-slate-800/80 sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="index.php" class="text-xl font-extrabold flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl bg-emerald-500 text-slate-950 flex items-center justify-center font-black">S</span>
                <span>Blindagem<span class="text-emerald-400">SMM</span></span>
            </a>
            <nav class="hidden md:flex items-center gap-6 text-sm text-slate-300">
                <a href="#servicos" class="hover:text-white">Serviços</a>
                <a href="#como-funciona" class="hover:text-white">Como funciona</a>
                <a href="#api" class="hover:text-white">API</a>
                <a href="#faq" class="hover:text-white">Perguntas Frequentes</a>
                <a href="consultar.php" class="hover:text-white">Rastrear</a>
            </nav>
            <div class="flex items-center gap-2.5">
                <?php if ($user): ?>
                    <a href="painel.php" class="bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-bold px-4 py-2.5 rounded-xl">
                        Acessar Meu Painel &rarr;
                    </a>
                <?php else: ?>
                    <a href="auth.php?modo=login" class="text-xs font-semibold text-slate-300 hover:text-white px-3 py-2">Entrar</a>
                    <a href="auth.php?modo=cadastro" class="bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-bold px-4 py-2.5 rounded-xl">
                        Criar conta grátis
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero + Caixa de Login Integrada -->
    <section class="max-w-6xl mx-auto px-4 py-12 grid lg:grid-cols-12 gap-10 items-center">
        <div class="lg:col-span-7 space-y-5">
            <span class="inline-flex items-center gap-2 text-xs font-bold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-3.5 py-1.5 rounded-full">
                🇧🇷 Painel SMM Automático • Pagamento via PIX a partir de R$ 1
            </span>
            <h1 class="text-3xl sm:text-5xl font-extrabold leading-tight tracking-tight">
                Cresça nas redes sociais com entrega automática em segundos
            </h1>
            <p class="text-slate-400 text-base sm:text-lg leading-relaxed">
                Seguidores reais, curtidas, visualizações e muito mais para Instagram, TikTok, YouTube e outras plataformas. Sem precisar informar sua senha.
            </p>
            <div class="flex flex-wrap gap-3 pt-1">
                <a href="auth.php?modo=cadastro" class="bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm px-6 py-3.5 rounded-xl transition">
                    Criar conta grátis
                </a>
                <a href="#servicos" class="bg-slate-900 hover:bg-slate-800 border border-slate-800 text-white font-semibold text-sm px-6 py-3.5 rounded-xl transition">
                    Ver todos os serviços
                </a>
            </div>
            <div class="grid grid-cols-3 gap-4 pt-4 border-t border-slate-800/80 text-xs">
                <div><strong class="block text-lg text-white">+4 Milhões</strong><span class="text-slate-400">Pedidos entregues</span></div>
                <div><strong class="block text-lg text-emerald-400">R$ 1,00</strong><span class="text-slate-400">Recarga mínima PIX</span></div>
                <div><strong class="block text-lg text-white">24/7</strong><span class="text-slate-400">Entrega automática</span></div>
            </div>
        </div>

        <!-- Card de Login Rápido (estilo SMM Oficial) -->
        <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl">
            <h2 class="text-lg font-bold">Acesse seu Painel SMM</h2>
            <p class="text-xs text-amber-400 bg-amber-500/10 border border-amber-500/20 rounded-xl p-2.5 mt-2 mb-4">
                Use seu login do <strong>nosso site</strong>, nunca a senha do Instagram.
            </p>

            <?php if ($user): ?>
                <div class="bg-slate-950 border border-slate-800 rounded-xl p-4 text-center space-y-3">
                    <p class="text-sm">Você já está conectado como <strong class="text-emerald-400">@<?= htmlspecialchars($user['usuario']) ?></strong></p>
                    <p class="text-xs text-slate-400">Saldo disponível: <strong class="text-white">R$ <?= number_format($user['saldo'], 2, ',', '.') ?></strong></p>
                    <a href="painel.php" class="block w-full py-3 rounded-xl bg-emerald-500 text-slate-950 font-bold text-sm">Ir para o Painel &rarr;</a>
                </div>
            <?php else: ?>
                <form action="auth.php" method="POST" class="space-y-3.5">
                    <input type="hidden" name="acao" value="entrar">
                    <div>
                        <label class="text-xs text-slate-400">Usuário ou E-mail</label>
                        <input type="text" name="login" required placeholder="seu_usuario" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm mt-1">
                    </div>
                    <div>
                        <label class="text-xs text-slate-400">Senha</label>
                        <input type="password" name="senha" required placeholder="••••••••" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm mt-1">
                    </div>
                    <button type="submit" class="w-full py-3 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm cursor-pointer">
                        Fazer Login &rarr;
                    </button>
                </form>
                <p class="text-center text-xs text-slate-400 mt-4">
                    Ainda não tem conta? <a href="auth.php?modo=cadastro" class="text-emerald-400 font-bold hover:underline">Criar Conta Grátis</a>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Plataformas -->
    <section class="border-y border-slate-800/80 bg-slate-900/40 py-5">
        <div class="max-w-6xl mx-auto px-4 flex flex-wrap justify-center gap-3 text-xs font-semibold text-slate-300">
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">📸 Instagram</span>
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">🎵 TikTok</span>
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">▶️ YouTube</span>
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">👍 Facebook</span>
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">✈️ Telegram</span>
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">🐦 Twitter / X</span>
            <span class="bg-slate-900 border border-slate-800 px-3.5 py-2 rounded-xl">🎵 Kwai</span>
        </div>
    </section>
