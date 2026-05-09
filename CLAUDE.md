# replay-site

Site para download de replays de futebol amador gerados pelo **replay-agent** (edge device Go).

## O que este projeto é

Backend PHP + Frontend Vue que recebe metadados de vídeo do edge device, armazena no MySQL e expõe uma interface para os jogadores baixarem os clips.

Os vídeos ficam armazenados no **Cloudflare R2** por 24h (lifecycle rule configurada no bucket) e são servidos via **presigned URL** — o PHP não intermedia o binário.

## Arquitetura

```
Edge Device (Go — projeto replay-saas)
    │
    │ POST /api/videos  (X-Api-Key)
    │ após upload direto ao R2
    ▼
PHP Backend (Hostinger)  ◄──────────────  Vue Frontend (Vercel)
    │  MySQL: tabela videos              GET /api/videos
    │                                    GET /api/videos/{id}/download → 302
    ▼
Cloudflare R2  (lifecycle: apaga após 24h)
    └── videos/{videoID}/replay_YYYYMMDD_HHMMSS_cam1.mp4
```

## Stack

| Camada | Tecnologia |
|---|---|
| Frontend | Vue 3 + Vite, deploy na Vercel |
| Backend | PHP 8.x na Hostinger (business) |
| Banco | MySQL na Hostinger |
| Storage | Cloudflare R2 (S3-compatible) |

## Schema MySQL

```sql
CREATE TABLE videos (
    id           CHAR(32) PRIMARY KEY,
    camera_id    VARCHAR(64) NOT NULL,
    r2_key       VARCHAR(255) NOT NULL,
    duration_s   SMALLINT UNSIGNED NOT NULL,
    size_bytes   INT UNSIGNED NOT NULL,
    triggered_at DATETIME NOT NULL,
    expires_at   DATETIME NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at),
    INDEX idx_camera  (camera_id, triggered_at)
);
```

`expires_at = triggered_at + 24h` — calculado pelo PHP no INSERT.
`id` é um hex de 32 chars gerado pelo edge device via `crypto/rand`.

## Endpoints PHP

### `POST /api/videos`
Chamado pelo edge device após upload ao R2.

**Auth:** header `X-Api-Key: <segredo>` — recusa com 401 se ausente ou incorreto.

**Body (JSON):**
```json
{
  "id": "a3f7...",
  "camera_id": "cam1",
  "r2_key": "videos/a3f7.../replay_20260508_143022_cam1.mp4",
  "duration_s": 25,
  "size_bytes": 9437184,
  "triggered_at": "2026-05-08T14:30:22Z"
}
```

**Resposta:** `201 Created` em caso de sucesso.

---

### `GET /api/videos`
Lista vídeos ativos (não expirados).

**Resposta:**
```json
[
  {
    "id": "a3f7...",
    "camera_id": "cam1",
    "duration_s": 25,
    "size_bytes": 9437184,
    "triggered_at": "2026-05-08T14:30:22Z",
    "expires_at": "2026-05-09T14:30:22Z"
  }
]
```

Não retorna `r2_key` — o frontend obtém o link de download via endpoint dedicado.

---

### `GET /api/videos/{id}/download`
Gera uma presigned URL do R2 com 1h de expiração e redireciona.

**Resposta:** `302 Location: <presigned-url>` ou `404` se não encontrado / expirado.

O PHP usa o AWS SDK for PHP (`aws/aws-sdk-php`) apontando para o endpoint R2:
`https://{ACCOUNT_ID}.r2.cloudflarestorage.com`

## Variáveis de ambiente PHP

```ini
DB_HOST=
DB_NAME=
DB_USER=
DB_PASS=

R2_ACCOUNT_ID=
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=replays

API_KEY=          # mesmo valor de REPLAY_BACKEND_API_KEY no edge device
```

## Contexto de negócio

- Clips de futebol amador gerados sob demanda por botão físico no campo
- Duração típica: até 30s, ~10 MB por clip
- Volume: ~25-30 clips/hora de jogo
- Acesso: modelo ainda indefinido (público com link vs. pago) — o endpoint de download
  já usa presigned URL, então adicionar auth depois não quebra a arquitetura
- Storage: Cloudflare R2, egress gratuito — não cobra por downloads

## O que já está pronto (no replay-saas)

- Edge device faz `PUT` direto no R2 via `upload.Client` (aws-sdk-go-v2)
- Após upload, faz `POST /api/videos` com os metadados e o header `X-Api-Key`
- R2 lifecycle rule de 24h deve ser configurada manualmente no dashboard Cloudflare

## O que precisa ser construído aqui

1. Estrutura do projeto PHP (router, env loader, DB connection)
2. Migration da tabela `videos`
3. `POST /api/videos` — valida API key, insere no banco
4. `GET /api/videos` — lista ativos
5. `GET /api/videos/{id}/download` — gera presigned URL e redireciona
6. Vue 3: lista de vídeos + botão de download
7. Deploy: PHP na Hostinger, Vue na Vercel
