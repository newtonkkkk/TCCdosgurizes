<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();
require_can(['dono']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $acao = $_POST['acao'] ?? 'salvar';

    if ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)current_user()['id']) {
            flash('erro', 'Você não pode excluir o perfil que está usando.');
            redirect('/pages/usuarios.php');
        }
        $st = $pdo->prepare("SELECT perfil, nome FROM usuarios WHERE id=?");
        $st->execute([$id]);
        $alvo = $st->fetch();
        if (!$alvo) {
            flash('erro', 'Perfil não encontrado.');
            redirect('/pages/usuarios.php');
        }
        if ($alvo['perfil'] === 'dono') {
            $qtdDonos = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil='dono' AND ativo=1")->fetchColumn();
            if ($qtdDonos <= 1) {
                flash('erro', 'Não é possível excluir o último dono.');
                redirect('/pages/usuarios.php');
            }
        }
        try {
            $pdo->beginTransaction();
            $fid = $pdo->prepare("SELECT id FROM funcionarios WHERE usuario_id=?");
            $fid->execute([$id]);
            $funcionarioId = $fid->fetchColumn();
            if ($funcionarioId) {
                $pdo->prepare("DELETE FROM pagamentos WHERE funcionario_id=?")->execute([$funcionarioId]);
                $pdo->prepare("DELETE FROM funcionarios WHERE id=?")->execute([$funcionarioId]);
            }
            $pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]);
            $pdo->commit();
            flash('ok', 'Perfil de ' . $alvo['nome'] . ' excluído.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('erro', 'Não foi possível excluir este perfil.');
        }
        redirect('/pages/usuarios.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nif = trim($_POST['nif'] ?? '') ?: null;
    $perfil = $_POST['perfil'] ?? 'mecanico';
    $telefone = trim($_POST['telefone'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $senha = (string)($_POST['senha'] ?? '');
    $cargo = trim($_POST['cargo'] ?? 'Colaborador');
    $valorHora = (float)str_replace(',', '.', (string)($_POST['valor_hora'] ?? 0));
    $comissao = (float)str_replace(',', '.', (string)($_POST['comissao_pct'] ?? 0));

    if (!in_array($perfil, perfis_validos(), true)) {
        $perfil = 'mecanico';
    }
    $email = mb_strtolower($email);
    if ($nome === '' || $email === '') {
        flash('erro', 'Nome e e-mail são obrigatórios.');
        redirect($id > 0 ? '/pages/usuarios.php?editar=' . $id : '/pages/usuarios.php');
    }

    // Unicidade de e-mail/CPF ignorando o próprio registo (evita falso positivo na edição)
    $st = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id <> ? LIMIT 1");
    $st->execute([$email, $id]);
    if ($st->fetch()) {
        flash('erro', 'Este e-mail já está em uso por outro utilizador.');
        redirect($id > 0 ? '/pages/usuarios.php?editar=' . $id : '/pages/usuarios.php');
    }
    if ($nif !== null && $nif !== '') {
        $st = $pdo->prepare("SELECT id FROM usuarios WHERE nif = ? AND id <> ? LIMIT 1");
        $st->execute([$nif, $id]);
        if ($st->fetch()) {
            flash('erro', 'Este CPF já está em uso por outro utilizador.');
            redirect($id > 0 ? '/pages/usuarios.php?editar=' . $id : '/pages/usuarios.php');
        }
    }

    try {
        if ($id > 0) {
            if ($senha !== '') {
                $pdo->prepare("UPDATE usuarios SET nome=?,email=?,nif=?,perfil=?,telefone=?,ativo=?,senha=? WHERE id=?")
                    ->execute([$nome,$email,$nif,$perfil,$telefone,$ativo,password_hash($senha, PASSWORD_DEFAULT),$id]);
            } else {
                $pdo->prepare("UPDATE usuarios SET nome=?,email=?,nif=?,perfil=?,telefone=?,ativo=? WHERE id=?")
                    ->execute([$nome,$email,$nif,$perfil,$telefone,$ativo,$id]);
            }
            $ex = $pdo->prepare("SELECT id FROM funcionarios WHERE usuario_id=?");
            $ex->execute([$id]);
            if ($ex->fetch()) {
                $pdo->prepare("UPDATE funcionarios SET cargo=?, valor_hora=?, comissao_pct=?, ativo=? WHERE usuario_id=?")
                    ->execute([$cargo,$valorHora,$comissao,$ativo,$id]);
            } else {
                $pdo->prepare("INSERT INTO funcionarios (usuario_id,cargo,valor_hora,comissao_pct,ativo) VALUES (?,?,?,?,?)")
                    ->execute([$id,$cargo,$valorHora,$comissao,$ativo]);
            }
            flash('ok', 'Utilizador atualizado.');
            redirect('/pages/usuarios.php?editar=' . $id);
        } else {
            if ($senha === '') $senha = 'root';
            $pdo->prepare("INSERT INTO usuarios (nome,email,nif,senha,perfil,telefone,ativo) VALUES (?,?,?,?,?,?,?)")
                ->execute([$nome,$email,$nif,password_hash($senha, PASSWORD_DEFAULT),$perfil,$telefone,$ativo]);
            $uid = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO funcionarios (usuario_id,cargo,valor_hora,comissao_pct,ativo) VALUES (?,?,?,?,?)")
                ->execute([$uid,$cargo,$valorHora,$comissao,$ativo]);
            flash('ok', 'Utilizador criado. Senha inicial: ' . ($senha === 'root' ? 'root' : '(definida)'));
        }
    } catch (PDOException $e) {
        // Mensagem realista: duplicate key vs outros erros (ex.: ENUM antigo no banco)
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate') || (int)$e->getCode() === 23000) {
            flash('erro', 'E-mail ou CPF já cadastrado.');
        } elseif (str_contains($msg, 'Data truncated') || str_contains($msg, 'perfil')) {
            flash('erro', 'Perfil inválido no banco. Rode o install.php (digite RECRIAR) para atualizar o schema.');
        } else {
            flash('erro', 'Não foi possível salvar o utilizador. Verifique os dados.');
        }
        redirect($id > 0 ? '/pages/usuarios.php?editar=' . $id : '/pages/usuarios.php');
    }
    redirect('/pages/usuarios.php');
}

$edit = null;
if (!empty($_GET['editar'])) {
    $st = $pdo->prepare("SELECT u.*, f.cargo, f.valor_hora, f.comissao_pct FROM usuarios u LEFT JOIN funcionarios f ON f.usuario_id=u.id WHERE u.id=?");
    $st->execute([(int)$_GET['editar']]);
    $edit = $st->fetch();
}
$lista = $pdo->query("SELECT u.*, f.cargo FROM usuarios u LEFT JOIN funcionarios f ON f.usuario_id=u.id ORDER BY u.ativo DESC, u.nome")->fetchAll();

$pageTitle = 'Utilizadores e perfis';
$pageHint = 'Perfis: Dono, Almoxarife, Mecânico e Financeiro. Senha padrão de novos usuários: root.';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="split">
<section class="card">
    <h2><?= $edit ? 'Editar utilizador' : 'Novo utilizador' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <label>Nome</label>
        <input name="nome" required value="<?= e($edit['nome'] ?? '') ?>">
        <div class="form-grid" style="margin-top:12px">
            <div><label>E-mail</label><input type="email" name="email" required value="<?= e($edit['email'] ?? '') ?>"></div>
            <div><label>CPF</label><input name="nif" value="<?= e($edit['nif'] ?? '') ?>"></div>
            <div>
                <label>Perfil</label>
                <select name="perfil">
                    <?php foreach (['dono'=>'Dono','almoxarife'=>'Almoxarife','mecanico'=>'Mecânico','financeiro'=>'Financeiro'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= (($edit['perfil'] ?? '')===$k)?'selected':'' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Telefone</label><input name="telefone" value="<?= e($edit['telefone'] ?? '') ?>"></div>
            <div class="full"><label>Nova senha <?= $edit?'(deixe em branco para manter)':'(padrão root se vazio)' ?></label><input type="password" name="senha"></div>
            <div><label>Cargo</label><input name="cargo" value="<?= e($edit['cargo'] ?? '') ?>"></div>
            <div><label>Valor hora (R$)</label><input name="valor_hora" value="<?= e((string)($edit['valor_hora'] ?? '0')) ?>"></div>
            <div><label>Comissão %</label><input name="comissao_pct" value="<?= e((string)($edit['comissao_pct'] ?? '0')) ?>"></div>
            <div class="full"><label><input type="checkbox" name="ativo" <?= empty($edit)||!empty($edit['ativo'])?'checked':'' ?>> Ativo</label></div>
        </div>
        <div class="row-actions">
            <button class="btn" type="submit">Salvar</button>
            <?php if ($edit): ?><a class="btn btn-ghost" href="usuarios.php">Cancelar</a><?php endif; ?>
        </div>
    </form>
    <?php if ($edit && (int)$edit['id'] !== (int)current_user()['id']): ?>
    <form method="post" style="margin-top:10px">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
        <button class="btn btn-danger" type="submit" data-confirm="Excluir este perfil definitivamente?">Excluir perfil</button>
    </form>
    <?php endif; ?>
</section>
<section class="card">
    <h2>Equipe</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nome</th><th>Acesso</th><th>Perfil</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lista as $u): ?>
                <tr>
                    <td><?= e($u['nome']) ?><?php if(!$u['ativo']): ?> <span class="badge badge-off">inativo</span><?php endif; ?><br><span class="muted"><?= e($u['cargo']) ?></span></td>
                    <td class="muted"><?= e($u['email']) ?><br><span class="mono"><?= e($u['nif']) ?></span></td>
                    <td><span class="badge badge-info"><?= e(perfil_label($u['perfil'])) ?></span></td>
                    <td>
                        <a class="btn-link" href="?editar=<?= (int)$u['id'] ?>">editar</a>
                        <?php if ((int)$u['id'] !== (int)current_user()['id']): ?>
                        <form method="post" style="display:inline;margin-left:8px">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button class="btn-link" type="submit" data-confirm="Excluir o perfil de <?= e($u['nome']) ?>? Esta ação não tem volta." style="color:#ff6b6b">excluir</button>
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
