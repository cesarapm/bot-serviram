# Instrucciones Frontend Quasar - Módulo de Citas Previas

Este documento cubre todo lo que el frontend (Quasar + Vue 3 + Pinia) necesita para integrar
la vista de citas previas estilo Google Calendar.

---

## 1) Contrato del backend

### Endpoints disponibles

| Método   | Endpoint                  | Auth requerida         | Descripción                        |
|----------|---------------------------|------------------------|------------------------------------|
| `GET`    | `/api/citas`              | Bearer token (sanctum) | Listar citas con filtros           |
| `GET`    | `/api/citas/{id}`         | Bearer token (sanctum) | Ver detalle de una cita            |
| `PUT`    | `/api/admin/citas/{id}`   | Bearer token + admin   | Actualizar una cita                |
| `DELETE` | `/api/admin/citas/{id}`   | Bearer token + admin   | Eliminar una cita                  |
| `POST`   | `/api/webhook/citas`      | Sin auth (n8n)         | Recibir citas desde la IA          |

---

### GET /api/citas — Listar citas

**Query params opcionales:**

| Param    | Tipo   | Ejemplo          | Descripción                     |
|----------|--------|------------------|---------------------------------|
| `year`   | int    | `2026`           | Año del calendario              |
| `month`  | int    | `4`              | Mes del calendario (1-12)       |
| `estatus`| string | `pendiente`      | Filtrar por estatus             |
| `ciudad` | string | `Monterrey`      | Filtrar por ciudad              |
| `estado` | string | `Nuevo León`     | Filtrar por estado              |

> **Nota:** `year` y `month` se usan juntos para la vista mensual del calendario.

**Respuesta (array):**

```json
[
  {
    "id": 1,
    "estatus": "pendiente",
    "nombre": "José García",
    "servicio": "Consulta general",
    "precio_servicio": 350.00,
    "dia": "2026-04-10",
    "hora": "10:30",
    "numero_celular": "+521234567890",
    "estado": "Nuevo León",
    "ciudad": "Monterrey",
    "created_at": "2026-04-06T12:00:00.000000Z"
  }
]
```

**Valores válidos de `estatus`:**
- `pendiente`
- `confirmada`
- `cancelada`
- `completada`

---

### GET /api/citas/{id} — Detalle de una cita

**Respuesta:** mismo objeto de arriba para una sola cita.

---

### PUT /api/admin/citas/{id} — Actualizar cita (solo admin)

**Body (todos opcionales, solo los que cambias):**

```json
{
  "estatus": "confirmada",
  "nombre": "José García López",
  "servicio": "Consulta especializada",
  "precio_servicio": 500.00,
  "dia": "2026-04-11",
  "hora": "11:00",
  "numero_celular": "+521234567890",
  "estado": "Nuevo León",
  "ciudad": "Monterrey"
}
```

**Respuesta:** objeto cita actualizado.

---

### DELETE /api/admin/citas/{id} — Eliminar cita (solo admin)

**Respuesta:**
```json
{ "message": "Cita eliminada." }
```

---

## 2) Store de citas (Pinia)

Archivo sugerido: `src/stores/citas.ts`

```ts
import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'

export interface Cita {
  id: number
  estatus: 'pendiente' | 'confirmada' | 'cancelada' | 'completada'
  nombre: string
  servicio: string
  precio_servicio: number | null
  dia: string       // 'YYYY-MM-DD'
  hora: string      // 'HH:mm'
  numero_celular: string
  estado: string
  ciudad: string
  created_at: string
}

export const useCitasStore = defineStore('citas', {
  state: () => ({
    citas: [] as Cita[],
    loading: false,
    currentYear: new Date().getFullYear(),
    currentMonth: new Date().getMonth() + 1,
  }),

  getters: {
    // Agrupar por día para pintar el calendario
    citasPorDia: (state): Record<string, Cita[]> => {
      return state.citas.reduce((acc, cita) => {
        const key = cita.dia
        if (!acc[key]) acc[key] = []
        acc[key].push(cita)
        return acc
      }, {} as Record<string, Cita[]>)
    },
  },

  actions: {
    async fetchCitas(filters?: {
      year?: number
      month?: number
      estatus?: string
      ciudad?: string
      estado?: string
    }) {
      this.loading = true
      try {
        const { data } = await api.get('/citas', { params: filters })
        this.citas = data
      } finally {
        this.loading = false
      }
    },

    async fetchMes(year: number, month: number) {
      this.currentYear = year
      this.currentMonth = month
      await this.fetchCitas({ year, month })
    },

    async updateCita(id: number, payload: Partial<Cita>) {
      const { data } = await api.put(`/admin/citas/${id}`, payload)
      const idx = this.citas.findIndex(c => c.id === id)
      if (idx !== -1) this.citas[idx] = data
      return data
    },

    async deleteCita(id: number) {
      await api.delete(`/admin/citas/${id}`)
      this.citas = this.citas.filter(c => c.id !== id)
    },
  },
})
```

