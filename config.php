<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin123');
define('SMM_API_URL', getenv('SMM_API_URL') ?: 'https://seuprovedor.com');
define('SMM_API_KEY', getenv('SMM_API_KEY') ?: 'SUA_CHAVE_API_SMM');

try {
    // Usa o Volume persistente /data do Railway se estiver montado, evitando perder contas/saldos no deploy
    $dbDir = (is_dir('/data') && is_writable('/data')) ? '/data' : __DIR__;
    $pdo = new PDO("sqlite:" . $dbDir . "/banco.sqlite", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            usuario TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            senha_hash TEXT NOT NULL,
            saldo REAL DEFAULT 0.00,
            api_key TEXT UNIQUE NOT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS servicos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider_service_id INTEGER NOT NULL,
            plataforma TEXT DEFAULT 'Instagram',
            tipo TEXT NOT NULL,
            qualidade TEXT NOT NULL,
            titulo TEXT NOT NULL,
            descricao TEXT NOT NULL,
            preco_por_mil REAL NOT NULL,
            minimo INTEGER DEFAULT 100,
            maximo INTEGER DEFAULT 50000,
            ativo INTEGER DEFAULT 1
        );

        CREATE TABLE IF NOT EXISTS pedidos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NULL,
            codigo_rastreio TEXT UNIQUE NOT NULL,
            servico_id INTEGER NOT NULL,
            link_destino TEXT NOT NULL,
            quantidade INTEGER NOT NULL,
            valor_total REAL NOT NULL,
            email_cliente TEXT DEFAULT '',
            status_pagamento TEXT DEFAULT 'pago',
            status_entrega TEXT DEFAULT 'processando',
            provider_order_id TEXT NULL,
            pix_copia_cola TEXT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS transacoes_pix (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL,
            txid TEXT UNIQUE NOT NULL,
            valor REAL NOT NULL,
            pix_copia_cola TEXT NOT NULL,
            status TEXT DEFAULT 'pendente',
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    if ((int)$pdo->query("SELECT COUNT(*) FROM servicos")->fetchColumn() === 0) {
        $pdo->exec("
            INSERT INTO servicos (provider_service_id, plataforma, tipo, qualidade, titulo, descricao, preco_por_mil, minimo) VALUES
            (101, 'Instagram', 'Seguidores', 'Exclusivo', 'Seguidores Instagram Brasileiros', 'Perfis brasileiros de alta qualidade. Entrega automática sem senha.', 6.90, 100),
            (102, 'Instagram', 'Curtidas', 'Premium', 'Curtidas Instagram Reais', 'Engajamento rápido e seguro para publicações e Reels.', 2.90, 100),
            (103, 'Instagram', 'Visualizacoes', 'Avancado', 'Views Stories & Reels Instagram', 'Visualizações imediatas em segundos.', 1.49, 500),
            (201, 'TikTok', 'Seguidores', 'Premium', 'Seguidores TikTok Mundiais', 'Crescimento rápido e consistente para seu perfil.', 7.49, 100),
            (202, 'TikTok', 'Visualizacoes', 'Avancado', 'Visualizações TikTok Rápidas', 'Aumente o alcance dos seus vídeos no TikTok.', 0.99, 1000),
            (301, 'YouTube', 'Visualizacoes', 'Exclusivo', 'Views YouTube Alta Retenção', 'Visualizações para ajudar a ranquear seu vídeo.', 8.90, 500)
        ");
    }
} catch (PDOException $e) {
    die("Erro no banco: " . $e->getMessage());
}

function usuarioAtual(PDO $pdo): ?array {
    if (empty($_SESSION['usuario_id'])) return null;
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    return $stmt->fetch() ?: null;
}
