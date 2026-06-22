# Design: Normalização Slots/Groups + QR Unificado

## Resumo

Dois problemas resolvidos juntos:
1. `groups` referenciava horário por colunas duplicadas, sem FK para `slots`
2. QR de jogo com grupo cadastrado bloqueava substitutos no fluxo de pagamento avulso

## Decisões

| Decisão | Alternativas | Por que |
|---|---|---|
| `slots` perde `camera_id` | Manter; criar `time_slots` separado | Câmera é metadado de gravação, não de horário |
| `groups` ganha `slot_id` FK | FK com camera_id; sem FK | Integridade referencial sem acoplar câmera |
| Unique key `(weekday, start_hour, start_minute)` | Por camera_id | Horário é único por tempo, não por câmera |
| QR mostra login + avulso simultaneamente | Redirect para login | Menor fricção; substituto tem caminho claro |
| Grupo e substituto coexistem nos vídeos | Marcar jogo como avulso | Zero overhead; quem pagou vê |
| Sem marcação manual de "jogo avulso" | Admin marcar antes/depois | YAGNI — fluxo natural resolve |

## Schema

### `slots` — remove `camera_id`

```sql
ALTER TABLE slots DROP INDEX uq_slot;
ALTER TABLE slots DROP COLUMN camera_id;
ALTER TABLE slots ADD UNIQUE KEY uq_slot (weekday, start_hour, start_minute);
```

### `groups` — adiciona `slot_id`, remove colunas redundantes

```sql
ALTER TABLE `groups` ADD COLUMN slot_id INT UNSIGNED NULL AFTER id;

UPDATE `groups` g
JOIN slots s ON s.weekday = g.slot_weekday
           AND s.start_hour = g.slot_hour
           AND s.start_minute = COALESCE(g.slot_minute, 0)
SET g.slot_id = s.id;

ALTER TABLE `groups` MODIFY slot_id INT UNSIGNED NOT NULL;
ALTER TABLE `groups` ADD FOREIGN KEY fk_group_slot (slot_id) REFERENCES slots(id);
ALTER TABLE `groups` DROP COLUMN slot_weekday,
                     DROP COLUMN slot_hour,
                     DROP COLUMN slot_minute,
                     DROP COLUMN camera_id;
```

`games` e `videos` mantêm `camera_id` — câmera é metadado de gravação.

## Backend

### Queries que mudam

**`maybeCreateGame()`** — busca slot só por weekday + hour (sem camera_id).

**`getGroupVideos()`** — JOIN direto em `slots` via `slot_id`.

**`resolveGroupAccess()` / `resolveVideoAuth()`** — JOIN group_tokens → groups → slots para obter weekday/hour/duration.

**`gameAccessType()`** — JOIN groups → slots para verificar se há grupo no horário do jogo.

**`getGroupSubscription()`** e **`initiateGroupSubscription()`** — não mudam (usam group_id diretamente).

### Admin

- `createAdminGroup` / `updateAdminGroup` recebem `slot_id` em vez de `slot_weekday + slot_hour + camera_id`
- `getAdminGroups` retorna `slot_id` + dados do slot via JOIN

## Frontend

### `AdminGroupsView`

- Formulário de grupo: dropdown de slots existentes (carregados via `fetchAdminSlots`) em vez de campos manuais de weekday/hour/camera

### `GameView` — novo estado `group-or-avulso`

```
game.access_type === 'group' && sem token válido
  → view = 'group-or-avulso'

  ┌──────────────────┬──────────────────────┐
  │ 🏃 Entrar com    │ 💳 Comprar acesso    │
  │ login do time    │ avulso               │
  │                  │                      │
  │ Use o login e    │ R$ 25,00 jogo        │
  │ senha do grupo   │ R$ 5,00 por clipe    │
  │                  │                      │
  │ [ Entrar ]       │ [ Ver opções ]       │
  └──────────────────┴──────────────────────┘
```

- Card "Entrar" → `goGroupLogin()` (salva `after_login`, redireciona `/login`)
- Card "Comprar" → expande para `avulso-preview` inline (sem redirect)

## Assumptions

- Nunca dois horários iguais no mesmo campo — unique `(weekday, start_hour, start_minute)` é válida
- Admin sempre cria o slot antes de criar o grupo
- Todos os grupos existentes têm correspondência em slots (verificar antes de rodar migration)