---

## 3) Colores por estatus (sugerencia UI)

```ts
export const estatusColor: Record<string, string> = {
  pendiente:  'orange',
  confirmada: 'green',
  cancelada:  'red',
  completada: 'grey',
}
```

---

## 4) Cargar el mes al montar el componente

```ts
import { onMounted } from 'vue'
import { useCitasStore } from 'src/stores/citas'

const store = useCitasStore()

onMounted(() => {
  store.fetchMes(store.currentYear, store.currentMonth)
})

// Navegar mes anterior / siguiente
function prevMes() {
  let m = store.currentMonth - 1
  let y = store.currentYear
  if (m < 1) { m = 12; y-- }
  store.fetchMes(y, m)
}

function nextMes() {
  let m = store.currentMonth + 1
  let y = store.currentYear
  if (m > 12) { m = 1; y++ }
  store.fetchMes(y, m)
}
```

---

## 5) Datos n8n necesita enviar al webhook

> **URL:** `POST /api/webhook/citas`
> No requiere token de autenticación.

**Payload JSON exacto que n8n debe mandar:**

```json
{
  "estatus":          "pendiente",
  "nombre":           "José García",
  "servicio":         "Consulta general",
  "precio_servicio":  350.00,
  "dia":              "2026-04-10",
  "hora":             "10:30",
  "numero_celular":   "+521234567890",
  "estado":           "Nuevo León",
  "ciudad":           "Monterrey"
}
```

**Campos requeridos (si falta alguno el webhook responde 422):**

| Campo            | Tipo    | Ejemplo              | Requerido |
|------------------|---------|----------------------|-----------|
| `nombre`         | string  | `"José García"`      | ✅ Sí     |
| `servicio`       | string  | `"Consulta general"` | ✅ Sí     |
| `dia`            | string  | `"2026-04-10"`       | ✅ Sí (formato `YYYY-MM-DD`) |
| `hora`           | string  | `"10:30"`            | ✅ Sí (formato `HH:mm`) |
| `numero_celular` | string  | `"+521234567890"`    | ✅ Sí     |
| `estado`         | string  | `"Nuevo León"`       | ✅ Sí     |
| `ciudad`         | string  | `"Monterrey"`        | ✅ Sí     |
| `precio_servicio`| number  | `350.00`             | ❌ Opcional |
| `estatus`        | string  | `"pendiente"`        | ❌ Opcional (default: `pendiente`) |

**Respuesta exitosa (HTTP 201):**
```json
{
  "id": 42,
  "estatus": "pendiente",
  "nombre": "José García",
  "servicio": "Consulta general",
  "precio_servicio": 350.00,
  "dia": "2026-04-10",
  "hora": "10:30",
  "numero_celular": "+521234567890",
  "estado": "Nuevo León",
  "ciudad": "Monterrey",
  "created_at": "2026-04-06T15:30:00.000000Z"
}
```

**Respuesta con error de validación (HTTP 422):**
```json
{
  "message": "The dia field is required.",
  "errors": {
    "dia": ["The dia field is required."]
  }
}
```

---

## 6) Configuración n8n — nodo HTTP Request

En el nodo **HTTP Request** de n8n configura así:

- **Method:** `POST`
- **URL:** `https://tudominio.com/api/webhook/citas`
- **Body Content Type:** `JSON`
- **Body:** mapeo de los campos del formulario/chat al JSON del punto 5

> Los campos `estado` y `ciudad` deben venir del flujo de conversación donde la IA
> le preguntó al usuario su ubicación.
