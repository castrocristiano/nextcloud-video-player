# Proposal

## Why

Ao reabrir o aplicativo em uma nova sessão ou em outro dispositivo, o usuário atualmente retorna sempre à raiz da primeira pasta configurada e precisa navegar manualmente até a subpasta ou episódio que estava assistindo. Além disso, o tamanho da fonte das legendas é fixo via CSS, dificultando a leitura em telas grandes (TVs/monitores 4K) ou dispositivos móveis.

Esta mudança melhora a continuidade de uso e a acessibilidade visual do player sem alterar o comportamento estritamente somente-leitura sobre as pastas de mídia nem violar as políticas de CSP/operação 100% offline.

## What Changes

1. **Retomada automática da última pasta e acesso ao último vídeo assistido**:
   - Persistir por usuário no banco de dados (preferências do usuário no Nextcloud) a última raiz (`lastRootId`) e o último caminho relativo de pasta (`lastFolderPath`) acessados com sucesso no catálogo.
   - Ao abrir o aplicativo, restaurar automaticamente a última pasta visitada caso ela ainda exista e esteja acessível pelas permissões vigentes do usuário; caso contrário, fazer fallback seguro para a primeira raiz configurada.
   - Exibir na barra superior/catálogo (ou banner de "Continuar assistindo") um atalho direto para retomar o último vídeo assistido recentemente (derivado do registro mais recente em `ncvp_playback_progress` dentro das pastas acessíveis ao usuário), permitindo reabri-lo diretamente no ponto salvo.
2. **Controle de tamanho da fonte das legendas**:
   - Adicionar controles dedicados (`A-` / `A+` ou botões de diminuir/aumentar com indicador percentual/tamanho) na barra de ferramentas do player ao lado do seletor de legendas.
   - Aplicar dinamicamente o tamanho de fonte escolhido no `#subtitleOverlay` tanto em modo janela quanto em modo tela cheia, respeitando limites seguros de legibilidade (ex.: `75%` a `200%` ou `0.75` a `2.0`).
   - Persistir a preferência de tamanho da fonte (`subtitleScale`) por usuário na tabela `ncvp_user_preferences` (com cache local em `localStorage`), garantindo sincronização entre dispositivos.

### Out of Scope (Non-Goals)
- Histórico completo de múltiplos vídeos assistidos ou filas/playlists personalizadas.
- Alteração de cor, família tipográfica ou contorno das legendas além do tamanho da fonte.
- Gravação de arquivos `.vtt`, `.srt` ou metadados de estado dentro das pastas de mídia do Nextcloud.

## Capabilities

### New Capabilities
- Nenhuma nova capacidade isolada é criada; os requisitos estendem capacidades existentes do catálogo, player e persistência.

### Modified Capabilities
- `video-catalog`: restauração automática da última pasta acessada ao iniciar o catálogo (com fallback seguro se removida/inacessível) e exposição de atalho "Continuar assistindo" para o último vídeo assistido.
- `video-streaming-player`: controles de aumentar e diminuir o tamanho da fonte das legendas na interface do player com atualização em tempo real no overlay (normal e tela cheia) e persistência da preferência.
- `session-and-persistence`: extensão da tabela `ncvp_user_preferences` via nova migration do Nextcloud para armazenar `last_root_id`, `last_folder_path` e `subtitle_scale`, mantendo isolamento por usuário.

## Impact

- **Backend PHP**:
  - Nova migration (`lib/Migration/Version0002Date...php`) adicionando colunas `last_root_id`, `last_folder_path` e `subtitle_scale` em `ncvp_user_preferences`.
  - Atualização de `UserPreferenceEntity`, `PreferenceService` e `PreferenceController` (`GET/PUT /api/v1/preferences`) para validar e persistir os novos campos.
  - Consulta ao último vídeo assistido acessível (`ProgressService` / `ConfigController::listRoots` ou `CatalogController`) respeitando `FolderConfigService::getEffectiveRoots()`.
- **Frontend JS/CSS/Templates**:
  - `templates/main.php`, `css/style.css`, `js/modules/state.js`, `js/modules/player.js`, `js/modules/catalog.js` e `js/main.js`.
  - Novas strings traduzíveis em `l10n/pt_BR.json` e `l10n/pt_BR.js`.
- **Testes Automatizados**:
  - Novos testes unitários PHPUnit (migration, `PreferenceService`, `ProgressService`) e Vitest (restauração de pasta/último vídeo e controle de tamanho de legenda).
