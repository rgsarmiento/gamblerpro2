<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { toast } from 'vue-sonner'
import { usePage } from '@inertiajs/vue3'

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import {
    AlertDialog,
    AlertDialogTrigger,
    AlertDialogContent,
    AlertDialogHeader,
    AlertDialogFooter,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogCancel,
    AlertDialogAction
} from '@/components/ui/alert-dialog'
import { Trash2, Pencil } from "lucide-vue-next"
import { Switch } from '@/components/ui/switch'

interface Sucursal {
    id: number
    nombre: string
    casino_id: number
    telefono?: string
    direccion?: string
    base_monedas?: number
    base_billetes?: number
    activo: boolean
    casino?: { nombre: string }
}

const props = defineProps<{
    sucursales: any
    casinos: Array<{ id: number, nombre: string }>
    filters: { casino_id?: number, search?: string }
    user: { id: number, name: string, roles: string[] }
}>()

const form = useForm({
    id: null as number | null,
    casino_id: null as number | null,
    nombre: '',
    telefono: '',
    direccion: '',
    base_monedas: '' as string | number,
    base_billetes: '' as string | number,
    activo: true,
})

const showModal = ref(false)
const isEditing = ref(false)

const filterForm = ref({
    casino_id: props.filters.casino_id || null,
    search: props.filters.search || '',
})

const applyFilters = () => {
    router.get('/sucursales', {
        casino_id: filterForm.value.casino_id,
        search: filterForm.value.search,
    }, { preserveState: true, preserveScroll: true })
}

const openModal = (sucursal?: Sucursal) => {
    if (sucursal) {
        isEditing.value = true
        form.id = sucursal.id
        form.casino_id = sucursal.casino_id
        form.nombre = sucursal.nombre
        form.telefono = sucursal.telefono || ''
        form.direccion = sucursal.direccion || ''
        form.base_monedas = sucursal.base_monedas || ''
        form.base_billetes = sucursal.base_billetes || ''
        form.activo = sucursal.activo
    } else {
        isEditing.value = false
        form.reset()
        form.activo = true
    }
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    form.reset()
}

const save = () => {
    if (isEditing.value && form.id) {
        form.put(`/sucursales/${form.id}`, {
            onSuccess: () => {
                toast.success('Sucursal actualizada correctamente')
                closeModal()
            },
            onError: (errors) => {
                const errorMsg = Object.values(errors).flat().join(', ')
                toast.error(errorMsg || 'Error al actualizar sucursal')
            },
        })
    } else {
        form.post('/sucursales', {
            onSuccess: () => {
                toast.success('Sucursal creada correctamente')
                closeModal()
            },
            onError: (errors) => {
                const errorMsg = Object.values(errors).flat().join(', ')
                toast.error(errorMsg || 'Error al crear sucursal')
            },
        })
    }
}

