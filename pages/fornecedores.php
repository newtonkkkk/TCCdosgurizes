<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();
require_can(['dono', 'almoxarife']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar') {
        $id = (int)($_POST['id'] ?? 0);
        $dados = [
            'nome'           => trim($_POST['nome'] ?? ''),
            'nif'            => trim($_POST['nif'] ?? ''),
            'telefone'       => trim($_POST['telefone'] ?? ''),
            'email'          => trim($_POST['email'] ?? ''),
            'endereco'       => trim($_POST['endereco'] ?? ''),
            'prazo_entrega'  => (int)($_POST['prazo_entrega'] ?? 7),
            'observacao'     => trim($_POST['observacao'] ?? ''),
            'ativo'          => isset($_POST['ativo']) ? 1 : 0,
        ];
        if ($dados['nome'] === '') {
            flash('erro', 'Informe o nome do fornecedor.');
        } else {
            if ($id > 0) {
                $dados['id'] = $id;
                $pdo->prepare("UPDATE fornecedores SET nome=:nome,nif=:nif,telefone=:telefone,email=:email,endereco=:endereco,prazo_entrega=:prazo_entrega,observacao=:observacao,ativo=:ativo WHERE id=:id")->execute($dados);
                flash('ok', 'Fornecedor atualizado.');
            } else {
                $pdo->prepare("INSERT INTO fornecedores (nome,nif,telefone,email,endereco,prazo_entrega,observacao,ativo) VALUES (:nome,:nif,:telefone,:email,:endereco,:prazo_entrega,:observacao,:ativo)")->execute($dados);
                flash('ok', 'Fornecedor cadastrado.');
            }
        }
        redirect('/pages/fornecedores.php');
    }
    if ($acao === 'inativar') {
        $pdo->prepare("UPDATE fornecedores SET ativo=0 WHERE id=?")->execute([(int)$_POST['id']]);
        flash('ok', 'Fornecedor inativado.');
        redirect('/pages/fornecedores.php');
    }
}

$edit = null;
if (!empty($_GET['editar'])) {
    $st = $pdo->prepare("SELECT * FROM fornecedores WHERE id=?");
    $st->execute([(int)$_GET['editar']]);
    $edit = $st->fetch();
}
$lista = $pdo->query("SELECT * FROM fornecedores ORDER BY ativo DESC, nome")->fetchAll();

$pageTitle = 'Fornecedores';
$pageHint = 'Base comercial para entrada de materiais (RF04).';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="split">
<section class="card">
    <h2><?= $edit ? 'Editar fornecedor' : 'Novo fornecedor' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <label>Nome</label>
        <input name="nome" required value="<?= e($edit['nome'] ?? '') ?>">
        <div class="form-grid" style="margin-top:12px">
            <div><label>CNPJ</label><input name="nif" value="<?= e($edit['nif'] ?? '') ?>"></div>
            <div><label>Telefone</label><input name="telefone" value="<?= e($edit['telefone'] ?? '') ?>"></div>
            <div class="full"><label>E-mail</label><input name="email" type="email" value="<?= e($edit['email'] ?? '') ?>"></div>
            <div class="full"><label>Endereço</label><input name="endereco" value="<?= e($edit['endereco'] ?? '') ?>"></div>
            <div><label>Prazo (dias)</label><input type="number" name="prazo_entrega" value="<?= e((string)($edit['prazo_entrega'] ?? 7)) ?>"></div>
            <div class="full"><label>Observação</label><textarea name="observacao"><?= e($edit['observacao'] ?? '') ?></textarea></div>
            <div class="full"><label><input type="checkbox" name="ativo" <?= empty($edit)||!empty($edit['ativo'])?'checked':'' ?>> Ativo</label></div>
        </div>
        <div class="row-actions">
            <button class="btn" type="submit">Salvar</button>
            <?php if ($edit): ?><a class="btn btn-ghost" href="fornecedores.php">Cancelar</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="card">
    <h2>Lista</h2>
    <input type="search" placeholder="Filtrar..." data-filter-table="#tab-forn" style="margin-bottom:12px">
    <div class="table-wrap">
        <table id="tab-forn">
            <thead><tr><th>Fornecedor</th><th>Contato</th><th>Prazo</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lista as $f): ?>
                <tr>
                    <td>
                        <?= e($f['nome']) ?>
                        <?php if (!$f['ativo']): ?><span class="badge badge-off">inativo</span><?php endif; ?>
                        <br><span class="muted mono"><?= e($f['nif']) ?></span>
                    </td>
                    <td><?= e($f['telefone']) ?><br><span class="muted"><?= e($f['email']) ?></span></td>
                    <td><?= (int)$f['prazo_entrega'] ?> dias</td>
                    <td>
                        <a class="btn-link" href="?editar=<?= (int)$f['id'] ?>">editar</a>
                        <?php if ($f['ativo']): ?>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="inativar">
                            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                            <button class="btn-link" type="submit" data-confirm="Inativar fornecedor?">inativar</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
