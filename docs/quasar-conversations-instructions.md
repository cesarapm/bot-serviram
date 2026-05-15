# Guía Frontend – Conversaciones

## Endpoints disponibles

Todos los endpoints requieren el header:
```
Authorization: Bearer {token}
```

---

### Listar conversaciones activas

```
GET /api/conversations
```

**Comportamiento por rol:**
- `admin`: ve **todas** las conversaciones activas.
- `asesor`: ve las conversaciones de su departamento (asignadas al departamento, asignadas a agentes del mismo departamento o sin asignar).

**Respuesta `200`:**
```json
[
  {
    "id": 1,
    "is_human": false,
    "status": "active",
    "department": "ventas",
    "department_name": "Ventas",
    "contact": {
      "id": 10,
      "phone": "+521234567890",
      "name": "Juan Pérez"
    },
    "assigned_to": {
      "id": 3,
      "name": "María López"
    },
    "last_message": "Hola, ¿en qué te puedo ayudar?",
    "updated_at": "2026-05-15T12:00:00.000000Z"
  }
]
```

---

### Obtener mensajes de una conversación

```
GET /api/conversations/{id}/messages
```

**Respuesta `200`:** Array de mensajes ordenados por fecha ascendente.

```json
[
  {
    "id": 1,
    "conversation_id": 1,
    "body": "Hola",
    "direction": "inbound",
    "sender_type": "contact",
    "twilio_sid": "SMxxxx",
    "created_at": "2026-05-15T11:00:00.000000Z"
  }
]
```

---

### Activar / desactivar modo humano

```
PATCH /api/conversations/{id}/toggle-human
```

Alterna `is_human` entre `true` y `false`.

- `is_human: true` → el agente humano controla la conversación desde el front.
- `is_human: false` → n8n / IA retoma el control.

**Respuesta `200`:**
```json
{
  "conversation_id": 1,
  "is_human": true
}
```

---

### Enviar mensaje como agente humano

```
POST /api/conversations/{id}/send
```

Solo funciona cuando `is_human = true`.

**Body:**
```json
{
  "body": "Texto del mensaje (máx 1600 caracteres)"
}
```

**Respuesta `200`:**
```json
{
  "status": "sent",
  "message_id": 42,
  "quota": { ... }
}
```

**Errores posibles:**
| Código | Descripción |
|--------|-------------|
| `422`  | La conversación no está en modo humano |
| `429`  | Cuota mensual de mensajes agotada |
| `500`  | Error al enviar por Twilio |
| `502`  | Twilio no pudo enviar el mensaje |

---

### Actualizar nombre de contacto

```
PATCH /api/contacts/{id}/name
```

**Roles permitidos:** `admin`, `asesor`

**Body:**
```json
{
  "name": "Nuevo nombre"
}
```

---

### Eliminar una conversación *(solo admin)*

```
DELETE /api/conversations/{id}
```

> Requiere rol `admin`. Elimina la conversación y **todos sus mensajes** de forma permanente.

**Respuesta `200`:**
```json
{
  "message": "Conversación eliminada correctamente."
}
```

**Ejemplo en Quasar/Axios:**
```js
async deleteConversation(conversationId) {
  await api.delete(`/conversations/${conversationId}`)
  // Eliminar de la lista local
  this.conversations = this.conversations.filter(c => c.id !== conversationId)
}
```

---

## Eventos en tiempo real (Laravel Reverb / Echo)

Suscribirse al canal `conversation.{id}` para recibir nuevos mensajes:

```js
Echo.channel(`conversation.${conversationId}`)
  .listen('MessageReceived', (e) => {
    // e.message contiene el nuevo mensaje
    this.messages.push(e.message)
  })
```
