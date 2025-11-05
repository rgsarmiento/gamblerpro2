<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'

import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'

/* ======================
   Tipos e interfaces
====================== */
interface LinkItem { url: string | null; label: string; active: boolean }
interface Paginator<T> { data: T[]; links: LinkItem[]; total: number }
interface Casino { id: number; nombre: string }
interface Sucursal { id: number; nombre: string; casino_id: number }
interface Maquina { id: number; ndi: string; nombre: string; denominacion: number; sucursal_id: number; sucursal?: { nombre: string }; activa: number }
interface Filters { search?: string; casino_id?: number | null; sucursal_id?: number | null }
interface User { id: number; roles: string[]; casino_id?: number | null; sucursal_id?: number | null }

/* ======================
   Props desde Laravel
====================== */
const props = withDefaults(
    defineProps<{
        maquinas: Paginator<Maquina>
        sucursales: Sucursal[]
        casinos: Casino[]
        filters?: Filters
        user: User
    }>(),
    {
        filters: () => ({ search: '', casino_id: null, sucursal_id: null }),
    }
)

// Estado de filtros reactivos
const search = ref(props.filters.search ?? '')
const selectedCasino = ref<number | null>(props.filters.casino_id ?? null)
const selectedSucursal = ref<number | null>(props.filters.sucursal_id ?? null)

// Computed para filtrar sucursales según casino seleccionado
const sucursalesFiltradas = computed(() => {
  if (!selectedCasino.value) return []
  return props.sucursales.filter(s => s.casino_id === selectedCasino.value)
})

// 🔄 Watchers reactivos: al cambiar, recargamos con router.get
watch([search, selectedCasino, selectedSucursal], ([s, c, su]) => {
  router.get('/maquinas', {
    search: s,
    casino_id: c,
    sucursal_id: su,
  }, { preserveState: true, replace: true })
})


// Rol activo
const role = computed(() => props.user.roles[0] ?? '')

/* ======================
   Estado local y formularios
====================== */
const form = useForm({
    nombre: '',
    ndi: '',
    denominacion: '',
    codigo_interno: '',
    sucursal_id: '',
})


const isEditing = ref(false)
const editingId = ref<number | null>(null)



/* ======================
   Funciones CRUD
====================== */
const openEdit = (m: Maquina) => {
    isEditing.value = true
    editingId.value = m.id
    form.nombre = m.nombre
    form.ndi = m.ndi
    form.denominacion = String(m.denominacion)
    form.codigo_interno = m.codigo_interno ?? ''
    form.sucursal_id = String(m.sucursal_id)
}

const reset = () => {
    isEditing.value = false
    editingId.value = null
    form.reset()
    form.clearErrors()
}

const save = () => {
    if (isEditing.value && editingId.value) {
        form.put(`/maquinas/${editingId.value}`, {
            onSuccess: () => {
                toast.success('Máquina actualizada correctamente')
                reset()
            },
        })
    } else {
        form.post('/maquinas', {
            onSuccess: () => {
                toast.success('Máquina creada correctamente')
                reset()
            },
        })
    }
}

const deleteMaquina = (id: number) => {
    if (confirm('¿Eliminar esta máquina?')) {
        router.delete(`/maquinas/${id}`, {
            onSuccess: () => toast.success('Máquina eliminada correctamente'),
            onError: (e) => toast.error(e?.maquina ?? 'No se pudo eliminar la máquina'),
        })
    }
}

/* ======================
   Activar / Desactivar
====================== */
// const toggleActivo = (m: Maquina) => {
//     const nuevoEstado = m.activa === 1 ? 0 : 1
//     router.patch(`/maquinas/${m.id}/toggle`, { activa: nuevoEstado }, {
//         preserveScroll: true,
//         onSuccess: () => {
//             toast.success(`Máquina ${nuevoEstado ? 'activada' : 'desactivada'}`)
//             router.reload({ only: ['maquinas'] })
//         },
//         onError: () => toast.error('Error al actualizar estado'),
//     })
// }


const toggleActivo = (maquina, nuevoEstado) => {
    const estadoAnterior = maquina.activa
    maquina.activa = nuevoEstado ? 1 : 0

    router.patch(`/maquinas/${maquina.id}/toggle`, { activa: maquina.activa }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Estado actualizado', {
                description: `La ${maquina.nombre} ahora está ${nuevoEstado ? 'activa' : 'inactivo'}.`,
            })
        },
        onError: () => {
            maquina.activa = estadoAnterior
            toast.error('Error al actualizar el estado', {
                description: 'No se pudo cambiar el estado de la maquina.',
            })
        },
    })
}




