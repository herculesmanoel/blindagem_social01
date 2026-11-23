<?php
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin123');
define('SMM_API_URL', getenv('SMM_API_URL') ?: 'https://seuprovedor.com');
define('SMM_API_KEY', getenv('SMM_API_KEY') ?: 'SUA_CHAVE_API_SMM');

try {
    $pdo = new PDO("sqlite:" . __DIR__ . "/banco.sqlite", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $pdo->exec("
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
            codigo_rastreio TEXT UNIQUE NOT NULL,
            servico_id INTEGER NOT NULL,
            link_destino TEXT NOT NULL,
            quantidade INTEGER NOT NULL,
            valor_total REAL NOT NULL,
            email_cliente TEXT NOT NULL,
            status_pagamento TEXT DEFAULT 'pendente',
            status_entrega TEXT DEFAULT 'aguardando',
            provider_order_id TEXT NULL,
            pix_copia_cola TEXT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    if ((int)$pdo->query("SELECT COUNT(*) FROM servicos")->fetchColumn() === 0) {
        $pdo->exec("
            INSERT INTO servicos (provider_service_id, tipo, qualidade, titulo, descricao, preco_por_mil, minimo) VALUES
            (101, 'Visualizacoes', 'Exclusivo', 'Visualizações para Instagram', 'Escolha a quantidade e informe o link do post. Não solicitamos sua senha.', 4.70, 1000),
            (102, 'Seguidores', 'Avancado', 'Seguidores Mundiais Avançados', 'Escolha a quantidade e informe seu perfil ou link. Não solicitamos sua senha.', 3.97, 1000),
            (103, 'Seguidores', 'Exclusivo', 'Seguidores Brasileiros Exclusivos', 'Escolha a quantidade e informe seu perfil ou link. Não solicitamos sua senha.', 6.75, 1000),
            (104, 'Curtidas', 'Exclusivo', 'Curtidas Brasileiras Reais (Exclusivo)', 'Escolha a quantidade e informe o link do post.', 5.99, 1000),
            (105, 'Curtidas', 'Premium', 'Curtidas Brasileiras Premium', 'Escolha a quantidade e informe o link do post.', 4.57, 1000),
            (106, 'Curtidas', 'Avancado', 'Curtidas Mundiais Avançado', 'Escolha a quantidade e informe o link do post.', 3.00, 1000),
            (107, 'Seguidores', 'Premium', 'Seguidores Brasileiros Premium', 'Deixe o perfil público. Insira o link do perfil.', 5.75, 1000),
            (108, 'Visualizacoes', 'Avancado', 'Visualizações em vídeo', 'Escolha a quantidade e o link do post. Não solicitamos sua senha.', 3.55, 1000),
            (109, 'Visualizacoes', 'Premium', 'Visualizações em vídeo BR', 'Escolha sua quantidade e dê o próximo passo para o seu perfil.', 3.75, 1000)
        ");
    }
} catch (PDOException $e) {
    die("Erro no banco: " . $e->getMessage());
}
