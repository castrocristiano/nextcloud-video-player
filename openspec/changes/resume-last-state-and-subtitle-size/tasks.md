# Tasks

## 1. Migração de Banco de Dados e Persistência de Preferências

- [ ] 1.1 Criar a migration incremental `lib/Migration/Version0002Date20261122000000.php` adicionando as colunas `last_root_id`, `last_folder_path` e `subtitle_scale` (padrão `1.0`) na tabela `ncvp_user_preferences`, verificando `hasColumn()` antes de criar, e validar com teste unitário PHPUnit de migration.
- [ ] 1.2 Atualizar `lib/Db/UserPreferenceEntity.php`, `lib/Service/PreferenceService.php` e `lib/Controller/PreferenceController.php` para persistir, normalizar/validar (`subtitleScale` entre `0.75` e `2.0`, `lastFolderPath` sem path traversal) e retornar os novos campos, garantindo cobertura nos testes PHPUnit de `ProgressAndPreferenceServiceTest`.

## 2. Último Vídeo Assistido no Backend

- [ ] 2.1 Adicionar consulta ao último vídeo assistido não concluído (`completed = false`, `positionSeconds > 3`) em `lib/Service/ProgressService.php` e `lib/Db/PlaybackProgressMapper.php`, validando que o `rootKey` e o arquivo ainda existem/estão acessíveis via `SafeNodeResolver` e expondo `lastWatched` no retorno de `ConfigController::listRoots()`, com testes unitários PHPUnit correspondentes.

## 3. Restauração da Última Pasta e "Continuar Assistindo" no Frontend

- [ ] 3.1 Atualizar `js/modules/state.js` e `js/main.js` para restaurar `lastRootId` e `lastFolderPath` ao carregar o catálogo (com fallback silencioso para `/` da primeira raiz se a pasta tiver sido removida/revogada) e salvar a localização atual ao navegar entre pastas, cobrindo com teste Vitest.
- [ ] 3.2 Adicionar o card/banner "Continuar assistindo" em `templates/main.php`, `css/style.css`, `js/modules/catalog.js` e `js/main.js` para retomar com 1 clique o último vídeo assistido (`lastWatched`) no ponto salvo, adicionando as traduções em `l10n/pt_BR.json` e `l10n/pt_BR.js` e teste Vitest.

## 4. Controle de Tamanho da Fonte das Legendas

- [ ] 4.1 Adicionar botões `A-` / `A+` e indicador percentual na barra de ferramentas do player em `templates/main.php` e variável CSS `--ncvp-subtitle-scale` em `css/style.css` (aplicada a `.ncvp-subtitle-line` em janela e tela cheia).
- [ ] 4.2 Implementar ajuste, limites (`0.75` a `2.0`, passo `0.15` ou `0.25`) e persistência de `subtitleScale` em `js/modules/player.js` e `js/main.js`, com traduções em `l10n/pt_BR.*` e testes unitários Vitest cobrindo limites e atualização do DOM.

## 5. Validação Final e Qualidade

- [ ] 5.1 Executar `openspec validate --all --strict --no-interactive`, `vendor/bin/phpstan analyse`, `vendor/bin/phpunit`, `npm run lint` e `npm run test:coverage` garantindo 0 erros e cobertura adequada para o Quality Gate do SonarQube.
