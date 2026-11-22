# Spec Delta

## Purpose

Fornece navegação hierárquica, filtragem de arquivos de vídeo, busca textual, ordenação e miniaturas sobre as pastas configuradas do Nextcloud sem gravar nem alterar nenhum arquivo nas pastas de mídia.

## ADDED Requirements

### Requirement: Hierarchical folder and video listing
The system SHALL list only subfolders and recognized video files inside a selected configured root folder, showing readable name, file size, and last modification date while hiding non-video and hidden files by default.

#### Scenario: Listing a root folder containing mixed files
- **GIVEN** uma pasta configurada contém subpastas, arquivos de vídeo suportados (`.mp4`, `.webm`, `.mkv`, `.avi`, `.mov`, `.m4v`, `.ogv`, `.wmv`, `.flv`, `.ts`, `.rmvb`), documentos PDF e arquivos ocultos iniciados por `.`
- **WHEN** o usuário abre essa pasta no catálogo
- **THEN** o sistema exibe apenas as subpastas visíveis e os arquivos de vídeo reconhecidos, com nome, tamanho formatado e data de modificação, omitindo documentos não-vídeo e arquivos ocultos

#### Scenario: Opening an empty folder or library without configured roots
- **GIVEN** o usuário não possui nenhuma pasta configurada acessível ou abre uma subpasta sem vídeos nem subpastas
- **WHEN** o catálogo é carregado
- **THEN** a interface exibe um estado vazio informativo com ação direta para abrir o gerenciador de pastas, sem tela em branco nem erro técnico

### Requirement: Breadcrumb navigation inside configured roots
The system SHALL provide breadcrumb navigation reflecting the active root folder and every nested subfolder level, preventing navigation above the selected configured root.

#### Scenario: Navigating deep subfolders and returning via breadcrumbs
- **GIVEN** o usuário está visualizando a subpasta `Temporada 01` dentro da raiz configurada `Series`
- **WHEN** o usuário clica no item `Series` na trilha de navegação
- **THEN** o catálogo retorna diretamente ao topo da raiz `Series` sem permitir subir para pastas pai não configuradas do Nextcloud

### Requirement: Library-wide video search and sorting
The system SHALL support case-insensitive video filename search across all active configured folders and sorting of displayed folders and videos by name or modification date.

#### Scenario: Searching videos across multiple configured roots
- **GIVEN** o usuário possui vídeos distribuídos em diferentes pastas configuradas e subpastas
- **WHEN** o usuário digita um termo parcial no campo de busca
- **THEN** o sistema lista todos os vídeos cujo nome corresponde ao termo (ignorando maiúsculas/minúsculas), exibindo a pasta de origem de cada resultado e permitindo reproduzi-lo diretamente

#### Scenario: Search returns no matches
- **GIVEN** o usuário digita um termo de busca que não corresponde a nenhum vídeo nas pastas configuradas
- **WHEN** a busca é concluída
- **THEN** a interface exibe aviso claro de nenhum vídeo encontrado e disponibiliza um botão para limpar o filtro

#### Scenario: Changing sort order
- **GIVEN** o usuário está visualizando uma pasta ou resultados de busca com múltiplos vídeos
- **WHEN** o usuário altera o seletor de ordenação entre Nome (A–Z / Z–A) e Data de modificação (mais recentes / mais antigos)
- **THEN** a listagem é reordenada imediatamente e a preferência de ordenação é preservada para sessões futuras

### Requirement: Read-only video thumbnails with graceful fallback
The system SHALL display visual video thumbnails sourced from Nextcloud preview APIs or an isolated cache outside the media folders, falling back cleanly to a default video placeholder without blocking the catalog listing.

#### Scenario: Video thumbnail unavailable or unsupported format
- **GIVEN** uma pasta contém um vídeo corrompido ou cujo codec não permite geração de prévia visual
- **WHEN** o catálogo renderiza os cartões de vídeo
- **THEN** o cartão exibe uma miniatura padrão limpa, o carregamento da listagem não é bloqueado e nenhum arquivo temporário é gravado nas pastas de mídia

### Requirement: Strict read-only catalog boundary
The system MUST ensure that browsing, searching, sorting, or generating previews never creates, renames, moves, or alters any file or directory inside configured Nextcloud folders, and rejects any access attempt outside configured roots.

#### Scenario: Direct request targeting a file outside configured folders
- **GIVEN** existe um arquivo de vídeo `/Privado/segredo.mp4` na conta do usuário que não pertence a nenhuma pasta configurada na biblioteca do plugin
- **WHEN** uma requisição tenta listar ou consultar `/Privado` ou `/Privado/segredo.mp4` pela API do plugin
- **THEN** o sistema recusa o acesso com erro (`403 Forbidden` ou `404 Not Found`) mantendo o isolamento estrito às pastas configuradas
