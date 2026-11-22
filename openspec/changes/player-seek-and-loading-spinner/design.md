# Design

## Context

Veja `proposal.md` para a motivação. Atualmente:
- Em `lib/Service/FfmpegService.php`, `buildTranscodeCommand('pipe:0', $startSeconds)` passa `-i pipe:0` **antes** de `-ss $safeStart`. No FFmpeg, `-ss` após `-i` é *output seeking*: ele lê e decodifica o stream SFTP inteiro desde o segundo `0` até atingir `$safeStart`, causando demora de dezenas de segundos em avanços longos.
- Em `js/modules/player.js`, `seekRelative()` / `seekToSeconds()` disparam `switchToTranscodedStream()` imediatamente a cada clique sem debounce e sem atualização visual prévia de `#currentTimeLabel` / `#seekSlider`.

## Goals / Non-Goals

**Goals:**
- Reduzir drasticamente o tempo de resposta de avanço/retrocesso em streams transcodificados e diretos.
- Evitar tempestade de requisições e processos FFmpeg órfãos quando o usuário aperta `+10s`/`-10s` várias vezes seguidas.
- Fornecer feedback visual imediato (spinner + atualização instantânea do tempo alvo) durante qualquer carregamento.

**Non-Goals:**
- Cache persistente de segmentos transcodificados em disco.

## Decisions

1. **Posicionamento rápido no stream de entrada (`Seek` proporcional + `-ss` antes de `-i` ou `fseek`)**:
   - *Decisão*: Quando `startSeconds > 0` em um arquivo seekável (`$file->getSize() > 0` e duração conhecida via `probeStreamHeader`), calcular ou posicionar o ponteiro do stream SFTP com margem de segurança antes de alimentar o FFmpeg (ou usar `-ss` de entrada quando o container suportar, mantendo `transcodeBaseOffset` sincronizado com o timestamp real). Para formatos onde o container exige cabeçalho no início (ex.: AVI/MP4/MKV), investigar/aplicar busca rápida sem decodificar frames descartados (`-skip_frame` / input seeking via HTTP/SFTP ou `ffmpeg -ss` otimizado).
   - *Alternativa considerada*: Manter `-ss` após `-i pipe:0` sem otimização. Rejeitada porque o tempo de espera cresce linearmente com a posição no filme.

2. **Debounce de Seek no Frontend (`300ms`)**:
   - *Decisão*: Manter um estado `pendingSeekTarget` durante `300ms` para chamadas consecutivas a `seekRelative()` / `seekToSeconds()` quando `state.isTranscodedStream === true`, atualizando `#currentTimeLabel`, `#seekSlider` e exibindo o spinner imediatamente a cada clique, e disparando `switchToTranscodedStream()` apenas uma vez ao estabilizar.
   - *Alternativa considerada*: Disparar a requisição imediatamente e cancelar via `AbortController`. Como `<video src="...">` não usa `fetch`, trocar `src` rapidamente ainda abre e fecha conexões HTTP no Apache/PHP; o debounce evita abrir conexões descartáveis.

3. **Spinner CSS puro 100% offline**:
   - *Decisão*: Elemento `<div id="playerLoadingSpinner" class="ncvp-player-spinner hidden">` dentro de `#playerStage` com anel animado por `@keyframes` CSS, sem imagens externas ou SVGs remotos, respeitando `default-src 'self'` do Nextcloud.

## Risks / Trade-offs

- **[Risco] Ao fazer seek por byte offset em pipe, alguns containers (AVI/MKV) precisam do cabeçalho inicial para decodificar** → *Mitigação*: Validar no container real com `A Onda.avi` e `Dragon Ball Z` qual combinação (`-ss` antes de `-i` vs. flags de decodificação rápida/discard) preserva o cabeçalho e evita erro de decodificador, mantendo fallback seguro.
- **[Risco] Spinner ficar preso visível se o navegador pular um evento** → *Mitigação*: Ocultar o spinner em todos os eventos terminais (`playing`, `canplay`, `seeked`, `pause` após erro, `error`, `closePlayer`).
