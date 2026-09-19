<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();
require_can(['dono', 'mecanico']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('erro', 'Sessão expirada. Tente salvar de novo.');
        redirect('/pages/agenda.php');
    }
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar') {
        $id = (int)($_POST['id'] ?? 0);
        $dados = [
            'cliente_id'    => (int)($_POST['cliente_id'] ?? 0) ?: null,
            'cliente_nome'  => trim($_POST['cliente_nome'] ?? ''),
            'telefone'      => trim($_POST['telefone'] ?? ''),
            'veiculo'       => trim($_POST['veiculo'] ?? ''),
            'mecanico_id'   => (int)($_POST['mecanico_id'] ?? 0) ?: null,
            'data_hora'     => to_mysql_dt($_POST['data_hora'] ?? '') ?: date('Y-m-d H:i:s'),
            'servico'       => trim($_POST['servico'] ?? ''),
            'status'        => $_POST['status'] ?? 'agendado',
            'observacao'    => trim($_POST['observacao'] ?? ''),
        ];
        if ($dados['cliente_nome'] === '' && !$dados['cliente_id']) {
            flash('erro', 'Informe o cliente ou o nome.');
        } else {
            if ($dados['cliente_id']) {
                $c = $pdo->prepare("SELECT nome,telefone FROM clientes WHERE id=?");
                $c->execute([$dados['cliente_id']]);
                $cli = $c->fetch();
                if ($cli && $dados['cliente_nome'] === '') $dados['cliente_nome'] = $cli['nome'];
                if ($cli && $dados['telefone'] === '') $dados['telefone'] = $cli['telefone'];
            } elseif ($dados['cliente_nome'] !== '') {
                $pdo->prepare("INSERT INTO clientes (nome, telefone) VALUES (?,?)")->execute([$dados['cliente_nome'], $dados['telefone'] ?: null]);
                $dados['cliente_id'] = (int)$pdo->lastInsertId();
            }
            try {
                if ($id > 0) {
                    $dados['id'] = $id;
                    $pdo->prepare("UPDATE agendamentos SET cliente_id=:cliente_id,cliente_nome=:cliente_nome,telefone=:telefone,veiculo=:veiculo,mecanico_id=:mecanico_id,data_hora=:data_hora,servico=:servico,status=:status,observacao=:observacao WHERE id=:id")->execute($dados);
                    flash('ok', 'Agendamento atualizado.');
                } else {
                    $pdo->prepare("INSERT INTO agendamentos (cliente_id,cliente_nome,telefone,veiculo,mecanico_id,data_hora,servico,status,observacao) VALUES (:cliente_id,:cliente_nome,:telefone,:veiculo,:mecanico_id,:data_hora,:servico,:status,:observacao)")->execute($dados);
                    flash('ok', 'Horário reservado.');
                }
            } catch (Throwable $e) {
                flash('erro', 'Não foi possível salvar o agendamento.');
            }
        }
        redirect('/pages/agenda.php');
    }
}

$edit = null;
if (!empty($_GET['editar'])) {
    $st = $pdo->prepare("SELECT * FROM agendamentos WHERE id=?");
    $st->execute([(int)$_GET['editar']]);
    $edit = $st->fetch();
}

$clientes = $pdo->query("SELECT id,nome,telefone FROM clientes ORDER BY nome")->fetchAll();
$mecs = $pdo->query("SELECT id,nome FROM usuarios WHERE perfil IN ('mecanico','dono') AND ativo=1 ORDER BY nome")->fetchAll();
$lista = $pdo->query("
    SELECT a.*, u.nome AS mecanico
    FROM agendamentos a
    LEFT JOIN usuarios u ON u.id=a.mecanico_id
    ORDER BY a.data_hora DESC
")->fetchAll();

$pageTitle = 'Agenda';
$pageHint = 'Horários de atendimento e alocação de mecânicos.';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="split">
<section class="card">
    <h2><?= $edit ? 'Editar horário' : 'Novo agendamento' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <label>Cliente cadastrado (opcional)</label>
        <select name="cliente_id">
            <option value="">Avulso / novo</option>
            <?php foreach ($clientes as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= ((int)($edit['cliente_id'] ?? 0)===$c['id'])?'selected':'' ?>><?= e($c['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-grid" style="margin-top:12px">
            <div><label>Nome</label><input name="cliente_nome" value="<?= e($edit['cliente_nome'] ?? '') ?>"></div>
            <div><label>Telefone</label><input name="telefone" value="<?= e($edit['telefone'] ?? '') ?>"></div>
            <div class="full"><label>Veículo</label><input name="veiculo" value="<?= e($edit['veiculo'] ?? '') ?>"></div>
            <div>
                <label>Mecânico</label>
                <select name="mecanico_id">
                    <option value="">—</option>
                    <?php foreach ($mecs as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= ((int)($edit['mecanico_id'] ?? 0)===$m['id'])?'selected':'' ?>><?= e($m['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="full"><label>Data e hora</label><input type="datetime-local" name="data_hora" required value="<?= !empty($edit['data_hora']) ? date('Y-m-d\TH:i', strtotime($edit['data_hora'])) : date('Y-m-d\TH:i') ?>"></div>
            <div class="full"><label>Serviço</label><input name="servico" value="<?= e($edit['servico'] ?? '') ?>"></div>
            <div>
                <label>Status</label>
                <select name="status">
                    <?php foreach (['agendado','confirmado','em_atendimento','concluido','cancelado','faltou'] as $s): ?>
                        <option value="<?= $s ?>" <?= (($edit['status'] ?? 'agendado')===$s)?'selected':'' ?>><?= status_agenda_label($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="full"><label>Observação</label><textarea name="observacao"><?= e($edit['observacao'] ?? '') ?></textarea></div>
        </div>
        <div class="row-actions"><button class="btn" type="submit">Salvar</button></div>
    </form>
</section>

<section class="card">
    <h2>Próximos e histórico</h2>
    <input type="search" placeholder="Filtrar..." data-filter-table="#tab-ag" style="margin-bottom:12px">
    <div class="table-wrap">
        <table id="tab-ag">
            <thead><tr><th>Quando</th><th>Cliente</th><th>Serviço</th><th>Mecânico</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lista as $a): ?>
                <tr>
                    <td class="mono"><?= date('d/m H:i', strtotime($a['data_hora'])) ?></td>
                    <td><?= e($a['cliente_nome']) ?><br><span class="muted"><?= e($a['veiculo']) ?></span></td>
                    <td><?= e($a['servico']) ?></td>
                    <td><?= e($a['mecanico'] ?? '—') ?></td>
                    <td><span class="badge <?= badge_agenda($a['status']) ?>"><?= e(status_agenda_label($a['status'])) ?></span></td>
                    <td><a class="btn-link" href="?editar=<?= (int)$a['id'] ?>">editar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
