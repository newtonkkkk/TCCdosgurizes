<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        flash('erro', 'Faça login para continuar.');
        redirect('/index.php');
    }
}

function attempt_login(string $identificador, string $senha): bool
{
    $pdo = db();
    $st = $pdo->prepare(
        "SELECT * FROM usuarios WHERE (email = :id OR nif = :id2) AND ativo = 1 LIMIT 1"
    );
    $st->execute(['id' => $identificador, 'id2' => $identificador]);
    $user = $st->fetch();

    if (!$user || !password_verify($senha, $user['senha'])) {
        return false;
    }

    $_SESSION['user'] = [
        'id'      => (int)$user['id'],
        'nome'    => $user['nome'],
        'email'   => $user['email'],
        'nif'     => $user['nif'],
        'perfil'  => $user['perfil'],
        'telefone'=> $user['telefone'],
    ];
    return true;
}

function attempt_login_id(int $id, string $senha): bool
{
    $pdo = db();
    $st = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND ativo = 1 LIMIT 1");
    $st->execute([$id]);
    $user = $st->fetch();
    if (!$user || !password_verify($senha, $user['senha'])) {
        return false;
    }
    $_SESSION['user'] = [
        'id'       => (int)$user['id'],
        'nome'     => $user['nome'],
        'email'    => $user['email'],
        'nif'      => $user['nif'],
        'perfil'   => $user['perfil'],
        'telefone' => $user['telefone'],
    ];
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
?>
