# Diretrizes do Projeto Nextcloud Video Player (AGENTS.md)

## Idioma e Convenções de Linguagem

### 1. Documentação, OpenSpec e Interação (PT-BR)
- **Comunicação e Interação**: Todas as respostas ao usuário, perguntas, explicações e relatórios DEVEM ser fornecidos em **Português do Brasil (PT-BR)**.
- **Artefatos do OpenSpec**: Todos os arquivos em `openspec/` (`proposal.md`, `specs/*/spec.md`, `design.md`, `tasks.md`), bem como `README.md`, `SECURITY.md` e páginas em `wiki/`, DEVEM ser redigidos em **Português do Brasil (PT-BR)** (mantendo apenas os cabeçalhos estruturais e palavras-chave normativas `SHALL`/`MUST` exigidos pela validação do OpenSpec).
- **Mensagens de Git**: Seguem Conventional Commits com descrição em português (ex.: `feat(player): adicionar suporte a legendas locais vtt e srt`).

### 2. Código-Fonte e Testes (EN-US)
- **Código-Fonte em Inglês**: Todo o código-fonte PHP, JavaScript e CSS DEVE ser escrito em **Inglês (EN-US)**.
- **Identificadores**: Namespaces (`OCA\FolderVideoPlayer\...`), classes, métodos, funções, variáveis, rotas, chaves de configuração e atributos CSS/DOM DEVEM ser em inglês (ex.: `FolderConfigService`, `VideoCatalogService`, `StreamController`, `listVideos`, `resolveSafeNode`).
- **Docblocks e Comentários**: Devem ser redigidos em inglês no código-fonte.
- **Testes**: Arquivos e funções de teste (PHPUnit e Vitest) devem seguir nomenclatura em inglês (ex.: `testStreamReturnsPartialContentForValidRange()`, `shouldResumePlaybackFromSavedPosition()`).

## Fluxo Spec-Driven com OpenSpec
Este repositório utiliza **OpenSpec** (`openspec/`) com skills configuradas para o Antigravity em `.agents/skills/`:
1. **Propor mudança**: `/openspec-propose` — gera `proposal.md`, delta `specs/`, `design.md` e `tasks.md` sem editar código de produção.
2. **Validar specs**: `openspec validate --all --strict --no-interactive`.
3. **Implementar mudança**: `/openspec-apply-change` — executa as tarefas aprovadas em `tasks.md`.
4. **Arquivar mudança**: `/openspec-archive-change` — consolida os delta specs em `openspec/specs/` e arquiva a mudança concluída.

## Gitflow e Qualidade (Padrão `contas`)
- Branches de trabalho: `feature/*` ou `phase/*` criadas a partir de `develop`; `hotfix/*` criadas a partir de `main`.
- Todo push dispara a esteira `.github/workflows/ci.yml`: validação OpenSpec, lint/análise estática (PHP + JS), testes unitários com cobertura, Quality Gate no SonarQube, abertura automática de PR (`feature/*` → `develop` → `main`) e geração automática de GitHub Release com pacote `.tar.gz` ao fazer merge na `main`.
- **Somente-Leitura Estrito**: Nenhuma funcionalidade pode criar, alterar ou excluir arquivos nas pastas de mídia configuradas.