/* ======================
   Fijar sucursal si es sucursal_admin
====================== */
if (props.user.roles.includes('sucursal_admin') && props.user.sucursal_id) {
    form.sucursal_id = String(props.user.sucursal_id)
}
</script>

<template>

    <Head title="Máquinas" />

    <AppLayout>

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

            

            <!-- Título y botón -->
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-xl font-bold">🎰 Máquinas</h1>
                <Button @click="reset" class="bg-indigo-600 text-white">Nueva máquina</Button>
            </div>

            

            <div v-if="role === 'master_admin' || role === 'casino_admin'" class="bg-card rounded-lg shadow border border-border  grid grid-cols-4 gap-4 p-4  mb-6">
                <div v-if="role === 'master_admin'">
                        <label class="block text-sm">Casino</label>
                        <Select v-model="selectedCasino" class=" w-full">
                            <SelectTrigger class="border w-full">
                                <SelectValue placeholder="Seleccione..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Casinos</SelectLabel>
                                    <SelectItem v-for="c in props.casinos" :key="c.id" :value="c.id">{{ c.nombre }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                
                <div >
                    <label class="block text-sm">Sucursal</label>
                    <Select v-model="selectedSucursal" class=" w-full">
                        <SelectTrigger class="border w-full">
                            <SelectValue placeholder="Seleccione..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Sucursales</SelectLabel>
                                <SelectItem v-for="s in (props.user.roles.includes('master_admin') ? sucursalesFiltradas : props.sucursales)" :key="s.id" :value="s.id">
            {{ s.nombre }}{{ s.nombre
                                }}</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Formulario -->
            <form @submit.prevent="save" class="grid grid-cols-4 gap-4 bg-card p-4 rounded mb-6">
                <input v-model="form.nombre" placeholder="Nombre" class="border p-2 rounded" />
                <input v-model="form.ndi" placeholder="NDI" class="border p-2 rounded" />
                <input v-model="form.denominacion" type="number" placeholder="Denominación"
                    class="border p-2 rounded" />
                <input v-model="form.codigo_interno" placeholder="Código interno" class="border p-2 rounded" />

                <Button type="submit" class="col-span-4 bg-indigo-600 text-white">Guardar</Button>
            </form>

            <!-- Buscador -->
            <div class="bg-card rounded-lg shadow border border-border p-4 mb-4 flex justify-between items-center">
                <Input v-model="search" placeholder="Buscar por nombre, NDI o código..." class="w-1/3" />
                <span class="text-muted-foreground text-sm">
                    Mostrando {{ props.maquinas.data.length }} de {{ props.maquinas.total }} registros
                </span>
            </div>

            <!-- Tabla -->
            <div class="bg-card rounded-lg shadow border border-border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>NDI</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Denominación</TableHead>
                            <TableHead>Sucursal</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-for="m in props.maquinas.data" :key="m.id">                            
                            <TableCell>{{ m.ndi }}</TableCell>
                            <TableCell>{{ m.nombre }}</TableCell>
                            <TableCell>{{ m.denominacion }}</TableCell>
                            <TableCell>{{ m.sucursal?.nombre }}</TableCell>   
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <Switch :model-value="Boolean(m.activa)"
                                        @update:model-value="(val: boolean) => toggleActivo(m, val)" />
                                    <Badge :variant="m.activa ? 'default' : 'secondary'" class="capitalize">
                                        {{ m.activa ? 'Activo' : 'Inactivo' }}
                                    </Badge>

                                </div>
                            </TableCell>

                            <TableCell>
                                <div class="flex gap-2">
                                    <Button variant="outline" size="sm" @click="openEdit(m)">Editar</Button>
                                    <Button variant="destructive" size="sm"
                                        @click="deleteMaquina(m.id)">Eliminar</Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- 🔹 Paginación -->
            <div class="flex gap-2 mt-4 justify-end">
                <a v-for="link in props.maquinas.links" :key="link.label" v-html="link.label" :href="link.url ?? '#'"
                    :class="[
                        'px-3 py-1 rounded text-sm',
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                    ]" />
            </div>
        </div>

    </AppLayout>
</template>
