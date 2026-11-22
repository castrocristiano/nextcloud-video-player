# Spec Delta — video-catalog

## ADDED Requirements

### Requirement: Restore last accessed folder on startup
The system SHALL restore the last configured root folder and relative subfolder path accessed by the authenticated user when opening the application, falling back safely to the first accessible configured root if the saved location no longer exists or is no longer permitted.

#### Scenario: Opening the app restores the last visited subfolder
- **GIVEN** o usuário autenticou-se e navegou anteriormente até a subpasta `/Seriados/Dragon Ball Z` dentro da raiz configurada `Videos`
- **WHEN** o usuário fecha o navegador e abre o aplicativo novamente (no mesmo dispositivo ou em outro)
- **THEN** o catálogo carrega diretamente a raiz `Videos` na subpasta `/Seriados/Dragon Ball Z`, exibindo a trilha de navegação e os vídeos correspondentes

#### Scenario: Fallback when the last accessed folder was removed or revoked
- **GIVEN** a preferência salva aponta para uma subpasta que foi excluída no armazenamento do Nextcloud ou para uma raiz cujo acesso foi revogado
- **WHEN** o usuário abre o aplicativo
- **THEN** o sistema descarta o destino inacessível sem travar a interface e carrega automaticamente o topo (`/`) da primeira raiz configurada acessível

### Requirement: Quick resume of the last watched video
The system SHALL expose a "Continue watching" entry point in the catalog for the most recently watched unfinished video belonging to an accessible configured root, allowing one-click playback resumption from the saved position.

#### Scenario: Displaying and resuming the most recent unfinished video
- **GIVEN** o usuário assistiu parcialmente ao vídeo `/Filmes/A Onda/A Onda.avi` (em `00:25:10` de `01:42:24`) em uma pasta configurada acessível
- **WHEN** o usuário abre a tela principal do catálogo
- **THEN** a interface exibe um card/banner de acesso rápido indicando o nome do vídeo, o tempo salvo e um botão para retomar a reprodução diretamente a partir de `00:25:10`

#### Scenario: Last watched video completed or no longer accessible
- **GIVEN** o último vídeo registrado excedeu 95% de conclusão ou pertence a uma pasta removida/não autorizada
- **WHEN** o usuário abre o catálogo
- **THEN** o atalho de "Continuar assistindo" não exibe esse item inválido e nenhuma informação inacessível é vazada
