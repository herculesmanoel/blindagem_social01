<?php
$host = getenv('MYSQLHOST') ?: 'localhost';
$port = getenv('MYSQLPORT') ?: '3306';
$db   = getenv('MYSQLDATABASE') ?: 'railway';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';

define('SMM_API_URL', getenv('SMM_API_URL') ?: 'https://seuprovedor.com');
define('SMM_API_KEY', getenv('SMM_API_KEY') ?: 'SUA_CHAVE_API_SMM');
define('MP_ACCESS_TOKEN', getenv('MP_ACCESS_TOKEN') ?: 'SEU_TOKEN_MERCADO_PAGO');

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Cria as tabelas automaticamente se ainda não existirem
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS servicos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_service_id INT NOT NULL,
            plataforma VARCHAR(50) DEFAULT 'Instagram',
            tipo ENUM('Seguidores', 'Curtidas', 'Visualizacoes') NOT NULL,
            qualidade ENUM('Avancado', 'Premium', 'Exclusivo') NOT NULL,
            titulo VARCHAR(120) NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            preco_por_mil DECIMAL(10, 2) NOT NULL,
            minimo INT DEFAULT 100,
            maximo INT DEFAULT 50000,
            ativo TINYINT(1) DEFAULT 1
        );

        CREATE TABLE IF NOT EXISTS pedidos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo_rastreio VARCHAR(20) UNIQUE NOT NULL,
            servico_id INT NOT NULL,
            link_destino VARCHAR(255) NOT NULL,
            quantidade INT NOT NULL,
            valor_total DECIMAL(10, 2) NOT NULL,
            email_cliente VARCHAR(150) NOT NULL,
            status_pagamento ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pendente',
            status_entrega ENUM('aguardando', 'processando', 'concluido', 'erro') DEFAULT 'aguardando',
            provider_order_id VARCHAR(50) NULL,
            pix_copia_cola TEXT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Insere os serviços iniciais caso a tabela esteja vazia
    if ((int)$pdo->query("SELECT COUNT(*) FROM servicos")->fetchColumn() === 0) {
        $pdo->exec("
            INSERT INTO servicos (provider_service_id, tipo, qualidade, titulo, descricao, preco_por_mil, minimo) VALUES
            (101, 'Visualizacoes', 'Exclusivo', 'Visualizações para Instagram', 'Escolha a quantidade e informe o link do post. Não solicitamos sua senha.', 4.70, 1000),
            (102, 'Seguidores', 'Avancado', 'Seguidores Mundiais Avançados', 'Escolha a quantidade e informe seu perfil ou link. Não solicitamos sua senha.', 3.97, 1000),
            (103, 'Seguidores', 'Exclusivo', 'Seguidores Brasileiros Exclusivos', 'Escolha a quantidade e informe seu perfil ou link. Não solicitamos sua senha.', 6.75, 1000),
            (104, 'Curtidas', 'Exclusivo', 'Curtidas Brasileiras Reais (Exclusivo)', 'Escolha a quantidade e informe o link do post. Não solicitamos sua senha.', 5.99, 1000)
        ");
    }
} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}
