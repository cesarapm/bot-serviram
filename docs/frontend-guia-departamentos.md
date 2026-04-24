# 📋 Sistema de Conversaciones por Departamentos - Guía Frontend

## 🎯 **Nueva Mecánica del Sistema**

### **Cómo Funciona Ahora**
- ✅ **Las conversaciones se asignan a DEPARTAMENTOS** (no solo usuarios específicos)  
- ✅ **Los asesores ven conversaciones de SU departamento + disponibles**
- ✅ **Los administradores ven TODAS las conversaciones**
- ✅ **Flexibilidad**: Asignación individual Y departamental

---

## 👥 **Tipos de Usuario y Permisos**

### **🔹 ASESOR** (`role: "asesor"`)
**Ve conversaciones cuando:**
1. **Departamento directo** → `department = "ventas"` 
2. **Usuario del mismo departamento** → `assigned_to` de alguien de ventas
3. **Sin asignar** → Disponibles para tomar (`department = null && assigned_to = null`)

### **🔹 ADMIN** (`role: "admin"`)  
**Ve todas las conversaciones** sin filtros

---

## 🔄 **Flujo de Asignación**

### **Opción 1: Por Departamento** (Recomendado)
```json
POST /api/webhook/assign-department
{
  "conversation_id": 123,
  "department": "ventas"
}
```
**Resultado**: Todos los usuarios de "ventas" pueden verla

### **Opción 2: Usuario Específico**
```json
POST /api/webhook/assign-department  
{
  "conversation_id": 123,
  "department": "servicio_tecnico",
  "assigned_to": 5
}
```
**Resultado**: Usuario ID 5 + todos de "servicio_tecnico" pueden verla

### **Opción 3: Sin Asignar**
```json
// No enviar nada, o enviar null
{
  "conversation_id": 123,
  "department": null
}
```
**Resultado**: Disponible para que cualquier asesor la tome

---

## 🚀 **APIs para Frontend**

### **1. Listar Conversaciones** 
```http
GET /api/conversations
Authorization: Bearer {token}
```

**Respuesta Automática por Rol:**

#### **Para Asesor de Ventas:**
```json
[
  {
    "id": 123,
    "department": "ventas",
    "department_name": "Ventas",
    "is_human": true,
    "status": "active",
    "contact": {
      "id": 45,
      "phone": "+5214445087305", 
      "name": "Cliente López"
    },
    "assigned_to": null,
    "last_message": "Necesito información de precios",
    "updated_at": "2026-04-24T10:30:00Z"
  },
  {
    "id": 124,
    "department": null,
    "department_name": "Sin Asignar", 
    "is_human": false,
    "status": "active",
    "contact": {
      "id": 46,
      "phone": "+5214445087306",
      "name": "Cliente Pérez" 
    },
    "assigned_to": null,
    "last_message": "Hola, necesito ayuda",
    "updated_at": "2026-04-24T09:15:00Z"
  }
]
```

#### **Para Admin:**
```json
[
  {
    "id": 123,
    "department": "ventas",
    "department_name": "Ventas",
    // ... resto igual
  },
  {
    "id": 125, 
    "department": "servicio_tecnico",
    "department_name": "Servicio Técnico",
    // ... resto igual
  },
  {
    "id": 124,
    "department": null,
    "department_name": "Sin Asignar",
    // ... resto igual  
  }
]
```

### **2. Mensajes de Conversación**
```http
GET /api/conversations/{id}/messages
```
Sin cambios - funciona igual que antes.

### **3. Activar/Desactivar Modo Humano**  
```http
POST /api/conversations/{id}/toggle-human
```
Sin cambios - funciona igual que antes.

---

## 🎨 **Implementación Frontend**

### **1. Indicadores Visuales por Departamento**
```css
/* Colores por departamento */
.conversation-card.department-ventas {
  border-left: 4px solid #10b981; /* Verde */
}

.conversation-card.department-servicio_tecnico {
  border-left: 4px solid #3b82f6; /* Azul */
}

.conversation-card.department-garantias {
  border-left: 4px solid #f59e0b; /* Naranja */
}

.conversation-card.department-refacciones {
  border-left: 4px solid #8b5cf6; /* Morado */
}

.conversation-card.department-administracion {
  border-left: 4px solid #ef4444; /* Rojo */
}

.conversation-card.department-unassigned {
  border-left: 4px solid #6b7280; /* Gris */
}
```

### **2. Componente de Lista**
```jsx
function ConversationList() {
  const [conversations, setConversations] = useState([]);
  const [user, setUser] = useState(null);

  useEffect(() => {
    // API automáticamente filtra según rol del usuario
    fetchConversations();
  }, []);

  const fetchConversations = async () => {
    const response = await fetch('/api/conversations', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });
    const data = await response.json();
    setConversations(data);
  };

  return (
    <div className="conversation-list">
      {conversations.map(conversation => (
        <ConversationCard 
          key={conversation.id}
          conversation={conversation}
          currentUser={user}
        />
      ))}
    </div>
  );
}
```

