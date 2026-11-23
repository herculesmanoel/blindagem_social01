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
<title>Blindagem SMM — O Maior Painel SMM do Brasil</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  body{background:#070b14;color:#f1f5f9;line-height:1.6}
  a{text-decoration:none;color:inherit}
  .container{max-width:1160px;margin:0 auto;padding:0 20px}
  .nav{position:sticky;top:0;z-index:50;background:rgba(7,11,20,.9);backdrop-filter:blur(12px);border-bottom:1px solid #1e293b}
  .nav-inner{height:70px;display:flex;align-items:center;justify-content:space-between}
  .logo{display:flex;align-items:center;gap:10px;font-weight:800;font-size:20px}
  .logo-badge{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#10b981,#059669);color:#04100a;display:flex;align-items:center;justify-content:center;font-weight:900}
  .nav-links{display:flex;gap:24px;font-size:14px;color:#94a3b8}
  .nav-links a:hover{color:#fff}
  .btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 22px;border-radius:12px;font-weight:700;font-size:14px;border:none;cursor:pointer;transition:.2s}
  .btn-green{background:linear-gradient(135deg,#10b981,#059669);color:#04100a;box-shadow:0 8px 24px rgba(16,185,129,.25)}
  .btn-green:hover{transform:translateY(-1px);filter:brightness(1.08)}
  .btn-dark{background:#111827;color:#fff;border:1px solid #1f2937}
  .btn-dark:hover{border-color:#10b981}
  .hero{padding:60px 0;background:radial-gradient(circle at 20% 20%,rgba(16,185,129,.12),transparent 45%)}
  .hero-grid{display:grid;grid-template-columns:1.2fr .9fr;gap:40px;align-items:center}
  .pill{display:inline-block;padding:6px 14px;border-radius:99px;background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#34d399;font-size:12px;font-weight:700;margin-bottom:16px}
  h1{font-size:44px;line-height:1.15;font-weight:800;letter-spacing:-1px;margin-bottom:16px}
  .sub{color:#94a3b8;font-size:17px;margin-bottom:28px}
  .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:32px;padding-top:24px;border-top:1px solid #1e293b}
  .stats strong{display:block;font-size:22px;color:#fff}
  .stats span{font-size:12px;color:#64748b}
  .card{background:#0f172a;border:1px solid #1e293b;border-radius:20px;padding:28px;box-shadow:0 20px 50px rgba(0,0,0,.4)}
  .alert{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#fbbf24;padding:10px 14px;border-radius:12px;font-size:12px;margin:12px 0 18px}
  .field{margin-bottom:14px}
  .field label{display:block;font-size:12px;color:#94a3b8;margin-bottom:6px;font-weight:600}
  .field input{width:100%;padding:12px 14px;border-radius:12px;background:#070b14;border:1px solid #1e293b;color:#fff;font-size:14px;outline:none}
  .field input:focus{border-color:#10b981}
  .platforms{background:#0b1120;border-top:1px solid #1e293b;border-bottom:1px solid #1e293b;padding:18px 0}
  .plat-list{display:flex;flex-wrap:wrap;justify-content:center;gap:12px}
  .plat-tag{background:#0f172a;border:1px solid #1e293b;padding:8px 16px;border-radius:12px;font-size:13px;font-weight:600}
  .section{padding:64px 0}
  .sec-title{text-align:center;margin-bottom:40px}
  .sec-title span{color:#34d399;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:1px}
  .sec-title h2{font-size:32px;font-weight:800;margin-top:6px}
  .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
  .srv-card{background:#0f172a;border:1px solid #1e293b;border-radius:18px;padding:24px;display:flex;flex-direction:column;justify-content:space-between;transition:.2s}
  .srv-card:hover{border-color:#10b981;transform:translateY(-3px)}
  .badge{font-size:11px;font-weight:700;padding:4px 10px;border-radius:99px;background:rgba(16,185,129,.12);color:#34d399}
  .price{font-size:22px;font-weight:800;color:#34d399}
  .faq details{background:#0f172a;border:1px solid #1e293b;border-radius:14px;padding:16px 20px;margin-bottom:12px}
  .faq summary{font-weight:700;cursor:pointer;font-size:15px}
  .faq p{color:#94a3b8;font-size:13px;margin-top:10px}
  footer{border-top:1px solid #1e293b;padding:32px 0;color:#64748b;font-size:13px;text-align:center}
  @media(max-width:860px){.hero-grid,.grid-3{grid-template-columns:1fr}.nav-links{display:none}h1{font-size:32px}}
</style>
</head>

<body>
<header class="nav">
  <div class="container nav-inner">
    <a href="index.php" class="logo">
      <span class="logo-badge">S</span>
      <span>Blindagem<span style="color:#34d399">SMM</span></span>
    </a>
    <nav class="nav-links">
      <a href="#servicos">Serviços</a>
      <a href="#como">Como funciona</a>
      <a href="#faq">Dúvidas</a>
      <a href="consultar.php">Rastrear Pedido</a>
    </nav>
    <div style="display:flex;gap:10px">
      <?php if ($user): ?>
        <a href="painel.php" class="btn btn-green">Acessar Meu Painel &rarr;</a>
      <?php else: ?>
        <a href="auth.php?modo=login" class="btn btn-dark">Entrar</a>
        <a href="auth.php?modo=cadastro" class="btn btn-green">Criar conta grátis</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="pill">🇧🇷 Número 1 em Serviços SMM • PIX a partir de R$ 1</span>
      <h1>Cresça nas redes sociais com entrega automática em segundos</h1>
      <p class="sub">Seguidores reais, curtidas, visualizações e muito mais para Instagram, TikTok e YouTube. Sem precisar dar sua senha.</p>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <a href="auth.php?modo=cadastro" class="btn btn-green">🚀 Criar conta grátis</a>
        <a href="#servicos" class="btn btn-dark">Ver catálogo</a>
      </div>
      <div class="stats">
        <div><strong>+4 Milhões</strong><span>Pedidos entregues</span></div>
        <div><strong>R$ 1,00</strong><span>Recarga mínima PIX</span></div>
        <div><strong>24/7</strong><span>Entrega automática</span></div>
      </div>
    </div>

    <div class="card">
      <h2 style="font-size:20px;font-weight:800">Acesse seu Painel SMM</h2>
      <div class="alert">⚠ Use seu login do <strong>nosso site</strong>, nunca a senha do Instagram.</div>
      <?php if ($user): ?>
        <p style="font-size:14px;margin-bottom:14px">Conectado como <strong style="color:#34d399">@<?= htmlspecialchars($user['usuario']) ?></strong><br>Saldo: <strong>R$ <?= number_format($user['saldo'], 2, ',', '.') ?></strong></p>
        <a href="painel.php" class="btn btn-green" style="width:100%">Abrir Meu Painel &rarr;</a>
      <?php else: ?>
        <form action="auth.php" method="POST">
          <input type="hidden" name="acao" value="entrar">
          <div class="field"><label>Usuário ou E-mail</label><input type="text" name="login" required placeholder="seu_usuario"></div>
          <div class="field"><label>Senha</label><input type="password" name="senha" required placeholder="••••••••"></div>
          <button type="submit" class="btn btn-green" style="width:100%">Fazer Login &rarr;</button>
        </form>
        <p style="text-align:center;font-size:13px;color:#94a3b8;margin-top:16px">Ainda não é membro? <a href="auth.php?modo=cadastro" style="color:#34d399;font-weight:700">Criar Conta Grátis</a></p>
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
    <div class="sec-title">
      <span>Como funciona</span>
      <h2>Em menos de 5 minutos você já tem pedidos rodando</h2>
    </div>
    <div class="grid-3">
      <div class="card"><span class="badge">Passo 1</span><h3 style="margin:12px 0 8px">Crie sua conta</h3><p style="font-size:13px;color:#94a3b8">Cadastro gratuito em 30 segundos, sem cartão e sem pedir senha do Instagram.</p></div>
      <div class="card"><span class="badge">Passo 2</span><h3 style="margin:12px 0 8px">Adicione saldo via PIX</h3><p style="font-size:13px;color:#94a3b8">Recarregue a partir de R$ 1,00 pelo PIX Efí e o saldo cai na hora.</p></div>
      <div class="card"><span class="badge">Passo 3</span><h3 style="margin:12px 0 8px">Faça seu pedido</h3><p style="font-size:13px;color:#94a3b8">Escolha o serviço, cole o link público e acompanhe em tempo real.</p></div>
    </div>
  </div>
</section>

<section id="servicos" class="section" style="padding-top:10px">
  <div class="container">
    <div class="sec-title">
      <span>Catálogo de serviços</span>
      <h2>Serviços mais procurados</h2>
    </div>
    <div class="grid-3">
      <?php foreach ($servicos as $s): ?>
      <div class="srv-card">
        <div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <span class="badge"><?= htmlspecialchars($s['qualidade']) ?></span>
            <span style="font-size:12px;color:#64748b"><?= htmlspecialchars($s['plataforma']) ?></span>
          </div>
          <h3 style="font-size:17px;margin-bottom:8px"><?= htmlspecialchars($s['titulo']) ?></h3>
          <p style="font-size:13px;color:#94a3b8"><?= htmlspecialchars($s['descricao']) ?></p>
        </div>
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid #1e293b;display:flex;justify-content:space-between;align-items:center">
          <div><span style="font-size:11px;color:#64748b;display:block">Por 1.000</span><span class="price">R$ <?= number_format($s['preco_por_mil'], 2, ',', '.') ?></span></div>
          <a href="<?= $user ? 'painel.php' : 'auth.php?modo=cadastro' ?>" class="btn btn-green" style="padding:9px 16px;font-size:13px">Pedir agora</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="faq" class="section faq" style="max-width:820px;margin:0 auto;padding-top:10px">
  <div class="sec-title"><span>Suporte</span><h2>Perguntas Frequentes</h2></div>
  <details><summary>Preciso informar minha senha do Instagram?</summary><p>Nunca. Trabalhamos apenas com o link público do seu perfil ou postagem.</p></details>
  <details><summary>Qual o valor mínimo de recarga via PIX?</summary><p>A partir de R$ 1,00 via PIX Efí com liberação automática na sua carteira.</p></details>
  <details><summary>Posso revender usando a API?</summary><p>Sim! Dentro do painel você gera sua chave de API compatível com qualquer painel SMM.</p></details>
</section>

<footer>
  <div class="container">
    <p>&copy; <?= date('Y') ?> Blindagem SMM — Todos os direitos reservados. Não solicitamos senha das suas redes sociais.</p>
  </div>
</footer>
</body>
</html>
