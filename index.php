<?php
require_once 'config.php';
$user = usuarioAtual($pdo);
$servicos = $pdo->query("SELECT * FROM servicos WHERE ativo = 1 ORDER BY id ASC LIMIT 9")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Blindagem Social — Painel SMM VIP | Seguidores, Curtidas e Views</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  html{scroll-behavior:smooth}
  body{background:#060608;color:#fafafa;line-height:1.6;overflow-x:hidden}
  a{text-decoration:none;color:inherit}
  .container{max-width:1180px;margin:0 auto;padding:0 20px}
  .gold{background:linear-gradient(135deg,#fde68a,#f59e0b,#d97706);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
  .gold-solid{color:#fbbf24}

  /* Barra Ao Vivo no Topo */
  .live-bar{background:linear-gradient(90deg,#12100a,#1c1608,#12100a);border-bottom:1px solid rgba(251,191,36,.25);height:36px;display:flex;align-items:center;overflow:hidden;font-size:12px}
  .live-track{display:flex;gap:48px;white-space:nowrap;animation:ticker 32s linear infinite;padding-left:100%}
  .live-track:hover{animation-play-state:paused}
  .live-item{display:inline-flex;align-items:center;gap:8px;color:#e4e4e7}
  .dot-live{width:7px;height:7px;border-radius:50%;background:#10b981;box-shadow:0 0 8px #10b981}
  @keyframes ticker{0%{transform:translateX(0)}100%{transform:translateX(-100%)}}

  /* Navbar */
  .nav{position:sticky;top:0;z-index:50;background:rgba(6,6,8,.88);backdrop-filter:blur(16px);border-bottom:1px solid rgba(255,255,255,.07)}
  .nav-inner{height:74px;display:flex;align-items:center;justify-content:space-between}
  .logo{display:flex;align-items:center;gap:11px;font-weight:900;font-size:21px;letter-spacing:-.5px}
  .logo-badge{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,#fde68a,#f59e0b,#b45309);color:#09090b;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:18px;box-shadow:0 0 24px rgba(245,158,11,.4)}
  .nav-links{display:flex;gap:26px;font-size:14px;font-weight:600;color:#a1a1aa}
  .nav-links a:hover{color:#fbbf24}

  /* Botões */
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 22px;border-radius:12px;font-weight:800;font-size:14px;border:none;cursor:pointer;transition:.25s}
  .btn-gold{background:linear-gradient(135deg,#fde68a 0%,#f59e0b 50%,#d97706 100%);color:#09090b;box-shadow:0 8px 28px rgba(245,158,11,.3)}
  .btn-gold:hover{transform:translateY(-2px);box-shadow:0 12px 34px rgba(245,158,11,.45);filter:brightness(1.05)}
  .btn-dark{background:rgba(24,24,27,.9);color:#fafafa;border:1px solid #27272a}
  .btn-dark:hover{border-color:#fbbf24;color:#fbbf24}

  /* Hero */
  .hero{padding:64px 0;background:radial-gradient(circle at 18% 15%,rgba(245,158,11,.16),transparent 45%),radial-gradient(circle at 85% 75%,rgba(217,119,6,.1),transparent 45%)}
  .hero-grid{display:grid;grid-template-columns:1.2fr .9fr;gap:44px;align-items:center}
  .pill{display:inline-flex;align-items:center;gap:8px;padding:7px 15px;border-radius:99px;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.35);color:#fbbf24;font-size:12px;font-weight:700;margin-bottom:18px}
  h1{font-size:46px;line-height:1.12;font-weight:900;letter-spacing:-1.2px;margin-bottom:18px}
  .sub{color:#a1a1aa;font-size:17px;margin-bottom:28px;max-width:560px}
  .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:34px;padding-top:24px;border-top:1px solid rgba(255,255,255,.08)}
  .stats strong{display:block;font-size:24px;font-weight:900;color:#fbbf24}
  .stats span{font-size:12px;color:#71717a}

  /* Card Login */
  .card{background:linear-gradient(180deg,#141418,#0e0e11);border:1px solid rgba(251,191,36,.22);border-radius:22px;padding:30px;box-shadow:0 24px 60px rgba(0,0,0,.75)}
  .alert{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.28);color:#fde68a;padding:10px 14px;border-radius:12px;font-size:12px;margin:12px 0 18px}
  .field{margin-bottom:14px}
  .field label{display:block;font-size:12px;color:#a1a1aa;margin-bottom:6px;font-weight:600}
  .field input{width:100%;padding:13px 14px;border-radius:12px;background:#070709;border:1px solid #27272a;color:#fff;font-size:14px;outline:none;transition:.2s}
  .field input:focus{border-color:#fbbf24;box-shadow:0 0 0 3px rgba(251,191,36,.12)}

  /* Plataformas */
  .platforms{background:#0b0b0e;border-top:1px solid #1f1f23;border-bottom:1px solid #1f1f23;padding:20px 0}
  .plat-list{display:flex;flex-wrap:wrap;justify-content:center;gap:12px}
  .plat-tag{background:#121215;border:1px solid #27272a;padding:9px 18px;border-radius:12px;font-size:13px;font-weight:700;color:#e4e4e7;transition:.2s}
  .plat-tag:hover{border-color:#fbbf24;color:#fbbf24}

  /* Seções & Cards */
  .section{padding:64px 0}
  .sec-title{text-align:center;margin-bottom:40px}
  .sec-title span{color:#fbbf24;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:1.4px}
  .sec-title h2{font-size:34px;font-weight:900;margin-top:6px;letter-spacing:-.5px}
  .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
  .srv-card{background:linear-gradient(180deg,#131317,#0d0d10);border:1px solid #27272a;border-radius:20px;padding:24px;display:flex;flex-direction:column;justify-content:space-between;transition:.25s}
  .srv-card:hover{border-color:#fbbf24;transform:translateY(-4px);box-shadow:0 16px 36px rgba(245,158,11,.12)}
  .badge{font-size:11px;font-weight:800;padding:4px 11px;border-radius:99px;background:rgba(245,158,11,.14);color:#fbbf24;border:1px solid rgba(245,158,11,.3)}
  .price{font-size:24px;font-weight:900;color:#fbbf24}

  /* Popup Prova Social */
  .toast{position:fixed;bottom:20px;left:20px;z-index:60;background:rgba(18,18,22,.96);backdrop-filter:blur(12px);border:1px solid rgba(251,191,36,.45);border-radius:16px;padding:13px 16px;display:flex;align-items:center;gap:12px;box-shadow:0 16px 40px rgba(0,0,0,.75);max-width:340px;transform:translateY(140%);opacity:0;transition:.45s cubic-bezier(.16,1,.3,1)}
  .toast.show{transform:translateY(0);opacity:1}
  .toast-ico{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,#fde68a,#d97706);color:#09090b;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:16px;flex-shrink:0}

  .faq details{background:#121215;border:1px solid #27272a;border-radius:14px;padding:16px 20px;margin-bottom:12px}
  .faq summary{font-weight:700;cursor:pointer;font-size:15px}
  .faq p{color:#a1a1aa;font-size:13px;margin-top:10px}
  footer{border-top:1px solid #1f1f23;padding:34px 0;color:#71717a;font-size:13px;text-align:center;background:#08080a}
  @media(max-width:860px){.hero-grid,.grid-3{grid-template-columns:1fr}.nav-links{display:none}h1{font-size:33px}.toast{left:12px;right:12px;max-width:none}}
</style>
</head>

<body>
<!-- Barra Ao Vivo Rodando no Topo -->
<div class="live-bar">
  <div class="live-track">
    <span class="live-item"><span class="dot-live"></span> <strong>@lucas.mendes</strong> acabou de comprar <strong class="gold-solid">1.000 Seguidores Instagram BR</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@camila_oliveira</strong> acabou de comprar <strong class="gold-solid">500 Curtidas Reais</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@pedrohenrique.oficial</strong> acabou de comprar <strong class="gold-solid">5.000 Visualizações Reels</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@studiobella_sp</strong> acabou de comprar <strong class="gold-solid">2.500 Seguidores Brasileiros</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@rafael.trader</strong> acabou de comprar <strong class="gold-solid">1.000 Seguidores TikTok</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@ana.clara99</strong> acabou de comprar <strong class="gold-solid">300 Curtidas Brasileiras</strong></span>
  </div>
</div>

<header class="nav">
  <div class="container nav-inner">
    <a href="index.php" class="logo">
      <span class="logo-badge">B</span>
      <span>Blindagem <span class="gold">Social</span></span>
    </a>
    <nav class="nav-links">
      <a href="#servicos">Catálogo VIP</a>
      <a href="#como">Como funciona</a>
      <a href="#depoimentos">Avaliações</a>
      <a href="#faq">Dúvidas</a>
      <a href="consultar.php">Rastrear</a>
    </nav>
    <div style="display:flex;gap:10px">
      <?php if ($user): ?>
        <a href="painel.php" class="btn btn-gold">Acessar Meu Painel &rarr;</a>
      <?php else: ?>
        <a href="auth.php?modo=login" class="btn btn-dark">Entrar</a>
        <a href="auth.php?modo=cadastro" class="btn btn-gold">Criar conta grátis</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="pill">👑 Plataforma Oficial • PIX Automático a partir de R$ 1,00</span>
      <h1>Transforme seu perfil em <span class="gold">autoridade real</span> em segundos</h1>
      <p class="sub">Seguidores brasileiros, curtidas, visualizações e engajamento automático para Instagram, TikTok e YouTube. Sem pedir sua senha.</p>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <a href="auth.php?modo=cadastro" class="btn btn-gold">Começar agora — Grátis &rarr;</a>
        <a href="#servicos" class="btn btn-dark">Ver preços e pacotes</a>
      </div>
      <div class="stats">
        <div><strong>+4,2 Milhões</strong><span>Pedidos entregues</span></div>
        <div><strong>R$ 1,00</strong><span>Recarga mínima PIX</span></div>
        <div><strong>24/7</strong><span>Envio automático</span></div>
      </div>
    </div>

    <div class="card">
      <h2 style="font-size:21px;font-weight:900">Painel <span class="gold">Blindagem Social</span></h2>
      <div class="alert">🔒 Segurança total: use apenas seu login do <strong>nosso site</strong>.</div>
      <?php if ($user): ?>
        <p style="font-size:14px;margin-bottom:16px">Bem-vindo, <strong class="gold-solid">@<?= htmlspecialchars($user['usuario']) ?></strong><br>Saldo disponível: <strong class="gold-solid">R$ <?= number_format($user['saldo'], 2, ',', '.') ?></strong></p>
        <a href="painel.php" class="btn btn-gold" style="width:100%">Abrir Meu Painel &rarr;</a>
      <?php else: ?>
        <form action="auth.php" method="POST">
          <input type="hidden" name="acao" value="entrar">
          <div class="field"><label>Usuário ou E-mail</label><input type="text" name="login" required placeholder="seu_usuario"></div>
          <div class="field"><label>Senha</label><input type="password" name="senha" required placeholder="••••••••"></div>
          <button type="submit" class="btn btn-gold" style="width:100%">Entrar no Painel &rarr;</button>
        </form>
        <p style="text-align:center;font-size:13px;color:#a1a1aa;margin-top:16px">Ainda não tem conta? <a href="auth.php?modo=cadastro" class="gold-solid" style="font-weight:800">Criar Conta Grátis</a></p>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="platforms">
  <div class="container plat-list">
    <span class="plat-tag">📸 Instagram</span>
    <span class="plat-tag">🎵 TikTok</span>
    <span class="plat-tag">▶️ YouTube</span>
    <span class="plat-tag">👍 Facebook</span>
    <span class="plat-tag">✈️ Telegram</span>
    <span class="plat-tag">🐦 Twitter / X</span>
    <span class="plat-tag">🎵 Kwai</span>
  </div>
</div>

<section id="como" class="section">
  <div class="container">
    <div class="sec-title"><span>Simples e Rápido</span><h2>Como impulsionar seu perfil em 3 passos</h2></div>
    <div class="grid-3">
      <div class="card"><span class="badge">01 • Cadastro</span><h3 style="margin:14px 0 8px;font-size:18px">Crie sua conta grátis</h3><p style="font-size:13px;color:#a1a1aa">Leva menos de 30 segundos. Não pedimos cartão de crédito nem senha de redes sociais.</p></div>
      <div class="card"><span class="badge">02 • Recarga PIX</span><h3 style="margin:14px 0 8px;font-size:18px">Adicione saldo na hora</h3><p style="font-size:13px;color:#a1a1aa">Recarregue a partir de R$ 1,00 via PIX Efí com liberação instantânea 24h por dia.</p></div>
      <div class="card"><span class="badge">03 • Resultado</span><h3 style="margin:14px 0 8px;font-size:18px">Envie seu pedido</h3><p style="font-size:13px;color:#a1a1aa">Escolha o pacote, cole o link público e acompanhe a entrega em tempo real no painel.</p></div>
    </div>
  </div>
</section>

<section id="servicos" class="section" style="padding-top:10px">
  <div class="container">
    <div class="sec-title"><span>Catálogo Exclusivo</span><h2>Pacotes mais vendidos hoje</h2></div>
    <div class="grid-3">
      <?php foreach ($servicos as $s): ?>
      <div class="srv-card">
        <div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <span class="badge"><?= htmlspecialchars($s['qualidade']) ?></span>
            <span style="font-size:12px;color:#71717a;font-weight:700"><?= htmlspecialchars($s['plataforma']) ?></span>
          </div>
          <h3 style="font-size:17px;margin-bottom:8px;font-weight:800"><?= htmlspecialchars($s['titulo']) ?></h3>
          <p style="font-size:13px;color:#a1a1aa"><?= htmlspecialchars($s['descricao']) ?></p>
        </div>
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid #27272a;display:flex;justify-content:space-between;align-items:center">
          <div><span style="font-size:11px;color:#71717a;display:block">A partir de (1.000)</span><span class="price">R$ <?= number_format($s['preco_por_mil'], 2, ',', '.') ?></span></div>
          <a href="<?= $user ? 'painel.php' : 'auth.php?modo=cadastro' ?>" class="btn btn-gold" style="padding:10px 18px;font-size:13px">Comprar &rarr;</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="depoimentos" class="section" style="padding-top:10px">
  <div class="container">
    <div class="sec-title"><span>Prova Social</span><h2>Quem usa a <span class="gold">Blindagem Social</span> recomenda</h2></div>
    <div class="grid-3">
      <div class="card"><p class="gold-solid" style="margin-bottom:8px">★★★★★</p><p style="font-size:13px;color:#d4d4d8">"Saí de 900 para 15 mil seguidores na minha loja em poucos dias. Passou muito mais credibilidade para fechar vendas no Instagram."</p><p style="font-size:12px;color:#71717a;margin-top:12px"><strong style="color:#fff">Mariana Costa</strong> • Empreendedora, SP</p></div>
      <div class="card"><p class="gold-solid" style="margin-bottom:8px">★★★★★</p><p style="font-size:13px;color:#d4d4d8">"O PIX cai na hora e os pedidos começam em segundos. Uso toda semana nos lançamentos dos meus clientes."</p><p style="font-size:12px;color:#71717a;margin-top:12px"><strong style="color:#fff">Rafael Mendes</strong> • Gestor de Tráfego, MG</p></div>
      <div class="card"><p class="gold-solid" style="margin-bottom:8px">★★★★★</p><p style="font-size:13px;color:#d4d4d8">"Interface muito rápida e suporte nota 10. Melhor painel que já utilizei até hoje."</p><p style="font-size:12px;color:#71717a;margin-top:12px"><strong style="color:#fff">Juliana Prado</strong> • Criadora de Conteúdo, PR</p></div>
    </div>
  </div>
</section>

<section id="faq" class="section faq" style="max-width:820px;margin:0 auto;padding-top:10px">
  <div class="sec-title"><span>Dúvidas</span><h2>Perguntas Frequentes</h2></div>
  <details><summary>Preciso informar minha senha do Instagram?</summary><p>Nunca. Trabalhamos exclusivamente com o link público do seu perfil ou postagem.</p></details>
  <details><summary>Qual o valor mínimo para adicionar saldo via PIX?</summary><p>A partir de R$ 1,00 via PIX Efí com liberação automática na sua carteira.</p></details>
  <details><summary>Em quanto tempo os seguidores ou curtidas chegam?</summary><p>O envio inicia automaticamente em segundos após você confirmar o pedido no painel.</p></details>
</section>

<footer>
  <div class="container">
    <p>&copy; <?= date('Y') ?> <strong class="gold-solid">Blindagem Social</strong> — Todos os direitos reservados. Não solicitamos senha das suas redes sociais.</p>
  </div>
</footer>

<!-- Popup Flutuante de Compras Recentes -->
<div id="live-toast" class="toast">
  <div class="toast-ico">👑</div>
  <div style="font-size:12px;line-height:1.4">
    <strong id="toast-nome" style="color:#fff;display:block">Lucas M. — São Paulo, SP</strong>
    <span id="toast-acao" style="color:#fbbf24;font-weight:700">Comprou 1.000 Seguidores Instagram</span>
    <span id="toast-tempo" style="color:#71717a;display:block;font-size:11px">há 14 segundos</span>
  </div>
</div>

<script>
const compras = [
  { nome: "Lucas M. — São Paulo, SP", acao: "Comprou 1.000 Seguidores Instagram BR", tempo: "há 12 segundos" },
  { nome: "Camila S. — Recife, PE", acao: "Comprou 500 Curtidas Brasileiras", tempo: "há 27 segundos" },
  { nome: "Gabriel R. — Curitiba, PR", acao: "Comprou 5.000 Visualizações Reels", tempo: "há 41 segundos" },
  { nome: "Fernanda L. — Belo Horizonte, MG", acao: "Comprou 2.000 Seguidores Instagram", tempo: "há 1 minuto" },
  { nome: "Matheus A. — Rio de Janeiro, RJ", acao: "Comprou 1.500 Seguidores TikTok", tempo: "há 1 minuto" },
  { nome: "Beatriz C. — Fortaleza, CE", acao: "Comprou 300 Curtidas Reais", tempo: "há 2 minutos" }
];
let idx = 0;
function mostrarToast() {
  const t = document.getElementById('live-toast');
  const item = compras[idx % compras.length];
  document.getElementById('toast-nome').innerText = item.nome;
  document.getElementById('toast-acao').innerText = item.acao;
  document.getElementById('toast-tempo').innerText = item.tempo;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 4800);
  idx++;
}
setTimeout(mostrarToast, 2500);
setInterval(mostrarToast, 9500);
</script>
</body>
</html>
