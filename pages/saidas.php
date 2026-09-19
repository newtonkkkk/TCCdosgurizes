<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();
flash('ok', 'Saídas e vendas agora ficam na aba Peças.');
redirect('/pages/produtos.php?tab=saida');
