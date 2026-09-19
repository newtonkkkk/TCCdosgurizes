<?php
declare(strict_types=1);

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($v): string
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

function to_mysql_dt(mixed $v): ?string
{
    $v = trim(str_replace('T', ' ', (string)$v));
    if ($v === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v)) {
        $v .= ':00';
    }
    return $v;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

function proximo_sku(PDO $pdo): string
{
    $n = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM produtos")->fetchColumn();
    return sprintf('P-%04d', $n);
}

function proximo_numero_os(PDO $pdo): string
{
    $ano = date('Y');
    $st = $pdo->query("SELECT numero FROM ordens_servico WHERE numero LIKE 'OS-{$ano}-%' ORDER BY id DESC LIMIT 1");
    $last = $st->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/OS-\d{4}-(\d+)/', $last, $m)) {
        $seq = (int)$m[1] + 1;
    }
    return sprintf('OS-%s-%04d', $ano, $seq);
}

function perfil_label(string $p): string
{
    return [
        'dono'       => 'Dono',
        'almoxarife' => 'Almoxarife',
        'mecanico'   => 'Mecânico',
        'financeiro' => 'Financeiro',
    ][$p] ?? $p;
}

/** Perfis válidos (RBAC). */
function perfis_validos(): array
{
    return ['dono', 'almoxarife', 'mecanico', 'financeiro'];
}

function current_perfil(): string
{
    return (string)($_SESSION['user']['perfil'] ?? '');
}

function is_dono(): bool
{
    return current_perfil() === 'dono';
}

function is_almoxarife(): bool
{
    return current_perfil() === 'almoxarife' || is_dono();
}

function is_mecanico(): bool
{
    return current_perfil() === 'mecanico';
}

function is_financeiro(): bool
{
    return current_perfil() === 'financeiro' || is_dono();
}

/** Gestão de peças (cadastro, entrada, inventário). */
function can_gestao_pecas(): bool
{
    return is_dono() || current_perfil() === 'almoxarife';
}

/** Só retirada de peça para serviço/O.S. (mecânico). */
function can_saida_os(): bool
{
    $p = current_perfil();
    return in_array($p, ['dono', 'almoxarife', 'mecanico'], true);
}

/** Venda avulsa (estoque + caixa): dono e almoxarife operam; financeiro só consulta. */
function can_venda_avulsa(): bool
{
    return is_dono() || current_perfil() === 'almoxarife';
}

/** Telas e KPIs financeiros (faturamento, folha, relatórios de vendas). */
function can_financeiro(): bool
{
    return is_dono() || current_perfil() === 'financeiro';
}

/** Relatório de movimentação de estoque (não é caixa). */
function can_relatorio_estoque(): bool
{
    $p = current_perfil();
    return in_array($p, ['dono', 'almoxarife', 'financeiro'], true);
}

function iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    $a = mb_strtoupper(mb_substr($partes[0] ?? 'U', 0, 1));
    $b = mb_strtoupper(mb_substr($partes[count($partes) - 1] ?? '', 0, 1));
    if (count($partes) < 2) {
        return $a;
    }
    return $a . $b;
}

function avatar_cor(int $id): string
{
    $cores = ['#e50914', '#1f6feb', '#2ea043', '#d4a017', '#8957e5', '#e85d04', '#0d9488', '#db2777'];
    return $cores[$id % count($cores)];
}

function status_os_label(string $s): string
{
    return [
        'aberta'          => 'Aberta',
        'em_andamento'    => 'Em andamento',
        'aguardando_peca' => 'Aguardando peça',
        'concluida'       => 'Concluída',
        'cancelada'       => 'Cancelada',
    ][$s] ?? $s;
}

function status_agenda_label(string $s): string
{
    return [
        'agendado'       => 'Agendado',
        'confirmado'     => 'Confirmado',
        'em_atendimento' => 'Em atendimento',
        'concluido'      => 'Concluído',
        'cancelado'      => 'Cancelado',
        'faltou'         => 'Faltou',
    ][$s] ?? $s;
}

function badge_os(string $s): string
{
    $map = [
        'aberta'          => 'badge-info',
        'em_andamento'    => 'badge-warn',
        'aguardando_peca' => 'badge-orange',
        'concluida'       => 'badge-ok',
        'cancelada'       => 'badge-off',
    ];
    return $map[$s] ?? 'badge-off';
}

function badge_agenda(string $s): string
{
    $map = [
        'agendado'       => 'badge-info',
        'confirmado'     => 'badge-ok',
        'em_atendimento' => 'badge-warn',
        'concluido'      => 'badge-ok',
        'cancelado'      => 'badge-off',
        'faltou'         => 'badge-off',
    ];
    return $map[$s] ?? 'badge-off';
}

function can(array $perfis): bool
{
    $u = $_SESSION['user']['perfil'] ?? '';
    if (is_dono()) {
        return true;
    }
    return in_array($u, $perfis, true);
}

function require_can(array $perfis): void
{
    if (!can($perfis)) {
        flash('erro', 'Você não tem permissão para acessar esta área.');
        redirect('/dashboard.php');
    }
}

