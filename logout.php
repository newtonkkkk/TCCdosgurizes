<?php
require_once __DIR__ . '/includes/auth.php';
logout();
session_start();
flash('ok', 'Sessão encerrada com segurança.');
redirect('/index.php');
