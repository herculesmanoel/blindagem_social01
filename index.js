const express = require('express');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason } = require('@whiskeysockets/baileys');
const pino = require('pino');
const QRCode = require('qrcode');

const app = express();
app.use(express.json());

const MEU_ZAP = '5581986520587@s.whatsapp.net';
const CHAVE_INTERNA = process.env.ZAP_SECRET || 'blindagem_zap_2026';

let sock = null;
let qrAtual = null;
let conectado = false;

async function iniciarZap() {
  const { state, saveCreds } = await useMultiFileAuthState('./sessao_zap');
  sock = makeWASocket({
    auth: state,
    logger: pino({ level: 'silent' }),
    printQRInTerminal: false
  });

  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('connection.update', ({ connection, lastDisconnect, qr }) => {
    if (qr) {
      qrAtual = qr;
      conectado = false;
    }
    if (connection === 'open') {
      conectado = true;
      qrAtual = null;
      console.log('WhatsApp conectado com sucesso!');
    }
    if (connection === 'close') {
      conectado = false;
      const reconectar = lastDisconnect?.error?.output?.statusCode !== DisconnectReason.loggedOut;
      if (reconectar) setTimeout(iniciarZap, 3000);
    }
  });
}

// Tela privada para você escanear o QR Code uma única vez
app.get('/', async (req, res) => {
  if (conectado) {
    return res.send('<body style="background:#050507;color:#34d399;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh"><h1>✅ WhatsApp Blindagem Social Conectado!</h1></body>');
  }
  if (!qrAtual) {
    return res.send('<body style="background:#050507;color:#fbbf24;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh"><h2>Gerando QR Code... atualize em 3 segundos.</h2><script>setTimeout(()=>location.reload(),3000)</script></body>');
  }
  const img = await QRCode.toDataURL(qrAtual);
  res.send(`<body style="background:#050507;color:#fff;font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh"><h2>Escaneie com seu WhatsApp</h2><img src="${img}" style="border:6px solid #fbbf24;border-radius:16px;margin-top:16px"><script>setTimeout(()=>location.reload(),15000)</script></body>`);
});

// Rota interna chamada pelo painel.php
app.post('/notificar', async (req, res) => {
  if (req.headers['x-secret'] !== CHAVE_INTERNA) {
    return res.status(401).json({ erro: 'Nao autorizado' });
  }
  const texto = req.body.mensagem || '';
  if (!conectado || !sock || !texto) {
    return res.status(400).json({ erro: 'Zap desconectado ou vazio' });
  }
  await sock.sendMessage(MEU_ZAP, { text: texto });
  res.json({ ok: true });
});

iniciarZap();
app.listen(process.env.PORT || 3000, () => console.log('Servidor Zap rodando'));