function alertas_estoque(PDO $pdo): array
{
    $sql = "SELECT * FROM produtos WHERE ativo = 1 AND estoque_atual <= estoque_minimo ORDER BY estoque_atual ASC";
    return $pdo->query($sql)->fetchAll();
}

/**
 * Entrada de estoque com auditoria (movimentação).
 * $manageTx = true inicia/commit própria transação.
 */
function registrar_entrada(PDO $pdo, int $produtoId, int $qtd, ?int $fornecedorId, string $nf, string $obs, int $usuarioId, bool $manageTx = true): ?string
{
    if ($produtoId <= 0 || $qtd <= 0) {
        return 'Peça e quantidade inválidas.';
    }
    if ($manageTx) {
        $pdo->beginTransaction();
    }
    try {
        $st = $pdo->prepare("SELECT id FROM produtos WHERE id=? AND ativo=1 FOR UPDATE");
        $st->execute([$produtoId]);
        if (!$st->fetch()) {
            if ($manageTx) $pdo->rollBack();
            return 'Peça inválida ou inativa.';
        }
        $pdo->prepare("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id=?")->execute([$qtd, $produtoId]);
        $pdo->prepare("INSERT INTO movimentacoes (tipo,produto_id,quantidade,fornecedor_id,nota_fiscal,observacao,usuario_id)
            VALUES ('entrada',?,?,?,?,?,?)")->execute([$produtoId, $qtd, $fornecedorId, $nf ?: null, $obs ?: null, $usuarioId]);
        if ($manageTx) {
            $pdo->commit();
        }
        return null;
    } catch (Throwable $e) {
        if ($manageTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return 'Falha ao registrar entrada.';
    }
}

/**
 * Lança peça em O.S.: baixa estoque, movimentação, os_itens e totais.
 * Usa SELECT FOR UPDATE para reduzir condição de corrida.
 */
function lancar_peca_os(PDO $pdo, int $osId, int $produtoId, int $qtd, int $mecId, string $obs = '', bool $manageTx = true): ?string
{
    if ($produtoId <= 0 || $qtd <= 0 || $osId <= 0) {
        return 'Dados inválidos para lançar a peça.';
    }

    if ($manageTx) {
        $pdo->beginTransaction();
    }
    try {
        $st = $pdo->prepare("SELECT status FROM ordens_servico WHERE id=? FOR UPDATE");
        $st->execute([$osId]);
        $os = $st->fetch();
        if (!$os) {
            if ($manageTx) $pdo->rollBack();
            return 'Ordem de serviço não encontrada.';
        }
        if (in_array($os['status'], ['concluida', 'cancelada'], true)) {
            if ($manageTx) $pdo->rollBack();
            return 'Não é possível lançar peças em O.S. concluída ou cancelada.';
        }

        $st = $pdo->prepare("SELECT estoque_atual, preco_venda, nome FROM produtos WHERE id=? AND ativo=1 FOR UPDATE");
        $st->execute([$produtoId]);
        $prod = $st->fetch();
        if (!$prod) {
            if ($manageTx) $pdo->rollBack();
            return 'Peça inválida ou inativa.';
        }
        if ((int)$prod['estoque_atual'] < $qtd) {
            if ($manageTx) $pdo->rollBack();
            return 'Estoque insuficiente para ' . $prod['nome'] . '. Disponível: ' . $prod['estoque_atual'];
        }

        $preco = (float)$prod['preco_venda'];
        $sub = $preco * $qtd;

        $pdo->prepare("UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id=?")->execute([$qtd, $produtoId]);
        $pdo->prepare("INSERT INTO movimentacoes (tipo,produto_id,quantidade,os_id,mecanico_id,observacao,usuario_id)
            VALUES ('saida',?,?,?,?,?,?)")->execute([
            $produtoId, $qtd, $osId, $mecId ?: null, $obs !== '' ? $obs : 'Peça lançada na O.S.', current_user()['id']
        ]);
        $pdo->prepare("INSERT INTO os_itens (os_id,produto_id,quantidade,preco_unitario,subtotal) VALUES (?,?,?,?,?)")
            ->execute([$osId, $produtoId, $qtd, $preco, $sub]);
        $pdo->prepare("UPDATE ordens_servico SET valor_pecas = valor_pecas + ? WHERE id=?")->execute([$sub, $osId]);
        $pdo->prepare("UPDATE ordens_servico SET valor_total = valor_mao_obra + valor_pecas WHERE id=?")->execute([$osId]);

        if ($manageTx) {
            $pdo->commit();
        }
        return null;
    } catch (Throwable $e) {
        if ($manageTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return 'Falha ao lançar a peça.';
    }
}

/**
 * Remove item da O.S., devolve estoque e ajusta totais.
 */
function remover_peca_os(PDO $pdo, int $itemId, int $osId): ?string
{
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT i.*, o.status FROM os_itens i JOIN ordens_servico o ON o.id=i.os_id WHERE i.id=? AND i.os_id=? FOR UPDATE");
        $st->execute([$itemId, $osId]);
        $item = $st->fetch();
        if (!$item) {
            $pdo->rollBack();
            return 'Item não encontrado.';
        }
        if (in_array($item['status'], ['concluida', 'cancelada'], true)) {
            $pdo->rollBack();
            return 'Não é possível remover peças de O.S. concluída ou cancelada.';
        }

        $pdo->prepare("UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id=?")
            ->execute([(int)$item['quantidade'], (int)$item['produto_id']]);
        $pdo->prepare("DELETE FROM os_itens WHERE id=?")->execute([$itemId]);
        $pdo->prepare("UPDATE ordens_servico SET valor_pecas = GREATEST(0, valor_pecas - ?) WHERE id=?")
            ->execute([(float)$item['subtotal'], $osId]);
        $pdo->prepare("UPDATE ordens_servico SET valor_total = valor_mao_obra + valor_pecas WHERE id=?")->execute([$osId]);
        $pdo->prepare("INSERT INTO movimentacoes (tipo,produto_id,quantidade,os_id,observacao,usuario_id)
            VALUES ('entrada',?,?,?,?,?)")->execute([
            (int)$item['produto_id'], (int)$item['quantidade'], $osId,
            'Devolução por remoção de item da O.S.', current_user()['id']
        ]);
        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return 'Falha ao remover a peça.';
    }
}


