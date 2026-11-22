# Tasks

## 1. Spinner de Carregamento no Player (UI/CSS/JS)

- [x] 1.1 Adicionar o markup `#playerLoadingSpinner` dentro de `#playerStage` em `templates/main.php` (com texto acessível traduzível em `l10n/pt_BR.json` e `l10n/pt_BR.js`) e os estilos/animação CSS pura em `css/style.css` funcionando em janela e tela cheia.
- [x] 1.2 Conectar os eventos `loadstart`, `waiting`, `seeking`, `canplay`, `playing`, `seeked` e `error` em `js/modules/player.js` e `js/main.js` para exibir e ocultar o spinner de forma determinística, cobrindo com testes unitários Vitest.

## 2. Debounce e Atualização Imediata nos Controles de Avanço/Retrocesso

- [x] 2.1 Atualizar `seekRelative()` e `seekToSeconds()` em `js/modules/player.js` e `js/main.js` para atualizar `#currentTimeLabel` e `#seekSlider` imediatamente, acumular cliques rápidos (`-10s` / `+10s` / `←` / `→`) com debounce de `300ms` em modo transcodificado e cancelar timers pendentes em `closePlayer()` / troca de vídeo, validando com testes Vitest.

## 3. Otimização de Seek no Streaming FFmpeg e Cache de Metadados (Backend PHP)

- [x] 3.1 Otimizar `lib/Service/FfmpegService.php` e `lib/Controller/StreamController.php` para reduzir o tempo até o primeiro frame após `start > 0` (`-ss` antes de `-i`), garantir encerramento imediato do processo `ffmpeg` quando o cliente fechar ou trocar a conexão, e adicionar cache de metadados (`ffprobe` e listagem do catálogo via `OCP\ICacheFactory`), validando com testes PHPUnit.

## 4. Validação Final e Deploy

- [x] 4.1 Executar `openspec validate --all --strict --no-interactive`, `vendor/bin/phpstan analyse`, `vendor/bin/phpunit`, `npm run lint` e `npm run test:coverage` e testar em `dc1` com vídeo `.avi` e `.mp4`.
