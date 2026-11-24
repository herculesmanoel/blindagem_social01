<?php
session_start();

// ==========================================
// 1. COLOQUE OS DADOS DO SEU BANCO AQUI:
// ==========================================
$db_host = 'localhost';
$db_nome = 'nome_do_seu_banco';
$db_user = 'usuario_do_banco';
$db_pass = 'senha_do_banco';
// ==========================================

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_nome;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}

// Cria automaticamente as tabelas necessárias se não existirem
$pdo->exec("CREATE TABLE IF NOT EXISTS `config_interface` (
    `chave` VARCHAR(100) NOT NULL PRIMARY KEY,
    `valor` LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS `servicos_painel` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plataforma` VARCHAR(50) NOT NULL,
    `nome` VARCHAR(150) NOT NULL,
    `preco_mil` DECIMAL(10,2) NOT NULL,
    `minimo` INT NOT NULL DEFAULT 100,
    `maximo` INT NOT NULL DEFAULT 10000,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Insere configurações padrão na primeira execução
$pdo->exec("INSERT IGNORE INTO `config_interface` (`chave`, `valor`) VALUES
    ('site_nome', 'PAINEL VIP SMM'),
    ('site_subtitulo', 'Impulsione suas redes sociais em segundos'),
    ('site_logo_url', ''),
    ('cor_primaria', '#D4AF37'),
    ('cor_fundo', '#0B0F19'),
    ('cor_card', '#161B26'),
    ('cor_texto', '#F9FAFB'),
    ('fonte_principal', 'Inter'),
    ('borda_arredondamento', '12px'),
    ('live_bar_ativa', '1'),
    ('live_bar_itens', '[{\"user\":\"@lucas.mendes\",\"acao\":\"acabou de pedir\",\"item\":\"1.000 Seguidores Instagram BR\"},{\"user\":\"@camila_oliveira\",\"acao\":\"acabou de pedir\",\"item\":\"500 Curtidas Reais\"},{\"user\":\"@studiobella_sp\",\"acao\":\"adicionou\",\"item\":\"R$ 50,00 via PIX\"},{\"user\":\"@rafael.trader\",\"acao\":\"acabou de pedir\",\"item\":\"2.000 Seguidores TikTok\"}]'),
    ('pix_minimo', '5.00'),
    ('chave_pix_manual', 'suachavepix@email.com'),
    ('whatsapp_suporte', '5581999999999'),
    ('efeito_emojis_ativo', '1'),
    ('aviso_topo_texto', '🔥 PROMOÇÃO ATIVA: Bônus em todas as recargas via PIX hoje!'),
    ('aviso_topo_ativo', '1'),
    ('texto_botao_comprar', '🚀 FINALIZAR PEDIDO AGORA'),
    ('css_customizado', '')
");

// Insere 2 serviços de exemplo se a tabela estiver vazia
$qtdServ = $pdo->query("SELECT COUNT(*) FROM servicos_painel")->fetchColumn();
if ($qtdServ == 0) {
    $pdo->exec("INSERT INTO `servicos_painel` (plataforma, nome, preco_mil, minimo, maximo) VALUES
        ('Instagram', 'Seguidores Brasileiros Reais (Reposição 30 dias)', 19.90, 100, 50000),
        ('TikTok', 'Seguidores Mundiais Alta Velocidade', 14.50, 100, 100000)
    ");
}

// Carrega toda a interface do banco para a variável $UI
$UI = $pdo->query("SELECT chave, valor FROM config_interface")->fetchAll(PDO::FETCH_KEY_PAIR);

function ui($chave, $padrao = '') {
    global $UI;
    return (isset($UI[$chave]) && $UI[$chave] !== '') ? $UI[$chave] : $padrao;
}
?>
