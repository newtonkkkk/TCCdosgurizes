<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(bool $fatal = true): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $opt = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
    } catch (PDOException $e) {
        if (!$fatal) {
            throw $e;
        }
        http_response_code(500);
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Erro de conexão</title>';
        echo '<style>body{font-family:sans-serif;background:#f4efe6;color:#1c1710;padding:40px}code{background:#fff;padding:2px 6px;border-radius:4px}</style></head><body>';
        echo '<h1>Não foi possível conectar ao MySQL</h1>';
        echo '<p>Ligue o XAMPP e abra <code>install.php</code> para criar o banco.</p>';
        echo '<p><a href="/almoxarifado/install.php">Ir para o instalador</a></p>';
        echo '<p style="color:#b42318">' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</body></html>';
        exit;
    }

    return $pdo;
}
?>
