# Proposal

## Why

Nos vídeos reproduzidos via transcodificação em tempo real (`FFmpeg` sobre pipe SFTP), os controles de avanço e retrocesso (`-10s`, `+10s`, barra de progresso e setas `←`/`→`) demoram excessivamente para atualizar a imagem ou não respondem de forma fluida, pois cada salto reinicia o stream lendo e decodificando desde o início do arquivo sem debounce nem retorno visual. Além disso, durante o carregamento inicial, buffering de rede ou reposicionamento, o player não exibe nenhum indicador visual de carregamento (spinner), dando a impressão de travamento.

Esta mudança melhora drasticamente a responsividade do seek e a experiência visual de carregamento no player HTML5, mantendo a operação 100% offline, compatível com CSP e estritamente somente-leitura sobre as pastas de mídia.

## What Changes

1. **Feedback visual imediato e Spinner de carregamento no player**:
   - Adicionar um indicador visual de carregamento (**spinner** CSS puro, 100% offline/CSP-compliant) centralizado sobre o palco do vídeo (`#playerStage`) com rótulo acessível (`Carregando vídeo...`).
   - Exibir o spinner automaticamente ao abrir um vídeo (`loadstart`), durante buffering de rede (`waiting`), durante reposicionamento (`seeking`) e ao aguardar resposta de stream transcodificado, ocultando-o imediatamente quando a reprodução estiver pronta (`playing`, `canplay`, `seeked` ou em caso de `error`).
   - Atualizar imediatamente o marcador de tempo atual e a posição da barra de progresso no instante em que o usuário acionar `-10s`, `+10s`, atalhos `←`/`→` ou mover o slider.
2. **Otimização dos controles de avanço, retrocesso e seek**:
   - Aplicar **debounce** nos comandos repetidos de avanço/retrocesso (`-10s` / `+10s` / setas `←`/`→`) em modo transcodificado para acumular múltiplos cliques rápidos (ex.: 3 cliques em `+10s` = `+30s`) antes de reiniciar o stream no servidor, evitando múltiplos processos FFmpeg concorrentes.
   - Abortar/encerrar imediatamente conexões de streaming anteriores e otimizar o posicionamento no backend (`FfmpegService`) para reduzir a latência até o primeiro frame após o seek (utilizando reposicionamento rápido quando disponível sem decodificar todo o prefixo desde 0s).
   - Em vídeos diretos (`.mp4`/`.webm`), garantir que o seek nativo (`videoElement.currentTime`) respeite a duração total e atualize a tela sem recarregar o `src`.

### Out of Scope (Non-Goals)
- Pré-transcodificação em disco ou geração de segmentos HLS gravados nas pastas de mídia ou no servidor.
- Miniaturas de preview ao passar o mouse sobre a barra de progresso (storyboard/VTT thumbnails).

## Capabilities

### New Capabilities
- Nenhuma nova capacidade isolada é criada; os requisitos aprimoram o comportamento de streaming e interface do player.

### Modified Capabilities
- `video-streaming-player`: otimização de latência e debounce nos controles de avanço/retrocesso (`±10s` e barra de progresso) e exibição de spinner de carregamento durante abertura, buffering e seek.

## Impact

- **Backend PHP**:
  - `lib/Service/FfmpegService.php` e `lib/Controller/StreamController.php` (otimização de seek/reposicionamento em stream e encerramento limpo do processo FFmpeg quando o cliente aborta a conexão).
- **Frontend JS/CSS/Templates**:
  - `templates/main.php` (elemento `#playerLoadingSpinner` dentro de `#playerStage`).
  - `css/style.css` (animação CSS pura do spinner e centralização em modo janela e tela cheia).
  - `js/modules/player.js` e `js/main.js` (controle de eventos `loadstart`/`waiting`/`playing`/`canplay`/`seeking`/`seeked`/`error`, atualização otimista do tempo e debounce de seek em streams transcodificados).
  - `l10n/pt_BR.json` e `l10n/pt_BR.js` (tradução de `"Loading video..."`).
- **Testes Automatizados**:
  - Novos testes unitários PHPUnit (`MediaStreamAndFfmpegServiceTest`) e Vitest (`catalog_picker_player.test.js`) cobrindo spinner de carregamento, atualização imediata e debounce de avanço/retrocesso.
