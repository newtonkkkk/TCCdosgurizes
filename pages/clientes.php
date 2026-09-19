<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();
require_can(['dono', 'mecanico']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('erro', 'Sessão expirada. Tente salvar de novo.');
        redirect('/pages/clientes.php');
    }

    $acao = $_POST['acao'] ?? 'salvar';
    $id = (int)($_POST['id'] ?? 0);

    if ($acao === 'excluir') {
        if ($id > 0) {
            $pdo->prepare("DELETE FROM clientes WHERE id=?")->execute([$id]);
            flash('ok', 'Cliente excluído.');
        }
        redirect('/pages/clientes.php');
    }

    $dados = [
        'nome'     => trim($_POST['nome'] ?? ''),
        'nif'      => trim($_POST['nif'] ?? '') ?: null,
        'telefone' => trim($_POST['telefone'] ?? '') ?: null,
        'email'    => trim($_POST['email'] ?? '') ?: null,
        'endereco' => trim($_POST['endereco'] ?? '') ?: null,
    ];
    if ($dados['nome'] === '') {
        flash('erro', 'Informe o nome do cliente.');
        redirect('/pages/clientes.php');
    }

    try {
        if ($id > 0) {
            $dados['id'] = $id;
            $pdo->prepare("UPDATE clientes SET nome=:nome,nif=:nif,telefone=:telefone,email=:email,endereco=:endereco WHERE id=:id")->execute($dados);
            flash('ok', 'Cliente atualizado.');
        } else {
            $pdo->prepare("INSERT INTO clientes (nome,nif,telefone,email,endereco) VALUES (:nome,:nif,:telefone,:email,:endereco)")->execute($dados);
            flash('ok', 'Cliente cadastrado.');
        }
    } catch (Throwable $e) {
        flash('erro', 'Não foi possível salvar o cliente.');
    }
    redirect('/pages/clientes.php');
}

$edit = null;
if (!empty($_GET['editar'])) {
    $st = $pdo->prepare("SELECT * FROM clientes WHERE id=?");
    $st->execute([(int)$_GET['editar']]);
    $edit = $st->fetch();
}
$lista = $pdo->query("SELECT * FROM clientes ORDER BY nome")->fetchAll();

$pageTitle = 'Clientes';
$pageHint = 'Cadastre clientes para usar na agenda e nas ordens de serviço.';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="split">
<section class="card">
    <h2><?= $edit ? 'Editar cliente' : 'Novo cliente' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <label>Nome*</label>
        <input name="nome" required value="<?= e($edit['nome'] ?? '') ?>" placeholder="Nome completo">
        <div class="form-grid" style="margin-top:12px">
            <div><label>CPF</label><input name="nif" value="<?= e($edit['nif'] ?? '') ?>"></div>
            <div><label>Telefone</label><input name="telefone" value="<?= e($edit['telefone'] ?? '') ?>" placeholder="(45) 99999-0000"></div>
            <div class="full"><label>E-mail</label><input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></div>
            <div class="full"><label>Endereço</label><input name="endereco" value="<?= e($edit['endereco'] ?? '') ?>"></div>
        </div>
        <div class="row-actions">
            <button class="btn" type="submit"><?= $edit ? 'Salvar alterações' : 'Adicionar cliente' ?></button>
            <?php if ($edit): ?><a class="btn btn-ghost" href="clientes.php">Cancelar</a><?php endif; ?>
        </div>
    </form>
</section>
<section class="card">
    <h2>Lista</h2>
    <input type="search" placeholder="Filtrar por nome, telefone..." data-filter-table="#tab-cli" style="margin-bottom:12px">
    <div class="table-wrap">
        <table id="tab-cli">
            <thead><tr><th>Nome</th><th>Contato</th><th></th></tr></thead>
            <tbody>
            <?php if (!$lista): ?>
                <tr><td colspan="3" class="empty">Nenhum cliente ainda. Use o formulário ao lado.</td></tr>
            <?php endif; ?>
            <?php foreach ($lista as $c): ?>
                <tr>
                    <td><?= e($c['nome']) ?><br><span class="muted mono"><?= e($c['nif']) ?></span></td>
                    <td><?= e($c['telefone']) ?><br><span class="muted"><?= e($c['email']) ?></span></td>
                    <td>
                        <a class="btn-link" href="?editar=<?= (int)$c['id'] ?>">editar</a>
                        <form method="post" style="display:inline;margin-left:8px">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn-link" type="submit" data-confirm="Excluir <?= e($c['nome']) ?>?" style="color:#ff6b6b">excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
