# Design: Self-Service Auth e Assinatura

## Resumo

Área self-service para que times se cadastrem, escolham um slot e assinem mensalmente — removendo do dono do campo o trabalho manual de criar usuários e gerar links.

## Entendimento

**O que é:**
- Cadastro, login e recuperação de senha para capitães e jogadores
- Capitão cria conta, escolhe slot disponível, paga via Mercado Pago (recorrência automática)
- Capitão compartilha acesso via link público (sem conta) ou convite (jogador cria conta)
- Assinatura vencida/falha → grupo inteiro perde acesso imediatamente

**Não-goals:**
- Período de graça após falha de pagamento
- Jogador individual assumir assinatura do capitão
- Outros gateways além do Mercado Pago

## Assumptions

- O admin do campo gerencia slots e vê assinaturas no painel admin existente
- Um capitão = um slot (uma assinatura por conta)
- Email via Resend (SMTP/API)
- Preço único global (mesmo valor para todos os slots)
- Domínio com SPF/DKIM ou uso do domínio Resend inicialmente
- Sandbox MP durante desenvolvimento

## Schema do Banco

### Novas tabelas

```sql
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_sessions (
    token         CHAR(64) PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    expires_at    DATETIME NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE password_reset_tokens (
    token      CHAR(64) PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE user_group_memberships (
    user_id    INT UNSIGNED NOT NULL,
    group_id   INT UNSIGNED NOT NULL,
    role       ENUM('captain','player') NOT NULL DEFAULT 'player',
    joined_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, group_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (group_id) REFERENCES `groups`(id)
);

CREATE TABLE invite_tokens (
    token      CHAR(32) PRIMARY KEY,
    group_id   INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME,
    FOREIGN KEY (group_id) REFERENCES `groups`(id)
);
```

### Modificações em tabelas existentes

```sql
ALTER TABLE `groups`
    ADD COLUMN captain_user_id     INT UNSIGNED,
    ADD COLUMN mp_subscription_id  VARCHAR(100),
    ADD COLUMN subscription_status ENUM('pending','active','inactive') NOT NULL DEFAULT 'pending',
    ADD FOREIGN KEY (captain_user_id) REFERENCES users(id);
```

## Endpoints Backend (PHP)

### Auth de usuário
```
POST /api/auth/register          → cria conta (name, email, password)
POST /api/auth/login             → login email/senha, retorna user_session token
POST /api/auth/logout            → invalida user_session
POST /api/auth/forgot-password   → gera password_reset_token, envia email
POST /api/auth/reset-password    → valida token, atualiza senha, marca used_at
```

### Slots e assinatura (capitão)
```
GET  /api/slots/available        → lista slots sem captain_user_id
POST /api/subscriptions          → cria grupo + inicia plano MP, retorna checkout_url
```

### Gestão do grupo (capitão autenticado)
```
GET  /api/group/invite-link      → gera/retorna invite_token do grupo do capitão
GET  /api/group/members          → lista membros do grupo
```

### Convite para jogador
```
GET  /api/invites/{token}        → valida token, retorna nome do grupo
POST /api/invites/{token}/join   → jogador cria conta e entra no grupo
```

### Vídeos (extensão do existente)
```
GET  /api/group/videos           → aceita user_session_token além do group_token atual
```

### Webhook Mercado Pago
```
POST /api/webhooks/mercadopago   → atualiza subscription_status do grupo
```

## Fluxos Frontend (Vue)

### Novas views
```
/cadastro          → RegisterView.vue
/redefinir-senha   → ResetPasswordView.vue  (?token=)
/assinar           → SubscribeView.vue
/grupo             → GroupDashboardView.vue
/convite/:token    → InviteView.vue
```

### Fluxo capitão
```
/cadastro → /login → /assinar → (MP checkout externo) → /grupo
```

### Fluxo jogador convidado
```
/convite/:token → cria conta → /grupo/videos
```

### Fluxo link público
```
link group_token → /grupo/videos (sem conta, igual hoje)
```

## Integração Mercado Pago

- Modelo: Preapproval Plans (plano único global com preço fixo)
- Capitão assina o plano → recebe `checkout_url` → paga no MP → webhook confirma
- Webhook é a fonte de verdade (não o redirect)

| Evento MP | Ação |
|---|---|
| `payment` aprovado | `subscription_status = 'active'` |
| `payment` falhou | `subscription_status = 'inactive'` |
| `subscription_preapproval` cancelado | `subscription_status = 'inactive'` |

## Email (Resend)

| Gatilho | Email |
|---|---|
| Forgot password | Link redefinição (expira 1h) |
| Assinatura confirmada | Confirmação ao capitão |
| Pagamento falhou | Aviso ao capitão |

Convite para jogador = link copiado pelo capitão (sem email automático).

**Variáveis de ambiente:**
```ini
RESEND_API_KEY=
MAIL_FROM=noreply@seudominio.com
APP_URL=https://seusite.com
SUBSCRIPTION_PRICE=  # em centavos, ex: 5000 = R$50,00
MP_ACCESS_TOKEN=
MP_PLAN_ID=          # criado uma vez no MP antes do deploy
MP_WEBHOOK_SECRET=
```

## Decision Log

| Decisão | Alternativas | Por que |
|---|---|---|
| Login compartilhado + conta individual coexistem | Só individual, só compartilhado | Capitão paga e convida — ou compartilha link público |
| Link público = `group_token` existente | Novo mecanismo | Reutiliza o que já funciona |
| `invite_tokens` separado do `group_token` | Reutilizar group_token | Semânticas distintas — convite cria conta, link público só acessa |
| Acesso cortado imediatamente ao vencer | Graça, jogador assume | Simples, sem casos-limite |
| Mercado Pago Preapproval Plans | Stripe, Asaas, manual | Recorrência automática, PIX+cartão, Brasil |
| Webhook como fonte de verdade | Redirect pós-checkout | Redirect pode falhar |
| Resend para email | Mailgun, SMTP Hostinger | Free tier, API simples, sem SDK |
| Convite por link copiado (sem email automático) | Email disparado | WhatsApp é mais natural no contexto |
| Token opaco para user_session | JWT, cookie | Consistente com arquitetura existente |
| Preço único global | Por slot, por grupo | Todos pagam o mesmo valor |
