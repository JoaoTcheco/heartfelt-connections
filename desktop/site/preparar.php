<?php
/**
 * FarmaPonto - preparacao da pasta de dados (chamado pelo programa ao arrancar).
 * Cria a base de dados, as pastas e o administrador inicial se ainda nao existirem.
 * Sem logica visivel: tudo vem do cofre cifrado.
 */

require __DIR__ . '/carregador.php';

Cofre::arrancar(__DIR__);

if (!defined('BASE_PATH')) { define('BASE_PATH', ''); }
if (!defined('PEDIDO_ID')) { define('PEDIDO_ID', 'app-' . substr(bin2hex(random_bytes(4)), 0, 8)); }

require 'cofre://scripts/preparar-dados.php';
