# Design: Links Públicos de Compartilhamento

## Resumo

Após uma compra (pay-per-game ou assinatura mensal), o comprador pode gerar uma URL pública e irrestrita para compartilhar um clipe ou o jogo inteiro com qualquer pessoa — sem exigir login ou pagamento de quem recebe.

---

## Understanding

- **O que:** URL pública não-autenticada para um clipe ou jogo completo adquirido
- **Quem gera:** comprador avulso (pay-per-game) ou mensalista do grupo
- **O que o destinatário pode fazer:** assistir, baixar e repassar o link
- **Escopo:** proporcional ao que foi adquirido — clipe avulso → link do clipe; jogo completo ou assinatura → link do jogo inteiro
- **Expira:** 24h após `slot_date` do jogo (alinhado ao R2 lifecycle)
- **Aparece em:** tela de confirmação de pagamento + player/página do jogo

---

## Banco de dados

```sql
CREATE TABLE share_links (
  token      CHAR(32) PRIMARY KEY,
  game_id    INT NOT NULL,
  clip_id    INT NULL,          -- NULL = jogo inteiro
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_expires (expires_at)
);
```

- `token` = `bin2hex(random_bytes(16))` — 32 chars hex, criptograficamente seguro
- `expires_at` = `slot_date + 24h`
- Geração idempotente por `(game_id, clip_id)`: reutiliza token existente ativo

---

## Endpoints

### Geração (autenticado)

```
POST /api/share
Authorization: Bearer <video_token>
Body: { "game_id": 123, "clip_id": 456 }   // clip_id omitido = jogo inteiro
```

- Valida `video_token` e verifica que ele cobre o escopo solicitado
- Cria ou reutiliza row em `share_links`
- Retorna: `{ "token": "abc…", "url": "https://…/share/abc…", "expires_at": "…" }`

### Acesso público

```
GET /api/share/{token}
```

- Busca token com `expires_at > NOW()`
- `clip_id` preenchido → retorna dados do clipe com `stream_url` (presigned R2)
- `clip_id NULL` → retorna metadados do jogo + lista de clipes com `stream_url`
- `404` se não existe ou expirado

---

## Frontend

Nova rota pública: `/share/:token` → `ShareView.vue`

- Sem verificação de `localStorage`
- `type === 'clip'`: renderiza player (igual ao PlayerView, sem botões de compra)
- `type === 'game'`: renderiza lista de clipes com player embutido
- Exibe banner de expiração e botão de download
- Botão "Compartilhar" copia `window.location.href`
- `404` da API → tela "este link expirou ou é inválido"

**Onde o link aparece para quem comprou:**
1. Tela de confirmação de pagamento — gerado automaticamente após `status: approved`
2. PlayerView / GameView — botão "Compartilhar" on demand para quem tem token válido

---

## Edge Cases

| Situação | Comportamento |
|---|---|
| Token expirado | 404, frontend mostra "link expirado" |
| Vídeo sumiu do R2 | Presigned URL retorna 403/404 no player |
| Mesmo comprador gera link duas vezes | Reutiliza token existente |
| Assinante fora do período | video_token não autoriza → POST /api/share retorna 403 |
| Sem Authorization | 401 |
| clip_id fora do escopo do token | 403 |

---

## Decision Log

| Decisão | Alternativas | Motivo |
|---|---|---|
| Nova tabela `share_links` | Coluna em `payments`; HMAC | Cobre assinantes e compradores com uma lógica única |
| Token = `random_bytes(16)` hex | UUID, JWT | Opaco, não adivinhável, sem chave secreta para validar |
| Idempotência por `(game_id, clip_id)` | Novo token a cada chamada | Evita proliferação; link sempre o mesmo |
| `expires_at` = 24h após `slot_date` | 48h; nunca expira | Alinha com R2 lifecycle |
| `ShareView.vue` separado | Flag em PlayerView | Rota pública sem auth; evita lógica condicional |
| `POST /api/share` retorna URL pronta | Só token | Frontend desacoplado da montagem da URL |