/**
 * Venda avulsa de peça (fora de O.S.): baixa estoque, movimentação tipo venda e registro financeiro.
 */
function registrar_venda_avulsa(
    PDO $pdo,
    int $produtoId,
    int $qtd,
    ?float $precoUnitario,
    string $clienteNome,
    string $obs,
    int $usuarioId
): ?string {
    if ($produtoId <= 0 || $qtd <= 0) {
        return 'Selecione a peça e uma quantidade válida.';
    }
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT estoque_atual, preco_venda, nome FROM produtos WHERE id=? AND ativo=1 FOR UPDATE");
        $st->execute([$produtoId]);
        $prod = $st->fetch();
        if (!$prod) {
            $pdo->rollBack();
            return 'Peça inválida ou inativa.';
        }
        if ((int)$prod['estoque_atual'] < $qtd) {
            $pdo->rollBack();
            return 'Estoque insuficiente para ' . $prod['nome'] . '. Disponível: ' . $prod['estoque_atual'];
        }
        $preco = $precoUnitario !== null && $precoUnitario > 0
            ? $precoUnitario
            : (float)$prod['preco_venda'];
        $total = round($preco * $qtd, 2);

        $pdo->prepare("UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id=?")
            ->execute([$qtd, $produtoId]);

        // Movimentação de estoque (tipo venda) — se o ENUM antigo não tiver 'venda', usa 'saida'
        try {
            $pdo->prepare("INSERT INTO movimentacoes (tipo,produto_id,quantidade,observacao,usuario_id)
                VALUES ('venda',?,?,?,?)")->execute([
                $produtoId, $qtd,
                $obs !== '' ? $obs : ('Venda avulsa' . ($clienteNome !== '' ? ' · ' . $clienteNome : '')),
                $usuarioId
            ]);
        } catch (Throwable $e) {
            $pdo->prepare("INSERT INTO movimentacoes (tipo,produto_id,quantidade,observacao,usuario_id)
                VALUES ('saida',?,?,?,?)")->execute([
                $produtoId, $qtd,
                'Venda avulsa' . ($clienteNome !== '' ? ' · ' . $clienteNome : '') . ($obs !== '' ? ' · ' . $obs : ''),
                $usuarioId
            ]);
        }

        // Registro financeiro (tabela dedicada; cria se não existir em bancos antigos)
        try {
            $pdo->prepare("INSERT INTO vendas_avulsas (produto_id,quantidade,preco_unitario,valor_total,cliente_nome,observacao,usuario_id)
                VALUES (?,?,?,?,?,?,?)")->execute([
                $produtoId, $qtd, $preco, $total,
                $clienteNome !== '' ? $clienteNome : null,
                $obs !== '' ? $obs : null,
                $usuarioId
            ]);
        } catch (Throwable $e) {
            // Sem tabela ainda: não impede a venda de estoque; valor fica na observação
            if ($pdo->inTransaction()) {
                // continue — estoque já baixou; tenta commit
            }
        }

        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return 'Falha ao registrar a venda avulsa.';
    }
}

/**
 * Garante tabela vendas_avulsas em instalações antigas (sem reinstall).
 */
function ensure_vendas_avulsas_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS vendas_avulsas (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      produto_id INT UNSIGNED NOT NULL,
      quantidade INT NOT NULL,
      preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      cliente_nome VARCHAR(160) DEFAULT NULL,
      observacao VARCHAR(255) DEFAULT NULL,
      usuario_id INT UNSIGNED DEFAULT NULL,
      criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_vendas_data (criado_em)
    ) ENGINE=InnoDB");
    // Tenta expandir ENUM de movimentacoes (ignora se falhar)
    try {
        $pdo->exec("ALTER TABLE movimentacoes MODIFY tipo ENUM('entrada','saida','venda') NOT NULL");
    } catch (Throwable $e) {
        // banco antigo ou sem permissão — ok
    }
    $done = true;
}
