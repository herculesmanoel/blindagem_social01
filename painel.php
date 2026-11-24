$msgSucesso = '';
$msgErro = '';
$pixGerado = null;
$efeitoCompra = '';
$abaAtiva = $_GET['aba'] ?? 'pedido';

if (isset($_GET['sincronizar'])) {
@@ -70,14 +71,17 @@

                    $pdo->commit();
                    $user['saldo'] -= $valorTotal;
                    $efeitoCompra = strtolower($serv['plataforma']);
                    $msgSucesso = "Pedido #{$codigo} enviado com sucesso! Valor debitado: R$ " . number_format($valorTotal, 2, ',', '.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msgErro = 'Erro ao processar pedido: ' . $e->getMessage();
                }
            }
        }
    } elseif ($acao === 'gerar_pix') {
    }

    elseif ($acao === 'gerar_pix') {
        $abaAtiva = 'saldo';
        $valorRecarga = round((float)str_replace(',', '.', $_POST['valor'] ?? '0'), 2);
        if ($valorRecarga < 1.00) {
@@ -92,6 +96,7 @@
                    ->execute([$user['id'], $txid, $valorRecarga, $copiaCola]);

                $pixGerado = ['valor' => $valorRecarga, 'codigo' => $copiaCola, 'txid' => $txid];
                $efeitoCompra = 'boasvindas';
            } catch (Exception $e) {
                $msgErro = 'Erro ao gerar PIX Efí: ' . $e->getMessage();
            }
@@ -133,7 +138,6 @@
    $totalGasto += (float)$lp['valor_total'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
@@ -146,10 +150,10 @@

<div class="live-bar">
  <div class="live-track">
    <span class="live-item"><span class="dot-live"></span> <strong>@lucas.mendes</strong> acabou de pedir <strong class="gold">1.000 Seguidores Instagram BR</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@camila_oliveira</strong> acabou de pedir <strong class="gold">500 Curtidas Reais</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@studiobella_sp</strong> adicionou <strong class="gold">R$ 50,00 via PIX</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@rafael.trader</strong> acabou de pedir <strong class="gold">2.000 Seguidores TikTok</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@lucas.mendes</strong> acabou de pedir <strong class="gold-solid">1.000 Seguidores Instagram BR</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@camila_oliveira</strong> acabou de pedir <strong class="gold-solid">500 Curtidas Reais</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@studiobella_sp</strong> adicionou <strong class="gold-solid">R$ 50,00 via PIX</strong></span>
    <span class="live-item"><span class="dot-live"></span> <strong>@rafael.trader</strong> acabou de pedir <strong class="gold-solid">2.000 Seguidores TikTok</strong></span>
  </div>
</div>

@@ -434,7 +438,37 @@ function copiarTexto(id) {
  navigator.clipboard.writeText(el.value);
  alert('Copiado com sucesso!');
}
function soltarEmojis(tipo) {
  const mapas = {
    boasvindas: ['👑', '🎉', '✨', '💰', '🚀'],
    tiktok:     ['🎵', '🎶', '🔥', '✨', '🚀'],
    instagram:  ['📸', '❤️', '🔥', '🚀', '✨'],
    youtube:    ['▶️', '🔔', '🔥', '🚀', '🎬'],
    facebook:   ['👍', '💙', '🔥', '🚀', '✨'],
    telegram:   ['✈️', '💎', '🔥', '🚀', '✨'],
    kwai:       ['🎵', '🧡', '🔥', '🚀', '✨'],
    padrao:     ['👑', '💎', '🚀', '✨', '🔥']
  };
  const lista = mapas[tipo] || mapas.padrao;
  for (let i = 0; i < 28; i++) {
    setTimeout(() => {
      const el = document.createElement('div');
      el.className = 'emoji-rain';
      el.innerText = lista[Math.floor(Math.random() * lista.length)];
      el.style.left = Math.random() * 94 + 'vw';
      el.style.fontSize = (22 + Math.random() * 18) + 'px';
      el.style.animationDuration = (2.2 + Math.random() * 1.4) + 's';
      document.body.appendChild(el);
      setTimeout(() => el.remove(), 3800);
    }, i * 70);
  }
}
calcularPainel();
<?php if (($_GET['efeito'] ?? '') === 'boasvindas'): ?>
  soltarEmojis('boasvindas');
<?php elseif (!empty($efeitoCompra)): ?>
  soltarEmojis(<?= json_encode($efeitoCompra) ?>);
<?php endif; ?>
</script>
</body>
</html>
