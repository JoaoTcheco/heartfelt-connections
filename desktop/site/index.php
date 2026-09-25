<?php
/**
 * FarmaPonto - arranque da aplicacao (versao de secretaria).
 * Este ficheiro nao contem logica de negocio: abre o cofre cifrado
 * e entrega o pedido ao controlador principal que vive dentro dele.
 */

require __DIR__ . '/carregador.php';

Cofre::arrancar(__DIR__);

require 'cofre://index.php';
