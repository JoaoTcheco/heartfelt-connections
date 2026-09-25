/**
 * ============================================================
 * FarmaPonto - Processo principal (Electron)
 * ============================================================
 * Responsabilidade: arrancar o PHP embutido num porto local aleatorio,
 * esperar que responda e abrir a janela da aplicacao.
 *
 * 100% offline: nada sai do computador. O servidor escuta apenas em
 * 127.0.0.1 e o porto e escolhido ao acaso em cada arranque.
 *
 * Seguranca:
 *   - contextIsolation ligado, nodeIntegration desligado;
 *   - navegacao limitada ao servidor local (qualquer outro endereco e recusado);
 *   - o codigo PHP vive dentro do cofre cifrado (ver scripts/proteger.php);
 *   - os dados do utilizador ficam em %APPDATA%\FarmaPonto.
 */

'use strict';

const { app, BrowserWindow, shell, dialog, Menu } = require('electron');
const { spawn } = require('child_process');
const http = require('http');
const net = require('net');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');

// ---------- Localizacao dos recursos ----------

const emDesenvolvimento = !app.isPackaged;
const pastaRecursos = emDesenvolvimento
  ? path.join(__dirname, 'recursos')
  : process.resourcesPath;

// Em Windows o motor e php.exe; noutros sistemas (usado nos testes) e php.
const phpBinario = path.join(pastaRecursos, 'php', process.platform === 'win32' ? 'php.exe' : 'php');
const phpIni = path.join(pastaRecursos, 'php', 'php.ini');
const pastaSite = path.join(pastaRecursos, 'site');
const routerSite = path.join(pastaSite, 'router.php');

// ---------- Pastas de dados do utilizador (%APPDATA%\FarmaPonto) ----------

const pastaDados = path.join(app.getPath('appData'), 'FarmaPonto');
const caminhos = {
  dados: pastaDados,
  bd: path.join(pastaDados, 'farmaponto.db'),
  storage: path.join(pastaDados, 'storage'),
  selfies: path.join(pastaDados, 'storage', 'selfies'),
  backups: path.join(pastaDados, 'storage', 'backups'),
  sessoes: path.join(pastaDados, 'sessoes'),
  registos: path.join(pastaDados, 'registos'),
};

function garantirPastas() {
  for (const p of Object.values(caminhos)) {
    if (p === caminhos.bd) continue;
    fs.mkdirSync(p, { recursive: true });
  }
}

// ---------- Porto local livre e aleatorio ----------

function portoLivre() {
  return new Promise((resolve, reject) => {
    const s = net.createServer();
    s.on('error', reject);
    // 0 = o sistema escolhe um porto livre
    s.listen(0, '127.0.0.1', () => {
      const { port } = s.address();
      s.close(() => resolve(port));
    });
  });
}

// ---------- Arranque do PHP ----------

let servidor = null;
let porto = 0;
const segredoInterno = crypto.randomBytes(32).toString('hex');

function ambientePhp() {
  return {
    ...process.env,
    FP_MODO: 'desktop',
    FP_DB_DRIVER: 'sqlite',
    FP_DB_FILE: caminhos.bd,
    FP_STORAGE_DIR: caminhos.storage,
    FP_SELFIE_DIR: caminhos.selfies,
    FP_BACKUP_DIR: caminhos.backups,
    FP_SESSION_DIR: caminhos.sessoes,
    FP_LOG_DIR: caminhos.registos,
    FP_HOST_BIN: process.execPath, // usado na verificacao de integridade
    FP_SEGREDO: segredoInterno,    // partilhado apenas entre esta janela e o PHP
  };
}

// Sessoes e registo de erros do PHP tambem dentro da pasta de dados do utilizador
function opcoesPhp() {
  return [
    '-c', phpIni,
    '-d', `session.save_path=${caminhos.sessoes}`,
    '-d', `error_log=${path.join(caminhos.registos, 'php-erros.log')}`,
  ];
}

