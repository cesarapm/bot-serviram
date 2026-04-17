<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Log;

class CitaController extends Controller
{
    /**
     * Listar citas (vista calendar para el front).
     *
     * Query params opcionales:
     *   ?year=2026&month=4   → filtra por mes
     *   ?estatus=pendiente   → filtra por estatus
     *   ?ciudad=Monterrey    → filtra por ciudad
     *   ?estado=NL           → filtra por estado
     */
    public function index(Request $request)
    {
        $query = Cita::query()->orderBy('fecha')->orderBy('hora');

        if ($request->filled('year') && $request->filled('month')) {
            $query->delMes((int) $request->year, (int) $request->month);
        }

        if ($request->filled('estatus')) {
            $query->where('estatus', $request->estatus);
        }

        if ($request->filled('ciudad')) {
            $query->where('ciudad', $request->ciudad);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $citas = $query->get()->map(fn($c) => $this->format($c));

        return response()->json($citas);
    }

    /**
     * Mostrar una cita específica.
     */
    public function show(Cita $cita)
    {
        return response()->json($this->format($cita));
    }

    /**
     * Recibir cita desde n8n (webhook sin auth).
     *
     * Payload esperado de n8n:
     * {
     *   "estatus":          "pendiente",
     *   "nombre":           "José García",
     *   "servicio":         "Consulta general",
     *   "precio_servicio":  350.00,
     *   "dia":              "2026-04-10",
     *   "hora":             "10:30",
     *   "numero_celular":   "+521234567890",
     *   "estado":           "Nuevo León",
     *   "ciudad":           "Monterrey"
     * }
     */
    public function store(Request $request)
    {
        // Log::info(request()->all()); // Log para depuración
        $data = $request->validate([
            'estatus'         => ['sometimes', Rule::in(['pendiente', 'confirmada', 'cancelada', 'completada'])],
            'nombre'          => ['required', 'string', 'max:255'],
            'servicio'        => ['required', 'string', 'max:255'],
            'precio_servicio' => ['nullable', 'numeric', 'min:0'],
            'fecha'           => ['required', 'date_format:Y-m-d'],
            'hora'            => ['required', 'date_format:H:i:s'],
            'numero_celular'  => ['required', 'string', 'max:20'],
            'estado'          => ['required', 'string', 'max:100'],
            'ciudad'          => ['required', 'string', 'max:100'],
            'direccion'       => ['nullable', 'string', 'max:255'],
        ]);

        $cita = Cita::create($data);

        return response()->json($this->format($cita), 201);
    }

    /**
     * Actualizar estatus u otros campos (desde el front o n8n).
     */
    public function update(Request $request, Cita $cita)
    {

    // Log::info(request()->all());
        $data = $request->validate([
            'estatus'         => ['sometimes', Rule::in(['pendiente', 'confirmada', 'cancelada', 'completada'])],
            'nombre'          => ['sometimes', 'string', 'max:255'],
            'servicio'        => ['sometimes', 'string', 'max:255'],
            'precio_servicio' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fecha'           => ['sometimes', 'date_format:Y-m-d'],
            'hora'            => ['sometimes', 'date_format:H:i:s'],
            'numero_celular'  => ['sometimes', 'string', 'max:20'],
            'estado'          => ['sometimes', 'string', 'max:100'],
            'ciudad'          => ['sometimes', 'string', 'max:100'],
            'direccion'       => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $cita->update($data);

        return response()->json($this->format($cita));
    }

    /**
     * Eliminar una cita.
     */
    public function destroy(Cita $cita)
    {
        $cita->delete();

        return response()->json(['message' => 'Cita eliminada.']);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function format(Cita $c): array
    {
        return [
            'id'              => $c->id,
            'estatus'         => $c->estatus,
            'nombre'          => $c->nombre,
            'servicio'        => $c->servicio,
            'precio_servicio' => $c->precio_servicio,
            'fecha'           => $c->fecha?->format('Y-m-d'),
            'hora'            => $c->hora,
            'numero_celular'  => $c->numero_celular,
            'estado'          => $c->estado,
            'ciudad'          => $c->ciudad,
            'direccion'       => $c->direccion,
            'created_at'      => $c->created_at,
        ];
    }
}