const deleteSucursal = (sucursal: Sucursal) => {
    router.delete(`/sucursales/${sucursal.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Sucursal eliminada correctamente'),
        onError: (errors) => {
            const errorMsg = errors.delete || Object.values(errors).flat().join(', ')
            toast.error(errorMsg || 'Error al eliminar sucursal')
        },
    })
}

const formatCurrency = (value: number | null | undefined) => {
    if (!value) return '-'
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(value)
}
</script>

<template>
    <Head title="Sucursales" />
    
    <AppLayout>
        <div class="p-4 space-y-4">
            <!-- Encabezado -->
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">🏢 Sucursales</h1>
                <Button @click="openModal()" class="bg-indigo-600 text-white">Nueva Sucursal</Button>
            </div>

            <!-- Filtros -->
            <div class="bg-card p-4 rounded-lg border space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-sm font-medium">Casino</label>
                        <Select v-model="filterForm.casino_id" @update:model-value="applyFilters">
                            <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">Todos</SelectItem>
                                <SelectItem v-for="c in casinos" :key="c.id" :value="c.id">{{ c.nombre }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="col-span-2">
                        <label class="text-sm font-medium">Buscar por nombre</label>
                        <Input 
                            v-model="filterForm.search" 
                            placeholder="Buscar sucursal..." 
                            @input="applyFilters"
                        />
                    </div>
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-card rounded-lg border overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Casino</TableHead>
                            <TableHead>Teléfono</TableHead>
                            <TableHead>Dirección</TableHead>
                            <TableHead>Base Monedas</TableHead>
                            <TableHead>Base Billetes</TableHead>
                            <TableHead>Activo</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="s in sucursales.data" :key="s.id">
                            <TableCell class="font-semibold">{{ s.nombre }}</TableCell>
                            <TableCell>{{ s.casino?.nombre }}</TableCell>
                            <TableCell class="text-sm">{{ s.telefono || '-' }}</TableCell>
                            <TableCell class="text-sm">{{ s.direccion || '-' }}</TableCell>
                            <TableCell>{{ formatCurrency(s.base_monedas) }}</TableCell>
                            <TableCell>{{ formatCurrency(s.base_billetes) }}</TableCell>
                            <TableCell>
                                <span :class="s.activo ? 'text-green-600' : 'text-red-600'" class="font-semibold">
                                    {{ s.activo ? 'Sí' : 'No' }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <div class="flex gap-2">
                                    <Button variant="outline" size="sm" @click="openModal(s)">
                                        <Pencil class="h-4 w-4" />
                                    </Button>

                                    <AlertDialog>
                                        <AlertDialogTrigger as-child>
                                            <Button variant="destructive" size="sm">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </AlertDialogTrigger>

                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>¿Eliminar Sucursal?</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    ¿Está seguro de eliminar la sucursal "{{ s.nombre }}"?
                                                    <br><br>
                                                    Esta acción no se puede deshacer. No se puede eliminar si tiene lecturas o gastos asociados.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                                <AlertDialogAction @click="deleteSucursal(s)">
                                                    Eliminar
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Paginación -->
            <div class="flex gap-2 justify-end">
                <a v-for="link in sucursales.links" :key="link.label" v-html="link.label" :href="link.url ?? '#'"
                    :class="[
                        'px-3 py-1 rounded text-sm',
                        link.active ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-accent'
                    ]" />
            </div>

            <!-- Modal -->
            <Dialog v-model:open="showModal">
                <DialogContent class="sm:max-w-[600px]">
                    <DialogHeader>
                        <DialogTitle>{{ isEditing ? 'Editar Sucursal' : 'Nueva Sucursal' }}</DialogTitle>
                        <DialogDescription>Complete los datos de la sucursal.</DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="save" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="text-sm font-medium">Casino *</label>
                                <Select v-model="form.casino_id" required>
                                    <SelectTrigger><SelectValue placeholder="Seleccione casino" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="c in casinos" :key="c.id" :value="c.id">{{ c.nombre }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="col-span-2">
                                <label class="text-sm font-medium">Nombre *</label>
                                <Input v-model="form.nombre" placeholder="Nombre de la sucursal" required />
                            </div>

                            <div>
                                <label class="text-sm font-medium">Teléfono</label>
                                <Input v-model="form.telefono" placeholder="Teléfono" />
                            </div>

                            <div>
                                <label class="text-sm font-medium">Dirección</label>
                                <Input v-model="form.direccion" placeholder="Dirección" />
                            </div>

                            <div>
                                <label class="text-sm font-medium">Base Monedas</label>
                                <Input type="number" v-model="form.base_monedas" placeholder="0" step="0.01" />
                            </div>

                            <div>
                                <label class="text-sm font-medium">Base Billetes</label>
                                <Input type="number" v-model="form.base_billetes" placeholder="0" step="0.01" />
                            </div>

                            <div class="col-span-2 flex items-center space-x-2">
                                <Switch v-model:checked="form.activo" id="activo" />
                                <label for="activo" class="text-sm font-medium cursor-pointer">
                                    Sucursal Activa
                                </label>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeModal">Cancelar</Button>
                            <Button type="submit" class="bg-indigo-600 text-white">
                                {{ isEditing ? 'Actualizar' : 'Guardar' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
