<?php
require_once 'config.php';
$user = usuarioAtual($pdo);
$servicos = $pdo->query("SELECT * FROM servicos WHERE ativo = 1 ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Blindagem Social — Painel SMM VIP | Seguidores, Curtidas e Views</title>
<link rel="stylesheet" href="estilo.css">
</head>
<body>

<div class="live-bar">
  <div class="live-track">
    <span class="live-item"><span class="dot-live"></span> <strong>@lucas.mendes</strong> acabou de comprar <strong class="gold-solid">1.000 Seguidores Instagram BR</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@camila_oliveira</strong> acabou de pedir <strong class="gold-solid">500 Curtidas Reais</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@pedrohenrique.oficial</strong> acabou de pedir <strong class="gold-solid">5.000 Visualizações Reels</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@studiobella_sp</strong> adicionou <strong class="gold-solid">R$ 50,00 via PIX</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@rafael.trader</strong> acabou de pedir <strong class="gold-solid">2.000 Seguidores TikTok</strong></span>
  </div>
</div>

<header class="nav">
  <div class="container nav-inner">
    <a href="index.php" class="logo">
      <span class="logo-badge">B</span>
      <span>Blindagem <span class="gold">Social</span></span>
    </a>
    <nav class="nav-links">
      <a href="#simulador">Simulador</a>
      <a href="#servicos">Catálogo VIP</a>
      <a href="#como">Como funciona</a>
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
      <h1>Eleve seu perfil ao nível <span class="gold">autoridade máxima</span> em segundos</h1>
      <p class="sub">Seguidores brasileiros, curtidas, visualizações e engajamento automático para Instagram, TikTok e YouTube. Sem pedir sua senha.</p>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <a href="auth.php?modo=cadastro" class="btn btn-gold">Começar agora — Grátis &rarr;</a>
        <a href="#simulador" class="btn btn-dark">Simular valores</a>
      </div>
      <div class="stats">
        <div><strong>+4,2 Milhões</strong><span>Pedidos entregues</span></div>
        <div><strong>R$ 1,00</strong><span>Recarga mínima PIX</span></div>
        <div><strong>24/7</strong><span>Envio automático</span></div>
      </div>
    </div>

    <div class="card" style="margin-bottom:0">
      <h2 style="font-size:21px;font-weight:900">Acesso VIP — <span class="gold">Blindagem Social</span></h2>
      <div class="alert">🔒 Segurança total: use apenas seu login do <strong>nosso site</strong>.</div>
      <?php if ($user): ?>
        <p style="font-size:14px;margin-bottom:16px">Conectado como <strong class="gold-solid">@<?= htmlspecialchars($user['usuario']) ?></strong><br>Saldo disponível: <strong class="gold-solid">R$ <?= number_format($user['saldo'], 2, ',', '.') ?></strong></p>
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

<!-- Simulador Interativo de Preços -->
<section id="simulador" class="container" style="margin-bottom:40px">
  <div class="card" style="border-color:rgba(251,191,36,.4);background:linear-gradient(135deg,#14130e,#0c0c0f)">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:18px">
      <div>
        <span class="badge">Calculadora Instantânea</span>
        <h2 style="font-size:22px;font-weight:900;margin-top:6px">Simule quanto custa impulsionar seu perfil agora</h2>
      </div>
      <span style="font-size:12px;color:#34d399;font-weight:700">✓ Entrega inicia em segundos</span>
    </div>

    <div class="grid-3" style="align-items:end">
      <div class="field" style="margin-bottom:0">
        <label>Escolha o Pacote</label>
        <select id="sim-srv" onchange="calcularSimulador()">
          <?php foreach ($servicos as $s): ?>
            <option value="<?= $s['preco_por_mil'] ?>" data-min="<?= $s['minimo'] ?>">
              [<?= htmlspecialchars($s['plataforma']) ?>] <?= htmlspecialchars($s['titulo']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-bottom:0">
        <label>Quantidade Desejada</label>
        <input type="number" id="sim-qtd" value="1000" min="100" step="100" oninput="calcularSimulador()">
      </div>
      <div style="display:flex;gap:12px;align-items:center;justify-content:space-between;background:#070709;border:1px solid rgba(251,191,36,.35);padding:10px 16px;border-radius:14px">
        <div>
          <span style="font-size:11px;color:#a1a1aa;display:block">Investimento PIX</span>
          <strong id="sim-total" style="font-size:22px;color:#fbbf24">R$ 0,00</strong>
        </div>
        <a href="<?= $user ? 'painel.php' : 'auth.php?modo=cadastro' ?>" class="btn btn-gold" style="padding:10px 16px">Pedir &rarr;</a>
      </div>
    </div>
  </div>
</section>

<!-- Catálogo VIP com Filtro por Plataforma -->
<section id="servicos" class="container" style="padding-bottom:60px">
  <div style="text-align:center;margin-bottom:28px">
    <span style="color:#fbbf24;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:1.4px">Catálogo Exclusivo</span>
    <h2 style="font-size:32px;font-weight:900;margin-top:6px">Pacotes mais vendidos hoje</h2>
  </div>

  <div style="display:flex;justify-content:center;flex-wrap:wrap;gap:8px;margin-bottom:28px">
    <button type="button" class="btn btn-gold plat-filter" onclick="filtrarVitrine('Todas', this)">Todas</button>
    <button type="button" class="btn btn-dark plat-filter" onclick="filtrarVitrine('Instagram', this)">📸 Instagram</button>
    <button type="button" class="btn btn-dark plat-filter" onclick="filtrarVitrine('TikTok', this)">🎵 TikTok</button>
    <button type="button" class="btn btn-dark plat-filter" onclick="filtrarVitrine('YouTube', this)">▶️ YouTube</button>
    <button type="button" class="btn btn-dark plat-filter" onclick="filtrarVitrine('Kwai', this)">🎵 Kwai</button>
  </div>

  <div class="grid-3" id="vitrine-cards">
    <?php foreach (array_slice($servicos, 0, 9) as $s): ?>
    <div class="srv-card" data-plat="<?= htmlspecialchars($s['plataforma']) ?>">
      <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <span class="badge"><?= htmlspecialchars($s['qualidade']) ?></span>
          <span style="font-size:12px;color:#71717a;font-weight:700"><?= htmlspecialchars($s['plataforma']) ?></span>
        </div>
        <h3 style="font-size:17px;margin-bottom:8px;font-weight:800"><?= htmlspecialchars($s['titulo']) ?></h3>
        <p style="font-size:13px;color:#a1a1aa"><?= htmlspecialchars($s['descricao']) ?></p>
      </div>
      <div style="margin-top:20px;padding-top:16px;border-top:1px solid #27272a;display:flex;justify-content:space-between;align-items:center">
        <div><span style="font-size:11px;color:#71717a;display:block">Por 1.000</span><span class="price">R$ <?= number_format($s['preco_por_mil'], 2, ',', '.') ?></span></div>
        <a href="<?= $user ? 'painel.php' : 'auth.php?modo=cadastro' ?>" class="btn btn-gold" style="padding:10px 18px;font-size:13px">Comprar &rarr;</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Como Funciona -->
<section id="como" class="container" style="padding-bottom:60px">
  <div class="grid-3">
    <div class="card" style="margin-bottom:0"><span class="badge">01 • Cadastro</span><h3 style="margin:14px 0 8px;font-size:18px">Crie sua conta grátis</h3><p style="font-size:13px;color:#a1a1aa">Menos de 30 segundos. Sem cartão de crédito e sem pedir senha das suas redes.</p></div>
    <div class="card" style="margin-bottom:0"><span class="badge">02 • Recarga PIX</span><h3 style="margin:14px 0 8px;font-size:18px">Adicione saldo na hora</h3><p style="font-size:13px;color:#a1a1aa">Recarregue a partir de R$ 1,00 via PIX Efí com liberação automática 24h.</p></div>
    <div class="card" style="margin-bottom:0"><span class="badge">03 • Resultado</span><h3 style="margin:14px 0 8px;font-size:18px">Envie seu pedido</h3><p style="font-size:13px;color:#a1a1aa">Informe o link público e acompanhe cada etapa em tempo real no painel.</p></div>
  </div>
</section>

<!-- FAQ -->
<section id="faq" class="container faq" style="max-width:820px;padding-bottom:60px">
  <h2 style="font-size:28px;font-weight:900;text-align:center;margin-bottom:24px">Perguntas Frequentes</h2>
  <details><summary>Preciso informar minha senha do Instagram?</summary><p>Nunca. Trabalhamos exclusivamente com o link público do seu perfil ou publicação.</p></details>
  <details><summary>Qual o valor mínimo para adicionar saldo via PIX?</summary><p>A partir de R$ 1,00 via PIX Efí com liberação automática na sua carteira.</p></details>
  <details><summary>Em quanto tempo começa a entrega?</summary><p>O envio começa automaticamente em segundos após você confirmar o pedido.</p></details>
</section>

<footer>
  <div class="container">
    <p>&copy; <?= date('Y') ?> <strong class="gold-solid">Blindagem Social</strong> — Todos os direitos reservados. Não solicitamos senha das suas redes sociais.</p>
  </div>
</footer>

<div id="live-toast" class="toast">
  <div class="toast-ico">👑</div>
  <div style="font-size:12px;line-height:1.4">
    <strong id="toast-nome" style="color:#fff;display:block">Lucas M. — São Paulo, SP</strong>
    <span id="toast-acao" style="color:#fbbf24;font-weight:700">Comprou 1.000 Seguidores Instagram</span>
    <span id="toast-tempo" style="color:#71717a;display:block;font-size:11px">há 14 segundos</span>
  </div>
</div>

<script>
function calcularSimulador() {
  const sel = document.getElementById('sim-srv');
  if (!sel || !sel.options.length) return;
  const preco = parseFloat(sel.value) || 0;
  const qtd = parseInt(document.getElementById('sim-qtd').value) || 0;
  const total = (preco / 1000) * qtd;
  document.getElementById('sim-total').innerText = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
function filtrarVitrine(plat, btn) {
  document.querySelectorAll('.plat-filter').forEach(b => { b.classList.remove('btn-gold'); b.classList.add('btn-dark'); });
  btn.classList.remove('btn-dark'); btn.classList.add('btn-gold');
  document.querySelectorAll('#vitrine-cards .srv-card').forEach(c => {
    c.style.display = (plat === 'Todas' || c.dataset.plat === plat) ? 'flex' : 'none';
  });
}
const compras = [
  { nome: "Lucas M. — São Paulo, SP", acao: "Comprou 1.000 Seguidores Instagram BR", tempo: "há 12 segundos" },
  { nome: "Camila S. — Recife, PE", acao: "Comprou 500 Curtidas Brasileiras", tempo: "há 27 segundos" },
  { nome: "Gabriel R. — Curitiba, PR", acao: "Comprou 5.000 Visualizações Reels", tempo: "há 41 segundos" },
  { nome: "Fernanda L. — Belo Horizonte, MG", acao: "Comprou 2.000 Seguidores Instagram", tempo: "há 1 minuto" },
  { nome: "Matheus A. — Rio de Janeiro, RJ", acao: "Comprou 1.500 Seguidores TikTok", tempo: "há 1 minuto" }
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
calcularSimulador();
setTimeout(mostrarToast, 2500);
setInterval(mostrarToast, 9000);
</script>
</body>
</html>
