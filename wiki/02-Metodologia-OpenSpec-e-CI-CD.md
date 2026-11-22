# 02 — Metodologia OpenSpec e CI/CD

## Fluxo Spec-Driven Development (OpenSpec)

Diferente dos projetos anteriores baseados em Spec Kit, o **nextcloud-video-player** adota o **OpenSpec** (`openspec/`):

1. **Configuração (`openspec/config.yaml`)**: Define o schema `spec-driven`, o contexto técnico do plugin Nextcloud e as regras de idioma e qualidade.
2. **Proposta de Mudança (`openspec/changes/<mudanca>/`)**:
   - `proposal.md` — motivação, escopo e capacidades impactadas.
   - `specs/<capacidade>/spec.md` — requisitos normativos e cenários verificáveis (*delta specs*).
   - `design.md` — arquitetura técnica, contratos HTTP e decisões de projeto.
   - `tasks.md` — lista granular de tarefas de implementação e testes.
3. **Validação Contínua**: `openspec validate --all --strict --no-interactive` é executado localmente e na esteira de CI.
4. **Arquivamento**: Ao finalizar uma mudança, os *delta specs* são sincronizados para `openspec/specs/` e a mudança vai para `openspec/changes/archive/`.

## Esteira de CI/CD (Padrão `contas`)

O workflow `.github/workflows/ci.yml` reproduz o padrão Gitflow + SonarQube do projeto `contas`:

1. **`build-and-test`**:
   - Validação estrita do OpenSpec (`openspec validate --all --strict`).
   - Validação XML de `appinfo/info.xml`, lint PHP (`php -l`), PHPStan e PHPUnit com cobertura Clover (`build/logs/clover.xml`).
   - Lint JS (ESLint) e testes unitários Vitest com cobertura LCOV (`coverage/lcov.info`).
2. **`sonarqube`**:
   - Container `sonarqube:10.5-community` + `sonar-scanner-cli` validando o **Quality Gate**.
3. **`auto-pr-to-develop` / `auto-pr-to-main`**:
   - Abertura automática de PRs de `feature/*` e `phase/*` → `develop` → `main` (e `hotfix/*` direto → `main`).
4. **`release-on-main`**:
   - Análise SemVer de *breaking changes*, geração do pacote `folder_video_player-v*.tar.gz` e publicação da **GitHub Release**.
5. **`.github/workflows/deploy-wiki.yml`**:
   - Sincroniza `wiki/` com a GitHub Wiki a cada atualização da `main`.