### **3. Card de Conversación**
```jsx
function ConversationCard({ conversation, currentUser }) {
  const getDepartmentClass = (department) => {
    if (!department) return 'department-unassigned';
    return `department-${department}`;
  };

  const canTakeConversation = () => {
    // Solo asesores pueden tomar conversaciones sin asignar
    return currentUser.role === 'asesor' && 
           conversation.department === null && 
           conversation.assigned_to === null;
  };

  return (
    <div className={`conversation-card ${getDepartmentClass(conversation.department)}`}>
      <div className="conversation-header">
        <div className="department-badge">
          {conversation.department_name}
        </div>
        
        {canTakeConversation() && (
          <button className="take-conversation-btn">
            Tomar Conversación
          </button>
        )}
      </div>
      
      <div className="contact-info">
        <h3>{conversation.contact.name}</h3>
        <p>{conversation.contact.phone}</p>
      </div>
      
      <div className="last-message">
        {conversation.last_message}
      </div>
      
      <div className="conversation-status">
        <span className={`status ${conversation.is_human ? 'human' : 'bot'}`}>
          {conversation.is_human ? '👤 Humano' : '🤖 Bot'}
        </span>
        
        {conversation.assigned_to && (
          <span className="assigned-to">
            Asignado a: {conversation.assigned_to.name}
          </span>
        )}
      </div>
    </div>
  );
}
```

### **4. Filtros Opcionales (Para Admin)**
```jsx
function ConversationFilters({ onFilterChange, userRole }) {
  const [selectedDepartment, setSelectedDepartment] = useState('all');

  const departments = [
    { value: 'all', label: 'Todos los Departamentos' },
    { value: 'ventas', label: 'Ventas' },
    { value: 'servicio_tecnico', label: 'Servicio Técnico' },
    { value: 'garantias', label: 'Garantías' }, 
    { value: 'refacciones', label: 'Refacciones' },
    { value: 'administracion', label: 'Administración' },
    { value: null, label: 'Sin Asignar' }
  ];

  // Solo mostrar filtros si es admin
  if (userRole !== 'admin') return null;

  const handleFilterChange = (department) => {
    setSelectedDepartment(department);
    onFilterChange(department);
  };

  return (
    <div className="conversation-filters">
      <select 
        value={selectedDepartment}
        onChange={(e) => handleFilterChange(e.target.value)}
      >
        {departments.map(dept => (
          <option key={dept.value} value={dept.value}>
            {dept.label}
          </option>
        ))}
      </select>
    </div>
  );
}
```

---

## 💡 **Casos de Uso Prácticos**

### **Caso 1: Cliente Selecciona "Ventas" en Menú**
1. **n8n** → `POST /api/webhook/assign-department` con `department: "ventas"`
2. **Resultado** → Carlos y María (de ventas) ven la conversación
3. **Frontend** → Muestra conversación con badge "Ventas" verde

### **Caso 2: Asesor de Soporte Inicia Sesión**
1. **Frontend** → `GET /api/conversations` 
2. **API** → Devuelve solo conversaciones de "servicio_tecnico" + sin asignar
3. **Frontend** → Lista filtrada automáticamente

### **Caso 3: Admin Revisa Todo**
1. **Frontend** → `GET /api/conversations`
2. **API** → Devuelve TODAS las conversaciones
3. **Frontend** → Muestra filtros por departamento

### **Caso 4: Conversación Sin Asignar**
1. **Estado** → `department: null, assigned_to: null`
2. **Resultado** → TODOS los asesores la ven
3. **Frontend** → Botón "Tomar Conversación" visible para asesores

---

## 📊 **Departamentos Disponibles**

| Código | Nombre | Color UI | 
|--------|--------|----------|
| `ventas` | Ventas | Verde `#10b981` |
| `servicio_tecnico` | Servicio Técnico | Azul `#3b82f6` |
| `garantias` | Garantías | Naranja `#f59e0b` |
| `refacciones` | Refacciones | Morado `#8b5cf6` |
| `administracion` | Administración | Rojo `#ef4444` |
| `null` | Sin Asignar | Gris `#6b7280` |

---

## ⚡ **Cambios vs Versión Anterior**

### **❌ Antes**
- Solo conversaciones asignadas individualmente
- `assigned_to` era el único filtro
- Asesores veían solo SU trabajo específico

### **✅ Ahora**  
- **Departamentos + usuarios individuales**
- **Conversaciones compartidas por equipo**
- **Asesores ven trabajo del departamento + disponibles**
- **Flexibilidad total de asignación**

---

## 🔧 **Testing Frontend**

### **Datos de Prueba**
```javascript
// Usuarios de prueba ya creados:
const testUsers = [
  { id: 3, name: "Carlos Vendedor", department: "ventas", role: "asesor" },
  { id: 4, name: "María Comercial", department: "ventas", role: "asesor" },
  { id: 5, name: "Juan Técnico", department: "servicio_tecnico", role: "asesor" },
  { id: 1, name: "Cesar Mata", role: "admin" }
];

// Para crear conversación de prueba:
POST /api/webhook/assign-department
{
  "conversation_id": 1,
  "department": "ventas"
}
```

### **Pruebas Recomendadas**
1. **Login como asesor de ventas** → Verificar que solo ve conversaciones de ventas
2. **Login como admin** → Verificar que ve todas las conversaciones  
3. **Crear conversación sin asignar** → Verificar que todos los asesores la ven
4. **Asignar al departamento** → Verificar filtrado automático

---

## ✅ **Checklist de Implementación**

- [ ] Actualizar componente de lista de conversaciones
- [ ] Implementar indicadores visuales por departamento  
- [ ] Agregar filtros para admin (opcional)
- [ ] Mostrar botón "Tomar conversación" para sin asignar
- [ ] Probar con diferentes roles de usuario
- [ ] Actualizar estilos CSS por departamento
- [ ] Validar responsividad en mobile

**¡El backend está 100% listo!** 🚀 Solo necesitas implementar el frontend siguiendo esta guía.