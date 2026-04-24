# Sistema de Conversaciones por Departamentos

## 🎯 **Nuevo Sistema Implementado**

### **Cómo Funciona Ahora**

✅ **Las conversaciones se asignan a DEPARTAMENTOS** (no a usuarios específicos)  
✅ **Los usuarios ven conversaciones de SU departamento**  
✅ **Los administradores ven TODAS las conversaciones**  

## 🏗️ **Estructura de Base de Datos**

### **Campo Agregado a `conversations`**
```sql
ALTER TABLE conversations ADD COLUMN department ENUM(
  'ventas', 
  'servicio_tecnico', 
  'garantias', 
  'refacciones', 
  'administracion'
) NULL;
```

### **Campo en `users` (ya existente)**
```sql
-- Campo department en users determina qué conversaciones puede ver cada usuario
department ENUM('ventas', 'servicio_tecnico', 'garantias', 'refacciones', 'administracion') NULL
```

## 🔄 **Flujo de Asignación**

### **1. Desde n8n (Webhook)**
```json
POST /api/webhook/assign-department
{
  "conversation_id": 9,
  "department": "ventas"
}
```

**Resultado:**
- La conversación ID 9 se asigna al departamento "ventas"
- Todos los usuarios con `department = 'ventas'` pueden verla
- Los admins también pueden verla

### **2. Automática (al recibir mensaje)**
```json
POST /api/webhook
{
  "data": {
    "from": "+1234567890",
    "text": "Quiero información de precios",
    "department": "ventas"  // ← n8n puede especificar departamento
  }
}
```

## 👥 **Permisos de Visualización**

### **Para Asesores (`role:asesor`)**
```php
// Solo ven conversaciones de SU departamento
$conversations = Conversation::byDepartment($user->department)
    ->where('status', 'active')
    ->get();
```

### **Para Administradores (`role:admin`)**  
```php
// Ven TODAS las conversaciones
$conversations = Conversation::where('status', 'active')
    ->get();
```

## 🚀 **APIs Actualizadas**

### **1. Listar Conversaciones**
```http
GET /api/conversations
Authorization: Bearer token
```

**Respuesta (Asesor de Ventas):**
```json
[
  {
    "id": 9,
    "department": "ventas",
    "department_name": "Ventas", 
    "is_human": true,
    "status": "active",
    "contact": {
      "id": 5,
      "phone": "+5214445087305",
      "name": "Cliente"
    },
    "last_message": "Quiero información de precios",
    "updated_at": "2026-04-24T10:30:00Z"
  }
]
```

**Respuesta (Admin):**
```json
[
  {
    "id": 8,
    "department": "servicio_tecnico", 
    "department_name": "Servicio Técnico",
    // ... otros campos
  },
  {
    "id": 9,
    "department": "ventas",
    "department_name": "Ventas", 
    // ... otros campos
  }
]
```

### **2. Asignar Conversación a Departamento**
```http
POST /api/webhook/assign-department
Content-Type: application/json

{
  "conversation_id": 123,
  "department": "servicio_tecnico"
}
```

**Respuesta:**
```json
{
  "status": "assigned",
  "conversation_id": 123,
  "assigned_to": {
    "type": "department",
    "department": "servicio_tecnico",
    "department_name": "Servicio Técnico"
  },
  "message": "Conversación asignada exitosamente"
}
```

## 📊 **Estadísticas por Departamento**

### **Ver Carga de Trabajo**
```bash
php artisan conversations:manage stats
```

**Output:**
```
📊 Estadísticas de Carga de Trabajo por Departamento

+------------------+----------+---------------+-----------------+
| Departamento     | Asesores | Conv. Activas | Promedio/Asesor |
+------------------+----------+---------------+-----------------+
| Ventas           | 2        | 15            | 7.5             |
| Servicio Técnico | 2        | 8             | 4.0             |
| Garantías        | 1        | 3             | 3.0             |
+------------------+----------+---------------+-----------------+
```

## ⚡ **Comandos Útiles**

### **Asignar Conversación Manualmente**
```bash
# Por departamento
php artisan conversations:manage assign --conversation=9 --department=ventas

# Ver conversaciones sin asignar 
php artisan conversations:manage list-unassigned
```

### **Crear Usuarios de Ejemplo**
```bash
php artisan db:seed --class=DepartmentUsersSeeder
```

## 🔧 **Configuración de Usuarios**

### **Crear Asesor de Ventas**
```php
User::create([
    'name' => 'Carlos Vendedor',
    'email' => 'carlos@empresa.com', 
    'password' => Hash::make('password'),
    'department' => 'ventas',
    'is_active' => true,
]);

// Asignar rol
$user->assignRole('asesor');
```

### **Crear Administrador**
```php
User::create([
    'name' => 'Admin General',
    'email' => 'admin@empresa.com',
    'password' => Hash::make('password'),
    'department' => null, // Los admin no necesitan departamento
    'is_active' => true,
]);

$user->assignRole('admin');
```

## 📱 **Frontend - Cambios Necesarios**

### **1. Al Listar Conversaciones**
```javascript
// Antes
conversations.filter(conv => conv.assigned_to?.id === currentUserId)

// Ahora - automático por API
// La API ya filtra según rol/departamento del usuario
```

### **2. Mostrar Departamento en UI**
```jsx
<div className="conversation-card">
  <div className="department-badge">
    {conversation.department_name}
  </div>
  <div className="contact-info">
    {conversation.contact.name}
  </div>
</div>
```

### **3. Indicadores Visuales**
```css
.department-ventas { border-left: 4px solid #10b981; }
.department-servicio_tecnico { border-left: 4px solid #3b82f6; }  
.department-garantias { border-left: 4px solid #f59e0b; }
.department-refacciones { border-left: 4px solid #8b5cf6; }
.department-administracion { border-left: 4px solid #ef4444; }
```

## ✅ **Beneficios del Nuevo Sistema**

1. **✅ Flexibilidad**: Múltiples asesores pueden atender el mismo departamento
2. **✅ Escalabilidad**: Fácil agregar/quitar asesores sin reasignar conversaciones  
3. **✅ Claridad**: Cada conversación tiene un departamento claro
4. **✅ Permisos**: Control granular de qué ve cada usuario
5. **✅ Estadísticas**: Métricas por departamento más precisas

## 🎯 **Casos de Uso**

### **Escenario 1: Cliente Selecciona Ventas en Menú**
1. n8n envía `POST /api/webhook/assign-department` con `department: "ventas"`  
2. Conversación se asigna a departamento "ventas"
3. Carlos y María (ambos de ventas) pueden ver la conversación
4. Admin también puede verla

### **Escenario 2: Asesor de Ventas Inicia Sesión**
1. Frontend llama `GET /api/conversations` 
2. API devuelve solo conversaciones con `department = "ventas"`
3. Frontend muestra lista filtrada automáticamente

### **Escenario 3: Admin Revisa Todas las Conversaciones**
1. Admin inicia sesión
2. Frontend llama `GET /api/conversations`
3. API devuelve conversaciones de TODOS los departamentos
4. Frontend muestra vista completa con filtros por departamento

¡El sistema está **completamente funcional** y listo para usar! 🚀