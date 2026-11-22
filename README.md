# nextcloud-video-player

Plugin (app) para **Nextcloud 30–32** que traz a experiência de biblioteca e player de vídeo do [lan-video-player](https://github.com/castrocristiano/lan-video-player) diretamente para o Nextcloud, reproduzindo vídeos organizados em **pastas selecionadas já configuradas** (modelo híbrido: pastas globais configuradas pelo administrador + pastas pessoais escolhidas pelo usuário).

> **Documentação completa**: consulte a pasta [wiki/](wiki/Home.md) ou a Wiki do repositório no GitHub.
> **Especificações (SDD)**: este projeto utiliza **OpenSpec** em [openspec/](openspec/config.yaml).

---

## Principais Funcionalidades Planejadas

- **Configuração Híbrida de Pastas Selecionadas**:
  - **Admin**: pré-configura pastas globais/compartilhadas do Nextcloud (incluindo montagens de *External Storage*), respeitando rigorosamente as permissões nativas de leitura de cada usuário.
  - **Usuário**: seleciona e gerencia pastas adicionais da sua própria conta diretamente na interface do plugin.
- **Somente-Leitura Estrito & Segurança Nativa Nextcloud**:
  - Usa exclusivamente as APIs de arquivos virtuais do Nextcloud (`OCP\Files\IRootFolder`), nunca gravando nem modificando arquivos nas pastas de vídeo.
  - 100% offline/auto-hospedado sem CDNs externos (compatível com a CSP rigorosa do Nextcloud).
- **Player Completo (Baseado no `lan-video-player`)**:
  - Streaming progressivo com HTTP Range (`206 Partial Content`) e suporte opcional a transcodificação/miniaturas via `ffmpeg`.
  - Tela cheia estilo YouTube com auto-ocultamento de controles, velocidade (`0.5x`–`2.0x`), atalhos de teclado/TV, retomada automática de reprodução, próximo vídeo automático com contagem regressiva, legendas locais (`.vtt` e conversão em memória de `.srt`) e seleção de faixa de áudio.
  - Navegação hierárquica com *breadcrumbs*, busca por nome em todas as pastas configuradas e ordenação por nome ou data de modificação.
- **Frontend Leve e Nativo ao Tema Nextcloud**:
  - Vanilla JS modular + CSS integrado às variáveis de tema claro/escuro do Nextcloud.

---

## Arquitetura & Estrutura do Repositório

```text
nextcloud-video-player/
├── .agents/skills/          # Skills do OpenSpec para Antigravity
├── .github/
│   ├── CODEOWNERS
│   └── workflows/
│       ├── ci.yml           # CI/CD: OpenSpec, PHP/JS Lint & Tests, SonarQube, Auto-PR, Release
│       └── deploy-wiki.yml  # Sincronização automática da Wiki
├── appinfo/                 # Metadados do app Nextcloud (info.xml, routes.php)
├── css/                     # Estilos adaptados ao tema claro/escuro do Nextcloud
├── js/                      # Frontend modular Vanilla JS do catálogo e player
├── lib/                     # Backend PHP 8.2+ (AppInfo, Controllers, Services)
├── openspec/                # Especificações Spec-Driven Development (OpenSpec)
│   ├── config.yaml
│   ├── specs/
│   └── changes/
├── templates/               # Templates PHP do Nextcloud
├── tests/                   # Testes automatizados (PHPUnit + Vitest)
├── wiki/                    # Documentação da Wiki do GitHub
└── sonar-project.properties # Configuração do SonarQube Quality Gate
```

---

## Metodologia Spec-Driven (OpenSpec)

Este projeto adota o **OpenSpec** para todo o ciclo de especificação e entrega:

```bash
# Listar mudanças ativas e especificações
openspec list
openspec list --specs

# Validar rigorosamente todas as specs e proposals
openspec validate --all --strict --no-interactive

# Verificar progresso dos artefatos de uma mudança
openspec status --change <nome-da-mudanca>
```

---

## Gitflow e CI/CD

Seguindo o mesmo padrão do projeto `contas`:

- `feature/*` e `phase/*` → CI + SonarQube → **Auto-PR para `develop`**
- `develop` → CI + SonarQube → **Auto-PR para `main`**
- `hotfix/*` → CI + SonarQube → **Auto-PR direto para `main`**
- Merge na `main` → **GitHub Release automática (`v*`)** com pacote `nextcloud_video_player-v*.tar.gz` pronto para instalação em `apps/`.

---

## Licença

Este projeto é software livre licenciado sob a **GNU Affero General Public License v3.0 (GNU AGPL v3.0)**. Consulte o arquivo [LICENSE](LICENSE).