function prepararDados() {
  return new Promise((resolve, reject) => {
    const p = spawn(phpBinario, [...opcoesPhp(), path.join(pastaSite, 'preparar.php')], {
      cwd: pastaSite,
      env: ambientePhp(),
      windowsHide: true,
    });
    let saida = '';
    p.stdout.on('data', (d) => (saida += d.toString()));
    p.stderr.on('data', (d) => (saida += d.toString()));
    p.on('error', reject);
    p.on('close', (codigo) => (codigo === 0 ? resolve(saida) : reject(new Error(saida))));
  });
}

function arrancarServidor() {
  servidor = spawn(
    phpBinario,
    [...opcoesPhp(), '-S', `127.0.0.1:${porto}`, '-t', pastaSite, routerSite],
    { cwd: pastaSite, env: ambientePhp(), windowsHide: true },
  );
  servidor.stdout.on('data', (d) => console.log('[php]', d.toString().trim()));
  servidor.stderr.on('data', (d) => console.log('[php]', d.toString().trim()));
  servidor.on('close', (codigo) => {
    servidor = null;
    if (codigo !== 0 && !app.isQuitting) {
      dialog.showErrorBox('FarmaPonto', 'O motor interno parou inesperadamente. Feche e volte a abrir o programa.');
    }
  });
}

function esperarServidor(tentativas = 60) {
  return new Promise((resolve, reject) => {
    const tentar = (n) => {
      const req = http.get({ host: '127.0.0.1', port: porto, path: '/login', timeout: 1000 }, (res) => {
        res.resume();
        resolve();
      });
      req.on('error', () => (n <= 1 ? reject(new Error('O motor interno nao respondeu.')) : setTimeout(() => tentar(n - 1), 250)));
      req.on('timeout', () => { req.destroy(); n <= 1 ? reject(new Error('tempo excedido')) : setTimeout(() => tentar(n - 1), 250); });
    };
    tentar(tentativas);
  });
}

// ---------- Janela ----------

let janela = null;

function criarJanela() {
  janela = new BrowserWindow({
    width: 1360,
    height: 860,
    minWidth: 1024,
    minHeight: 700,
    show: false,
    backgroundColor: '#0f172a',
    title: 'FarmaPonto',
    icon: path.join(pastaRecursos, 'icone.png'),
    webPreferences: {
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
      devTools: false,
      spellcheck: false,
    },
  });

  janela.setMenu(null);
  janela.once('ready-to-show', () => {
    janela.show();
    janela.maximize();
  });

  const base = `http://127.0.0.1:${porto}`;

  // Ligacoes externas abrem no navegador do sistema; nunca dentro da janela.
  janela.webContents.setWindowOpenHandler(({ url }) => {
    if (!url.startsWith(base)) { shell.openExternal(url); }
    return { action: 'deny' };
  });
  janela.webContents.on('will-navigate', (evento, url) => {
    if (!url.startsWith(base)) { evento.preventDefault(); }
  });

  janela.loadURL(`${base}/login`);
}

// ---------- Ciclo de vida ----------

// Uma so instancia: evita duas janelas a escrever na mesma base de dados.
if (!app.requestSingleInstanceLock()) {
  app.quit();
} else {
  app.on('second-instance', () => {
    if (janela) { janela.isMinimized() && janela.restore(); janela.focus(); }
  });

  app.whenReady().then(async () => {
    Menu.setApplicationMenu(null);
    try {
      garantirPastas();
      await prepararDados();
      porto = await portoLivre();
      arrancarServidor();
      await esperarServidor();
      criarJanela();
    } catch (erro) {
      dialog.showErrorBox(
        'FarmaPonto',
        'Nao foi possivel iniciar o programa.\n\n' + String(erro && erro.message ? erro.message : erro),
      );
      app.quit();
    }
  });

  app.on('window-all-closed', () => app.quit());
  app.on('before-quit', () => {
    app.isQuitting = true;
    if (servidor) { try { servidor.kill(); } catch (_) {} }
  });
}
